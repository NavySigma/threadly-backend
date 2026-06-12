<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class VoteTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    private function makeOpenPost(string $userId): Post
    {
        $category = Category::create([
            'name' => 'Cat '.Str::random(4),
            'slug' => 'cat-'.Str::random(4),
        ]);

        return Post::create([
            'user_id' => $userId,
            'category_id' => $category->id,
            'title' => 'Post title for vote testing purposes',
            'body' => 'Post body content for vote testing purposes here.',
            'status' => 'open',
        ]);
    }

    public function test_upvote_post_berhasil(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $voterHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($voterHeaders)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'upvote',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Vote berhasil.');
    }

    public function test_upvote_toggle_off_jika_vote_sama(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [$voter, $vH] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($vH)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'upvote',
        ])->assertStatus(201);

        $this->withHeaders($vH)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'upvote',
        ])->assertStatus(200)
            ->assertJsonPath('message', 'Vote dibatalkan.');
    }

    public function test_vote_ditolak_pada_konten_sendiri(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($user->id);

        $this->withHeaders($headers)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'upvote',
        ])->assertStatus(422);
    }

    public function test_downvote_ditolak_reputation_kurang_15(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $voterHeaders] = $this->actingAsUser(['reputation_points' => 5]);
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($voterHeaders)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'downvote',
        ])->assertStatus(422);
    }

    public function test_downvote_berhasil_dengan_cukup_reputation(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $voterHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($voterHeaders)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'downvote',
        ])->assertStatus(201);
    }

    public function test_vote_ditolak_post_closed(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $voterHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $category = Category::create(['name' => 'ClosedCat', 'slug' => 'closed-cat']);
        $post = Post::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Closed post for vote test here',
            'body' => 'Body of closed post for vote testing.',
            'status' => 'closed',
        ]);

        $this->withHeaders($voterHeaders)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'upvote',
        ])->assertStatus(422);
    }

    public function test_vote_ditolak_tanpa_auth(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $this->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'upvote',
        ])->assertStatus(401);
    }

    public function test_vote_pada_comment_berhasil(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [$commenter] = $this->actingAsUser(['reputation_points' => 20]);
        [, $voterHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $commenter->id,
            'body' => 'Comment body content for vote test.',
        ]);

        $this->withHeaders($voterHeaders)->postJson('/api/votes', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'vote_type' => 'upvote',
        ])->assertStatus(201);
    }

    public function test_vote_ditolak_pada_reply(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [$replier] = $this->actingAsUser(['reputation_points' => 20]);
        [, $voterHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $parent = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'body' => 'Parent comment for reply vote test.',
        ]);

        $reply = Comment::create([
            'post_id' => $post->id,
            'user_id' => $replier->id,
            'parent_id' => $parent->id,
            'body' => 'Reply that should not be votable.',
        ]);

        $this->withHeaders($voterHeaders)->postJson('/api/votes', [
            'target_type' => 'comment',
            'target_id' => $reply->id,
            'vote_type' => 'upvote',
        ])->assertStatus(422);
    }

    public function test_ganti_vote_type(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [$voter, $vH] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($vH)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'upvote',
        ])->assertStatus(201);

        $this->withHeaders($vH)->postJson('/api/votes', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'vote_type' => 'downvote',
        ])->assertStatus(200)
            ->assertJsonPath('message', 'Vote berhasil diubah.');
    }
}

