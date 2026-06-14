<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\Post;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private function mapType(string $type): string
    {
        return match ($type) {
            'upvote'           => 'upvote_post',
            'like'             => 'like_post',
            'reply'            => 'reply_comment',
            'reply_on_post'    => 'reply_on_post',
            'comment'          => 'comment_post',
            'answer_accepted'  => 'accepted_answer',
            'follow'           => 'follow_user',
            'complete_profile' => 'complete_profile',
            'report_confirmed' => 'report_confirmed',
            'report_penalized' => 'report_penalized',
            'level_up'         => 'level_up',
            default            => $type,
        };
    }

    private function getCategory(string $type): string
    {
        return match ($type) {
            'upvote_post', 'like_post', 'accepted_answer' => 'posts',
            'reply_comment', 'reply_on_post', 'comment_post' => 'comments',
            'follow_user' => 'users',
            default       => 'system',
        };
    }

    private function resolveTargetTitle(?string $refId, ?string $refType): ?string
    {
        if (!$refId || !$refType) return null;

        if ($refType === 'post') {
            return Post::where('id', $refId)->value('title');
        }

        if ($refType === 'comment') {
            $comment = Comment::with('post:id,title')->where('id', $refId)->first();
            return $comment?->post?->title;
        }

        return null;
    }

    private function resolveMessage(?string $refId, ?string $refType): ?string
    {
        if (!$refId || !$refType) return null;

        if ($refType === 'comment') {
            return Comment::where('id', $refId)->value('body');
        }

        return null;
    }

    private function resolvePostId(?string $refId, ?string $refType): ?string
    {
        if (!$refId || !$refType) return null;

        if ($refType === 'post') {
            return $refId;
        }

        if ($refType === 'comment') {
            return Comment::where('id', $refId)->value('post_id');
        }

        return null;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $category = $request->category; // users | posts | comments | system
        $isDone = $request->boolean('is_done');

        $query = Notification::where('user_id', $user->id)
            ->with('actor:id,username,avatar_url')
            ->latest('created_at');

        // Filter by done/undone
        if ($isDone) {
            $query->where('is_read', true);
        } else {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate(20);

        // Transform items
        $items = $notifications->map(function ($n) use ($category) {
            $mappedType = $this->mapType($n->type);
            $itemCategory = $this->getCategory($mappedType);

            return [
                'id'           => $n->id,
                'type'         => $mappedType,
                'category'     => $itemCategory,
                'is_read'      => $n->is_read,
                'is_done'      => $n->is_read,
                'actor'        => $n->actor,
                'target_id'    => $n->reference_id,
                'target_type'  => $n->reference_type,
                'target_title' => $this->resolveTargetTitle($n->reference_id, $n->reference_type),
                'post_id'      => $this->resolvePostId($n->reference_id, $n->reference_type),
                'message'      => $this->resolveMessage($n->reference_id, $n->reference_type),
                'created_at'   => $n->created_at,
                'read_at'      => $n->is_read ? $n->created_at : null,
            ];
        });

        // Apply category filter after transform
        if ($category) {
            $items = $items->filter(fn($i) => $i['category'] === $category)->values();
        }

        $unreadCount = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'data' => $items,
            'meta' => [
                'unread_count' => $unreadCount,
                'total_count'  => $notifications->total(),
            ],
        ]);
    }

    public function read(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notification->update(['is_read' => true]);

        return response()->json(['message' => 'Notifikasi ditandai sudah dibaca.']);
    }

    public function undone(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notification->update(['is_read' => false]);

        return response()->json(['message' => 'Notifikasi dikembalikan ke inbox.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notifikasi dihapus.']);
    }

    public function destroyRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->where('is_read', true)
            ->delete();

        return response()->json(['message' => 'Semua notifikasi yang sudah dibaca dihapus.']);
    }
}
