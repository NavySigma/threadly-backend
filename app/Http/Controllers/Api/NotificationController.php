<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    // List notifikasi milik user
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->with('actor:id,username,avatar_url')
            ->when($request->unread_only, fn($q) =>
                $q->where('is_read', false)
            )
            ->latest('created_at')
            ->paginate(20);

        $unreadCount = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'data'         => $notifications,
        ]);
    }

    // Mark satu notif as read
    public function read(Request $request, Notification $notification): JsonResponse
    {
        // Pastikan notif milik user yang login
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    // Mark semua notif as read
    public function readAll(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    // Hapus satu notif
    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notifikasi dihapus.']);
    }

    // Hapus semua notif yang sudah dibaca
    public function destroyRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', true)
            ->delete();

        return response()->json(['message' => 'Semua notifikasi yang sudah dibaca dihapus.']);
    }
}
