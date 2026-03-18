<?php

namespace App\Domain\Setting\Actions;

use App\Domain\Shared\Actions\BaseAction;
use Illuminate\Support\Facades\Mail;

/**
 * Action: Send a test email using current mail configuration.
 */
class SendTestMailAction extends BaseAction
{
    /**
     * Execute the action.
     *
     * @throws \Exception
     */
    public function execute(string $recipientEmail): void
    {
        Mail::raw('This is a test email from '.config('app.name').'.', function ($message) use ($recipientEmail) {
            $message->to($recipientEmail)
                ->subject('Test Email - '.config('app.name'));
        });
    }
}
