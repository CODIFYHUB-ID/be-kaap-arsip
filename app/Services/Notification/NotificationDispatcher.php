<?php

namespace App\Services\Notification;

use App\Models\User;
use App\Notifications\ActivityNotification;
use Illuminate\Support\Facades\Notification;

class NotificationDispatcher
{
    /**
     * Send notification to specific roles or all staff except the trigger user.
     */
    public static function notifyRoles(
        array $roleNames,
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        ?array $meta = null,
        ?int $excludeUserId = null
    ): void {
        try {
            $query = User::role($roleNames);

            if ($excludeUserId) {
                $query->where('id', '!=', $excludeUserId);
            }

            $users = $query->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new ActivityNotification(
                    title: $title,
                    message: $message,
                    type: $type,
                    url: $url,
                    meta: $meta
                ));
            }
        } catch (\Throwable $e) {
            \Log::error('Failed sending role notification: ' . $e->getMessage());
        }
    }

    /**
     * Notify all active users except the actor.
     */
    public static function notifyAll(
        string $title,
        string $message,
        string $type = 'info',
        ?string $url = null,
        ?array $meta = null,
        ?int $excludeUserId = null
    ): void {
        try {
            $query = User::where('status', 'active');

            if ($excludeUserId) {
                $query->where('id', '!=', $excludeUserId);
            }

            $users = $query->get();

            if ($users->isNotEmpty()) {
                Notification::send($users, new ActivityNotification(
                    title: $title,
                    message: $message,
                    type: $type,
                    url: $url,
                    meta: $meta
                ));
            }
        } catch (\Throwable $e) {
            \Log::error('Failed sending broadcast notification: ' . $e->getMessage());
        }
    }
}
