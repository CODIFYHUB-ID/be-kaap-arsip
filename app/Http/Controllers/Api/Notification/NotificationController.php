<?php

namespace App\Http\Controllers\Api\Notification;

use App\Http\Controllers\Controller;
use App\Support\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Get paginated notifications for current authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $limit = (int) $request->query('limit', 15);

        $notifications = $user->notifications()->paginate($limit);
        $unreadCount = $user->unreadNotifications()->count();

        $items = $notifications->getCollection()->map(function ($n) {
            return [
                'id' => $n->id,
                'title' => $n->data['title'] ?? 'Pemberitahuan Sistem',
                'message' => $n->data['message'] ?? '',
                'type' => $n->data['type'] ?? 'info',
                'url' => $n->data['url'] ?? null,
                'meta' => $n->data['meta'] ?? null,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ];
        });

        return $this->success([
            'items' => $items,
            'unread_count' => $unreadCount,
            'pagination' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ], 'Daftar notifikasi berhasil diambil.');
    }

    /**
     * Get unread notifications count (lightweight endpoint for polling).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->unreadNotifications()->count();

        return $this->success([
            'unread_count' => $count,
        ], 'Jumlah notifikasi belum dibaca.');
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->find($id);

        if ($notification && ! $notification->read_at) {
            $notification->markAsRead();
        }

        return $this->success(null, 'Notifikasi berhasil ditandai sudah dibaca.');
    }

    /**
     * Mark all notifications as read for current user.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $this->success(null, 'Semua notifikasi berhasil ditandai sudah dibaca.');
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->where('id', $id)->delete();

        return $this->success(null, 'Notifikasi berhasil dihapus.');
    }
}
