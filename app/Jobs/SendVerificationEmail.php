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

class SendVerificationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Khởi tạo job instance.
     */
    public function __construct(
        protected User $user,
        protected string $token
    ) {}

    /**
     * Thực thi job.
     */
    public function handle(): void
    {
        Mail::to($this->user->email)->send(new VerifyEmailMail($this->user, $this->token));
    }
}
