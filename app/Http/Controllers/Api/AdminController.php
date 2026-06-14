<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PostEditHistory;
use App\Models\CommentEditHistory;
use App\Models\Post;
use App\Models\Comment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function editHistory(Request $request): JsonResponse
    {
        if (! $request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $perPage = (int) ($request->per_page ?? 20);
        $page = (int) ($request->page ?? 1);
        $typeFilter = $request->type; // 'post', 'comment', or null (all)
        $dateFilter = $request->date;  // 'YYYY-MM-DD' or null

        $merged = collect();

        // Post history
        if (! $typeFilter || $typeFilter === 'post') {
            $query = PostEditHistory::with('editor:id,username,avatar_url');
            if ($dateFilter) {
                $query->whereDate('edited_at', $dateFilter);
            }
            $history = $query->get();
            $postIds = $history->pluck('post_id')->unique();
            $posts = Post::whereIn('id', $postIds)->select('id', 'title', 'user_id')->get()->keyBy('id');
            $authorIds = $posts->pluck('user_id')->unique();
            $authors = User::whereIn('id', $authorIds)->select('id', 'username')->get()->keyBy('id');

            $mapped = $history->map(fn ($h) => [
                'id'          => $h->id,
                'type'        => 'post',
                'title'       => $posts[$h->post_id]?->title,
                'author'      => $authors[$posts[$h->post_id]?->user_id]?->username,
                'editor'      => $h->editor?->username,
                'editor_avatar' => $h->editor?->avatar_url,
                'body_before' => $h->body_before,
                'body_after'  => $h->body_after,
                'reason'      => $h->reason,
                'edited_at'   => $h->edited_at,
            ]);
            $merged = $merged->concat($mapped);
        }

        // Comment history
        if (! $typeFilter || $typeFilter === 'comment') {
            $query = CommentEditHistory::with('editor:id,username,avatar_url');
            if ($dateFilter) {
                $query->whereDate('edited_at', $dateFilter);
            }
            $history = $query->get();
            $commentIds = $history->pluck('comment_id')->unique();
            $comments = Comment::whereIn('id', $commentIds)->select('id', 'post_id', 'user_id')->get()->keyBy('id');
            $commentPostIds = $comments->pluck('post_id')->unique();
            $commentPosts = Post::whereIn('id', $commentPostIds)->select('id', 'title')->get()->keyBy('id');
            $commentAuthorIds = $comments->pluck('user_id')->unique();
            $commentAuthors = User::whereIn('id', $commentAuthorIds)->select('id', 'username')->get()->keyBy('id');

            $mapped = $history->map(fn ($h) => [
                'id'          => $h->id,
                'type'        => 'comment',
                'title'       => $commentPosts[$comments[$h->comment_id]?->post_id]?->title,
                'author'      => $commentAuthors[$comments[$h->comment_id]?->user_id]?->username,
                'editor'      => $h->editor?->username,
                'editor_avatar' => $h->editor?->avatar_url,
                'body_before' => $h->body_before,
                'body_after'  => $h->body_after,
                'reason'      => null,
                'edited_at'   => $h->edited_at,
            ]);
            $merged = $merged->concat($mapped);
        }

        $merged = $merged->sortByDesc('edited_at')->values();

        $total = $merged->count();
        $items = $merged->slice(($page - 1) * $perPage, $perPage)->values();

        return response()->json([
            'data' => $items,
            'meta' => [
                'current_page' => $page,
                'last_page'    => (int) max(1, ceil($total / $perPage)),
                'per_page'     => $perPage,
                'total'        => $total,
            ],
        ]);
    }
}
