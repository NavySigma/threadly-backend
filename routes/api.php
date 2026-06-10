<?php

use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Auth\SocialAuthController;
use App\Http\Controllers\Api\BookmarkController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\FollowController;
use App\Http\Controllers\Api\LikeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PointsLogController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\TagController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\VoteController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::prefix('auth')->group(function () {
    Route::get('/{provider}/redirect', [SocialAuthController::class, 'redirect']);
    Route::get('/{provider}/callback', [SocialAuthController::class, 'callback']);
});

Route::get('/users/{user}', [UserController::class, 'show']);
Route::get('/users/{user}/posts', [UserController::class, 'posts']);

Route::prefix('search')->group(function () {
    Route::get('/', [SearchController::class, 'global']); // GET /api/search?q=laravel
    Route::get('/posts', [SearchController::class, 'posts']);  // GET /api/search/posts?q=laravel
    Route::get('/users', [SearchController::class, 'users']);  // GET /api/search/users?q=john
    Route::get('/tags', [SearchController::class, 'tags']);   // GET /api/search/tags?q=php
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/me', [UserController::class, 'me']);
    Route::put('/me', [UserController::class, 'update']);
    Route::put('/me/password', [UserController::class, 'updatePassword']);

    Route::post('/users/{user}/follow', [FollowController::class, 'follow']);
    Route::delete('/users/{user}/follow', [FollowController::class, 'unfollow']);

    // Lihat history poin milik sendiri
    Route::get('/me/points', [PointsLogController::class, 'index']);
    // Admin lihat history poin user tertentu
    Route::get('/users/{userId}/points', [PointsLogController::class, 'userHistory']);
    Route::post('/users/{userId}/points/recalculate', [PointsLogController::class, 'recalculate']);

    Route::post('/posts', [PostController::class, 'store']);
    Route::put('/posts/{post}', [PostController::class, 'update']);
    Route::delete('/posts/{post}', [PostController::class, 'destroy']);

    Route::get('/posts/{post}/history', [PostController::class, 'history']);
    Route::get('/comments/{comment}/history', [CommentController::class, 'history']);

    Route::post('/posts/{post}/comments', [CommentController::class, 'store']);
    Route::put('/comments/{comment}', [CommentController::class, 'update']);
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy']);
    // Accept / unaccept answer
    Route::post('/posts/{post}/comments/{comment}/accept', [CommentController::class, 'accept']);
    Route::delete('/posts/{post}/unaccept', [CommentController::class, 'unaccept']);

    Route::get('/me/bookmarks', [BookmarkController::class, 'index']);
    Route::post('/bookmarks', [BookmarkController::class, 'store']);
    Route::delete('/bookmarks/{post}', [BookmarkController::class, 'destroy']);
    Route::get('/bookmarks/{post}/check', [BookmarkController::class, 'check']);

    // Votes
    Route::post('/votes', [VoteController::class, 'vote']);

    // Likes (bookmark)
    Route::post('/likes', [LikeController::class, 'like']);
    Route::delete('/likes', [LikeController::class, 'unlike']);
    Route::get('/me/bookmarks/posts', [LikeController::class, 'likedPosts']);
    Route::get('/me/bookmarks/comments', [LikeController::class, 'likedComments']);

    // Semua user bisa report
    Route::post('/reports', [ReportController::class, 'store']);
    // Mod & Admin only
    Route::get('/reports', [ReportController::class, 'index']);
    Route::get('/reports/{report}', [ReportController::class, 'show']);
    Route::patch('/reports/{report}/resolve', [ReportController::class, 'resolve']);

    // CATEGORIES
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::put('/categories/{category}', [CategoryController::class, 'update']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

    // TAGS
    Route::post('/tags', [TagController::class, 'store']);
    Route::put('/tags/{tag}', [TagController::class, 'update']);
    Route::delete('/tags/{tag}', [TagController::class, 'destroy']);

    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::patch('/notifications/{notification}/read', [NotificationController::class, 'read']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'readAll']);
    Route::delete('/notifications/read', [NotificationController::class, 'destroyRead']);
    Route::delete('/notifications/{notification}', [NotificationController::class, 'destroy']);

    Route::post('/logout', [AuthController::class, 'logout']);
});

// CATEGORIES
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

// TAGS
Route::get('/tags', [TagController::class, 'index']);
Route::get('/tags/{tag}', [TagController::class, 'show']);

// COMMENTS
Route::get('/posts/{post}/comments', [CommentController::class, 'index']);

// POSTINGAN
Route::get('/posts', [PostController::class, 'index']);
Route::get('/posts/{post}', [PostController::class, 'show']);

// FOLLOW
Route::get('/users/{user}/followers', [FollowController::class, 'followers']);
Route::get('/users/{user}/following', [FollowController::class, 'following']);

// RESET PASSWORD
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword']);
