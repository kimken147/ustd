<?php

namespace App\Jobs;

use App\Exceptions\PaymayaResponseError;
use App\Model\UserChannel;
use App\Model\UserChannelAccount;
use App\Services\Maya\MayaService;
use App\Services\Maya\PayMayaApiService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class MayaChangeEmail implements ShouldQueue
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
        MayaService $mayaService,
        PayMayaApiService $mayaApiService
    ) {
        if ($this->attempts() > 1) {
            $this->delete();
            return true;
        }
        $detail = $this->account->detail;

        if ($this->currentStatus == "init") {
            try {
                $detail["email_status"] = "login";
                $detail["newEmail"] = $this->data["newEmail"];
                $this->account->update([
                    "detail" => $detail,
                ]);
                $account = $mayaService->userChannelAccountLogin(
                    $this->account
                );
                $detail = $account->detail;
                $detail["email_status"] = "need_otp";
            } catch (\Throwable $th) {
                $detail["email_status"] = "login_failed";
            } finally {
                $this->account->update([
                    "detail" => $detail,
                ]);
            }
        } elseif ($this->currentStatus == "need_otp") {
            $detail["email_status"] = "enter_otp";
            $this->account->update([
                "detail" => $detail,
            ]);
            $result = $mayaService->userChannelOtpLogin(
                $this->account,
                $this->data["otp"]
            );
            $appToken = $result["token"];
            $accessToken = $detail["accessToken"];
            $newEmail = $detail["newEmail"];
            $password = $detail["mpin"];
            try {
                $result = $mayaService->changeEmail(
                    $password,
                    $newEmail,
                    $accessToken,
                    $appToken
                );
                $result = $mayaService->verifyEmail($accessToken, $appToken);
                $detail["email_status"] = "success";
                $detail["email"] = $newEmail;
            } catch (PaymayaResponseError $e) {
                $message = $mayaService->getMayaServiceExceptionMessage($e);
                Log::error(
                    "UserChannelAccount: " .
                        $this->account->account .
                        "modify email error. message: ",
                    $message
                );
                $detail["email_status"] = "failed";
            } catch (Exception $e) {
                $message = $e->getMessage();
                Log::error(
                    "UserChannelAccount: " .
                        $this->account->account .
                        "modify email error. message: ",
                    $message
                );
                $detail["email_status"] = "failed";
            } finally {
                $this->account->update([
                    "detail" => $detail,
                ]);
            }
        }
    }
}
