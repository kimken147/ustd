<?php

namespace App\Jobs;

use App\Model\UserChannelAccount;
use App\Services\Maya\MayaLoginService;
use App\Services\Maya\MayaService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MayaChangePassword implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    private UserChannelAccount $account;
    private string $currentStatus;
    private array $data;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(
        UserChannelAccount $account,
        string $status,
        array $data = []
    ) {
        $this->account = $account;
        $this->queue = config("queue.queue-priority.medium");
        $this->currentStatus = $status;
        $this->data = $data;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(
        MayaLoginService $mayaLoginService,
        MayaService $mayaService
    ) {
        if ($this->attempts() > 1) {
            $this->delete();
            return false;
        }

        $detail = $this->account->detail;
        if (!isset($detail["mpin"])) {
            return false;
        }

        if ($this->currentStatus == "init") {
            Log::info(
                "UserChannelAccount: " .
                    $this->account->account .
                    " change password login."
            );
            try {
                $detail["password_status"] = "login";
                $result = $mayaLoginService->apiLogin(
                    $this->account->account,
                    $detail["mpin"]
                );
            } catch (Exception $e) {
                $detail["password_status"] = "login failed";
                Log::error(
                    "UserChannelAccount: " .
                        $this->account->account .
                        "change password login fail.",
                    compact("message")
                );
                $message = $mayaService->getMayaServiceExceptionMessage($e);
            } finally {
                $this->account->update([
                    "detail" => $detail,
                ]);
            }
            $detail["password_status"] = "need_otp";
            $detail["expiresChallengeId"] = $result["expiresChallengeId"];
            $detail["accessToken"] = $result["accessToken"];
            $detail["newPassword"] = $this->data["newPassword"];
            $this->account->update(["detail" => $detail]);
        } elseif ($this->currentStatus == "need_otp") {
            Log::info(
                "UserChannelAccount: " .
                    $this->account->account .
                    "change password otp login."
            );
            $detail["password_status"] = "enter_otp";
            $this->account->update([
                "detail" => $detail,
            ]);
            $expiresChallengeId = $detail["expiresChallengeId"];
            $otp = $this->data["otp"];
            $password = $detail["mpin"];
            $newPassword = $detail["newPassword"];
            $accessToken = $detail["accessToken"];

            try {
                $profile = $mayaLoginService->apiLoginNext(
                    $expiresChallengeId,
                    $otp,
                    $accessToken
                );
                $mayaService->changePassword(
                    $password,
                    $newPassword,
                    $accessToken,
                    $profile["token"]
                );
                $this->account = $mayaService->syncAccount(
                    $this->account->id,
                    $accessToken,
                    $profile["token"]
                );
                $detail = $this->account->detail;
                $detail["account_status"] = "pass";
                $detail["password_status"] = "completed";
                $detail["mpin"] = $newPassword;
            } catch (Exception $e) {
                $detail["password_status"] = "failed";
                $detail["account_status"] = "fail";
                $detail["sync_at"] = now();
                $detail["sync_status"] = "account_limited";
                $message = $mayaService->getMayaServiceExceptionMessage($e);
                Log::error(
                    "UserChannelAccount: " .
                        $this->account->account .
                        "change password login fail.",
                    compact("message")
                );
            } finally {
                $this->account->update([
                    "detail" => $detail,
                ]);
            }
        }
    }
}
