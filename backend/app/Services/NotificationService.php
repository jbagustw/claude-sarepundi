<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Single entry point for both notification channels CLAUDE.md calls for
 * (Email + in-app; WhatsApp is explicitly optional there and needs a
 * third-party Fonnte/Wablas account we don't have, so it's left out here).
 * Every event that should notify someone goes through here, not through
 * ad-hoc Mail::send()/Notification::create() calls scattered in
 * controllers — keeps the two channels from drifting out of sync.
 */
class NotificationService
{
    public function notify(User $user, string $type, string $title, string $message): Notification
    {
        $notification = Notification::create([
            'user_id' => $user->id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
        ]);

        $this->sendEmail($user, $title, $message);

        return $notification;
    }

    private function sendEmail(User $user, string $title, string $message): void
    {
        try {
            Mail::raw($message, function ($mail) use ($user, $title) {
                $mail->to($user->email)->subject($title);
            });
        } catch (\Throwable $e) {
            // Email is a secondary channel here — the in-app notification
            // above already landed, so a mail transport hiccup shouldn't
            // bubble up and fail the booking/moderation action that
            // triggered it.
            Log::warning('Failed to send notification email', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
