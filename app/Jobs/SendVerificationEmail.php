<?php

namespace App\Jobs;

use App\Models\User;
use App\Mail\VerifyEmailMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Khởi tạo job instance.
     */
    public function __construct(
        public User $user,
        public string $token
    ) {}

    /**
     * Thực thi job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(new VerifyEmailMail($this->user, $this->token));
    }

    /**
     * Xử lý khi job thất bại hoàn toàn.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendVerificationEmail job failed completely for user ' . $this->user->id . ': ' . $exception->getMessage(), [
            'user_id' => $this->user->id,
            'email' => $this->user->email,
            'exception' => $exception->getTraceAsString(),
        ]);
    }
}
