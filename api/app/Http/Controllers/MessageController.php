<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Message;
use App\Http\Resources\Message as MessageResponse;
use App\Http\Resources\MessageCollection;
use App\Http\Resources\MessageContact;

class MessageController extends Controller
{
    public function history(Request $request, User $to)
    {
        $user = \Auth::user();
        // 權限判斷

        $messages = Message::with('from', 'to')->where(function ($builder) use ($to) {
            $builder->where('from_id', $to->id)->orWhere('to_id', $to->id);
        })
            ->when($request->start_at, function ($builder, $startAt) {
                $builder->where('ceated_at', '>=', $startAt);
            })
            ->when($request->end_at, function ($builder, $endAt) {
                $builder->where('ended_at', '<=', $endAt);
            })
            ->when($request->last_message_id, function ($builder, $lastMessageId) {
                $builder->where('id', '<', $lastMessageId);
            })
            ->when($request->input('limit', 100), function ($builder, $limit) {
                $builder->limit($limit);
            })
            ->orderByDesc('created_at')
            ->get();

        return MessageCollection::make($messages);
    }

    public function contacts(Request $request)
    {
        $user = \Auth::user();
        $user->load('toSelfMessages', 'fromSelfMessages');
        // 權限判斷

        if (in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SUB_ACCOUNT])) {
            $query = User::where('role', User::ROLE_PROVIDER)->where('last_login_at', '>', now()->subDays(7));

            $usersId = [$request->input('id')];
            if (count($usersId) > 0) {
                $query->orWhereIn('id', $usersId);
            }

            $others = $query->get();
            return MessageContact::collection($others);
        } else {
            $others = User::whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUB_ACCOUNT])->orderBy('last_activity_at', 'desc')->first();
            return MessageContact::collection([$others]);
        }
    }

    public function sendText(Request $request)
    {
        abort_if(!$request->has('to'), Response::HTTP_BAD_REQUEST, __('common.No recipient specified'));

        $user = \Auth::user();
        // 權限判斷

        $message = Message::create([
            'from_id' => $user->id,
            'to_id' => $request->input('to'),
            'text' => $request->input('text'),
            'detail' => []
        ]);
        $message->load('from', 'to');
        return MessageResponse::make($message);
    }

    public function sendFile(Request $request)
    {
        abort_if(!$request->has('to'), Response::HTTP_BAD_REQUEST, __('common.No recipient specified'));

        abort_if(!$request->has('file_name') || !$request->hasFile('file'), Response::HTTP_BAD_REQUEST, __('common.No file specified'));

        $user = \Auth::user();
        // 權限判斷

        $date = now()->toDateString();
        $extension = $request->file('file')->extension();
        $fileName =  $user->id . '/' . $date;
        $path = $request->file('file')->store($fileName, 'im-files');
        $filePath = explode('/', $path);
        $fileUrl = route('message.file', ['id' => $filePath[0], 'date' => $filePath[1], 'path' => $filePath[2]]);

        $message = Message::create([
            'from_id' => $user->id,
            'to_id' => $request->input('to'),
            'text' => $fileUrl,
            'type' => Message::TYPE_FILE,
            'detail' => [
                'url' => $fileUrl,
                'name' => $request->input('file_name'),
                'size' => $request->file('file')->getSize(),
                'extension' => $extension
            ]
        ]);

        $message->load('from', 'to');

        return MessageResponse::make($message);
    }

    public function showFile(Request $request, $id, $date, $path)
    {
        if (env('APP_ENV') != 'local') {
            URL::forceScheme('https');
        }

        $user = \Auth::user();
        // 權限判斷

        $filePath = implode('/', [$id, $date, $path]);
        $extension = explode('.', $path)[1];

        if ($extension == 'jpg') {
            $extension = 'jpeg';
        }

        return response()->make(
            Storage::disk('im-files')->get($filePath),
            200,
            ['Content-Type' => "image/${extension}"]
        );
    }

    public function read(Request $request)
    {
        $user = \Auth::user();
        // 權限判斷

        Message::unread()->where([
            'to_id' => $user->id
        ])->update(['readed_at' => now()]);

        return response()->json([]);
    }
}
