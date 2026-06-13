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
            // Users yang active (updated dalam 24 jam terakhir)
            $usersOnline = User::where('updated_at', '>=', now()->subHours(24))->count();

            // Total keseluruhan
            $questions = Post::where('status', 'open')->count();
            $answers   = Comment::count();
            $upvotes   = Vote::where('vote_type', 'up')->count();

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
