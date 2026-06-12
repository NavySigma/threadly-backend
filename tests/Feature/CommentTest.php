<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CommentTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat '.Str::random(4),
            'slug' => 'cat-'.Str::random(4),
        ]);
    }

    private function makeOpenPost(string $userId): Post
    {
        return Post::create([
            'user_id' => $userId,
            'category_id' => $this->makeCategory()->id,
            'title' => 'Open post title for testing comments',
            'body' => 'Open post body content for testing comment functionality.',
            'status' => 'open',
        ]);
    }

    // ── GET /api/posts/{post}/comments ────────────────────────────────

    public function test_GET_list_mengembalikan_komentar_post(): void
    {
        [$user] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'body' => 'This is a test comment body.',
        ]);

        $this->getJson("/api/posts/{$post->id}/comments")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    // ── POST /api/posts/{post}/comments ───────────────────────────────

    public function test_POST_komentar_berhasil(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $userHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($userHeaders)->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'This is my comment on this post.',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Komentar berhasil dibuat.');
    }

    public function test_POST_komentar_gagal_tanpa_auth(): void
    {
        [$owner] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'Anonymous comment attempt here.',
        ])->assertStatus(401);
    }

    public function test_POST_komentar_gagal_post_closed(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $category = $this->makeCategory();
        $post = Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Closed post for comment test',
            'body' => 'Body of the closed post for testing.',
            'status' => 'closed',
        ]);

        $this->withHeaders($headers)->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'Comment on closed post.',
        ])->assertStatus(422);
    }

    public function test_POST_reply_berhasil(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $userHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $parent = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'body' => 'Parent comment body content here.',
        ]);

        $this->withHeaders($userHeaders)->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'This is my reply to the parent comment.',
            'parent_id' => $parent->id,
        ])->assertStatus(201);
    }

    public function test_POST_reply_ditolak_lebih_dari_1_level(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $userHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $parent = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'body' => 'Level 1 comment body here.',
        ]);

        $reply = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'parent_id' => $parent->id,
            'body' => 'Level 2 reply body here.',
        ]);

        $this->withHeaders($userHeaders)->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'Trying to nest deeper than allowed.',
            'parent_id' => $reply->id,
        ])->assertStatus(422);
    }

    public function test_owner_post_max_2_komentar_di_post_sendiri(): void
    {
        [$owner, $ownerHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        for ($i = 0; $i < 2; $i++) {
            Comment::create([
                'post_id' => $post->id,
                'user_id' => $owner->id,
                'body' => "Owner comment number {$i} on own post.",
            ]);
        }

        $this->withHeaders($ownerHeaders)->postJson("/api/posts/{$post->id}/comments", [
            'body' => 'Third comment by owner on their own post.',
        ])->assertStatus(422);
    }

    // ── PUT /api/comments/{comment} ───────────────────────────────────

    public function test_PUT_berhasil_oleh_owner(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($user->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $user->id,
            'body' => 'Original comment body content here.',
        ]);

        $this->withHeaders($headers)->putJson("/api/comments/{$comment->id}", [
            'body' => 'Updated comment body content here.',
        ])->assertStatus(200);
    }

    public function test_PUT_ditolak_bukan_owner(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $otherHeaders] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'body' => 'Comment that others cannot edit.',
        ]);

        $this->withHeaders($otherHeaders)->putJson("/api/comments/{$comment->id}", [
            'body' => 'Trying to edit someone else comment.',
        ])->assertStatus(403);
    }

    // ── POST /api/posts/{post}/comments/{comment}/accept ──────────────

    public function test_accept_berhasil_oleh_owner_post(): void
    {
        [$owner, $ownerHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        [$answerer] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $answerer->id,
            'body' => 'This is the answer to your question.',
        ]);

        $this->withHeaders($ownerHeaders)
            ->postJson("/api/posts/{$post->id}/comments/{$comment->id}/accept")
            ->assertStatus(200);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'accepted_answer_id' => $comment->id,
            'is_answered' => true,
        ]);
    }

    public function test_accept_ditolak_bukan_owner_post(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [$other, $otherH] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $other->id,
            'body' => 'An answer from another user here.',
        ]);

        $this->withHeaders($otherH)
            ->postJson("/api/posts/{$post->id}/comments/{$comment->id}/accept")
            ->assertStatus(403);
    }

    // ── DELETE /api/posts/{post}/unaccept ─────────────────────────────

    public function test_unaccept_berhasil(): void
    {
        [$owner, $ownerHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        [$answerer] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $answerer->id,
            'body' => 'Accepted answer body content here.',
            'is_accepted' => true,
        ]);

        $post->update([
            'accepted_answer_id' => $comment->id,
            'is_answered' => true,
        ]);

        $this->withHeaders($ownerHeaders)
            ->deleteJson("/api/posts/{$post->id}/unaccept")
            ->assertStatus(200);

        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'is_answered' => false,
        ]);
    }
}

