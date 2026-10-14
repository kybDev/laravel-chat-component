<?php

namespace Vendor\LaravelChat\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Vendor\LaravelChat\Models\ChatMessage;

class SendMessageNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public ChatMessage $message)
    {
    }

    public function handle(): void
    {
        // Extend this job for notifications and delivery integration.
    }
}
