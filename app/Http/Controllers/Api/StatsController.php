<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function community(): JsonResponse
    {
        $stats = cache()->remember('community.stats', now()->addMinutes(5), function () {
            $usersOnline = User::where('updated_at', '>=', now()->subHour())->count();
            $questions   = Post::where('status', 'open')
                ->where('created_at', '>=', now()->subHour())
                ->count();
            $answers     = Comment::where('created_at', '>=', now()->subHour())->count();
            $upvotes     = Vote::where('vote_type', 'up')
                ->where('created_at', '>=', now()->subHour())
                ->count();

            return [
                'users_online' => $usersOnline,
                'questions'    => $questions,
                'answers'      => $answers,
                'upvotes'      => $upvotes,
            ];
        });

        return response()->json(['data' => $stats]);
    }
}
