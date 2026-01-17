<?php

namespace App\Http\Controllers\Provider;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use App\Http\Controllers\Controller;

use App\Channel\Resources\UserChannelAccount as UserChannelAccountResource;
use App\Http\Requests\UploadImageRequest;

class AccountController extends Controller
{
    public function index(Request $request, $channel)
    {
        $operator = \Auth::user();
        // 權限判斷

        return UserChannelAccountResource::collection($channel->getAccounts());
    }

    public function show(Request $request, $channel, $account)
    {
        $operator = \Auth::user();
        // 權限判斷

        $account = $channel->getAccounts()->where('id', $account)->first();

        return UserChannelAccountResource::make($account);
    }

    public function store(Request $request, $channel)
    {
        $operator = \Auth::user();
        // 權限判斷

        $data = $request->all();

        try {
            $channel->addAccount($data);

        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }

        return new UserChannelAccountResource($channel);
    }

    public function update(Request $request, $channel, $account)
    {
        $operator = \Auth::user();
        // 權限判斷

        $data = $request->all();

        try {
            $account = $channel->updateAccount($account, $data);

            return new UserChannelAccountResource($account);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }
    }

    public function destroy(Request $request, $channel, $account)
    {
        $operator = \Auth::user();
        // 權限判斷

        $channel->deleteAccount($account);

        return response()->json([
            'data' => 'OK'
        ]);
    }

    public function uploadQRCode(UploadImageRequest $request, $channel, $account)
    {
        $name = "{$channel->user_id}_{$channel->channel_code}_{$channel->min_amount}_{$channel->max_amount}_{$account}";
        $ext = $request->file->getClientOriginalExtension();
        $path = "/qrcodes/originals/{$name}.{$ext}";

        try {
            $result = Storage::disk('s3')->put($path, file_get_contents($request->file('file')));

            $channel->updateAccount($account, ['detail' => ['qrcode' => $path]]);
            return response()->json([
                'data' => $path
            ]);
        } catch (\Exception $e) {
            abort(500, $e->getMessage());
        }
    }

    public function verify()
    {

    }

    public function statistic($request, $id)
    {

    }
}
