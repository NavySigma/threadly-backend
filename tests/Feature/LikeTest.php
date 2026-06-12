<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\CreatesUser;
use Tests\TestCase;

class LikeTest extends TestCase
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
            'title' => 'Post title for like test here',
            'body' => 'Post body content for like testing purposes here.',
            'status' => 'open',
        ]);
    }

    // ── POST /api/likes ───────────────────────────────────────────────

    public function test_like_post_berhasil(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($headers)->postJson('/api/likes', [
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Berhasil di-bookmark.');

        $this->assertDatabaseHas('likes', [
            'user_id' => $user->id,
            'target_id' => $post->id,
            'target_type' => 'post',
        ]);
    }

    public function test_like_comment_berhasil(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'body' => 'Comment body for like test.',
        ]);

        $this->withHeaders($headers)->postJson('/api/likes', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
        ])->assertStatus(201);
    }

    public function test_like_ditolak_jika_sudah_like(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        // Like pertama
        $this->withHeaders($headers)->postJson('/api/likes', [
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->assertStatus(201);

        // Like kedua → ditolak
        $this->withHeaders($headers)->postJson('/api/likes', [
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->assertStatus(422);
    }

    public function test_like_ditolak_post_closed(): void
    {
        [$owner] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $category = Category::create(['name' => 'ClosedCat', 'slug' => 'closed-cat-like']);
        $post = Post::create([
            'user_id' => $owner->id,
            'category_id' => $category->id,
            'title' => 'Closed post for like test here',
            'body' => 'Body of the closed post for like testing.',
            'status' => 'closed',
        ]);

        $this->withHeaders($headers)->postJson('/api/likes', [
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->assertStatus(422);
    }

    public function test_like_ditolak_tanpa_auth(): void
    {
        [$owner] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->postJson('/api/likes', [
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->assertStatus(401);
    }

    // ── DELETE /api/likes ─────────────────────────────────────────────

    public function test_unlike_berhasil(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        Like::create([
            'user_id' => $user->id,
            'target_id' => $post->id,
            'target_type' => 'post',
        ]);

        $this->withHeaders($headers)->deleteJson('/api/likes', [
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->assertStatus(200)
            ->assertJsonPath('message', 'Bookmark dihapus.');

        $this->assertDatabaseMissing('likes', [
            'user_id' => $user->id,
            'target_id' => $post->id,
            'target_type' => 'post',
        ]);
    }

    public function test_unlike_yang_belum_dilike_tetap_200(): void
    {
        [$owner] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($headers)->deleteJson('/api/likes', [
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->assertStatus(200);
    }

    public function test_unlike_ditolak_tanpa_auth(): void
    {
        [$owner] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->deleteJson('/api/likes', [
            'target_type' => 'post',
            'target_id' => $post->id,
        ])->assertStatus(401);
    }

    // ── GET /api/me/bookmarks/posts ───────────────────────────────────

    public function test_liked_posts_mengembalikan_daftar(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        Like::create([
            'user_id' => $user->id,
            'target_id' => $post->id,
            'target_type' => 'post',
        ]);

        $res = $this->withHeaders($headers)->getJson('/api/me/bookmarks/posts');
        $res->assertStatus(200)
            ->assertJsonStructure(['data']);

        $this->assertCount(1, $res->json('data'));
    }

    public function test_liked_posts_ditolak_tanpa_auth(): void
    {
        $this->getJson('/api/me/bookmarks/posts')->assertStatus(401);
    }

    // ── GET /api/me/bookmarks/comments ───────────────────────────────

    public function test_liked_comments_mengembalikan_daftar(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'body' => 'Comment for liked comments test.',
        ]);

        Like::create([
            'user_id' => $user->id,
            'target_id' => $comment->id,
            'target_type' => 'comment',
        ]);

        $res = $this->withHeaders($headers)->getJson('/api/me/bookmarks/comments');
        $res->assertStatus(200)
            ->assertJsonStructure(['data']);

        $this->assertCount(1, $res->json('data'));
    }

    public function test_liked_comments_ditolak_tanpa_auth(): void
    {
        $this->getJson('/api/me/bookmarks/comments')->assertStatus(401);
    }
}
