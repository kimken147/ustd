<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Announcement;
use App\Models\AnnouncementUser;
use App\Http\Resources\AnnouncementCollection;
use Illuminate\Database\Eloquent\Builder;

class AnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $this->validate($request, [
            'started_at'       => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'ended_at'         => ['nullable', 'date_format:'.DateTimeInterface::ATOM],
            'title'            => 'nullable|string',
            'content'          => 'nullable|string',
            'notes'            => 'nullable|string',
        ]);

        $startedAt = $request->started_at ? optional(Carbon::make($request->started_at)->tz(config('app.timezone'))) : today();
        $endedAt = $request->ended_at ? Carbon::make($request->ended_at)->tz(config('app.timezone')) : now();

        $user = auth()->user();

        if ($user->isAdmin() || $user->isSubAccount()) {
            $results = Announcement::with('users')->orderByDesc('created_at');
            
            $results->when($request->started_at, function ($builder, $startedAt) {
                $builder->where('started_at', '>=', Carbon::make($startedAt)->tz(config('app.timezone')));
            });

            $results->when($request->ended_at, function ($builder, $endedAt) {
                $builder->where('started_at', '<=', Carbon::make($endedAt)->tz(config('app.timezone')));
            });

            $results->when($request->for_who, function ($builder, $forWho) {
                if (in_array('merchants', $forWho)) { $builder->where('for_merchant', 1); }
                if (in_array('providers', $forWho)) { $builder->where('for_provider', 1); }
            });

            $results->when($request->targets, function ($builder, $targets) {
                $builder->whereHas('users', function (Builder $query) use ($targets) {
                    $query->whereIn('user_id', $targets);
                });
            });

            $results->when($request->title, function ($builder, $title) {
                $builder->where('title', 'like', "%$title%");
            });

            $results->when($request->content, function ($builder, $content) {
                $builder->where('content', 'like', "%$content%");
            });

            $results->when($request->notes, function ($builder, $notes) {
                $builder->where('notes', 'like', "%$notes%");
            });

        } else {
            $query = Announcement::with('users')
                ->where('started_at', '<=', now())
                ->where(function ($query) {
                    $query->whereNull('ended_at')->orWhere('ended_at', '>=', now());
                })
                ->whereHas('users', function (Builder $query) use ($user) {
                    $query->where('user_id', $user->id);
                });

            if ($user->role == User::ROLE_MERCHANT) {
                $query->orWhere('for_merchant', true);
            }

            if ($user->role == User::ROLE_PROVIDER) {
                $query->orWhere('for_provider', true);
            }

            $results = $query->orderByDesc('started_at');
        }

        return AnnouncementCollection::make($results->paginate());
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'content' => 'required',
            'started_at' => 'required'
        ]);

        $user = auth()->user();


        DB::transaction(function () use ($request) {
            $announcement = Announcement::create($request->all());
            foreach ($request->providers as $to) {
                AnnouncementUser::create(['announcement_id' => $announcement->id, 'user_id' => $to]);
            }
            foreach ($request->merchants as $to) {
                AnnouncementUser::create(['announcement_id' => $announcement->id, 'user_id' => $to]);
            }
        });

        return response()->noContent();
    }

    public function update(Request $request, Announcement $announcement)
    {
        $user = auth()->user();

        DB::transaction(function () use ($request, $announcement) {
            $announcement->update($request->all());

            AnnouncementUser::where('announcement_id', $announcement->id)->delete();

            foreach ($request->providers as $to) {
                AnnouncementUser::create(['announcement_id' => $announcement->id, 'user_id' => $to]);
            }
            foreach ($request->merchants as $to) {
                AnnouncementUser::create(['announcement_id' => $announcement->id, 'user_id' => $to]);
            }
        });

        return response()->noContent();
    }

    public function destroy(Request $request, Announcement $announcement)
    {
        $user = auth()->user();

        DB::transaction(function () use ($announcement) {
            $announcement->delete();
            AnnouncementUser::where('announcement_id', $announcement->id)->delete();
        });

        return response()->noContent();
    }
}
