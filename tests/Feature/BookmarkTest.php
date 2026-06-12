<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\CreatesUser;
use Tests\TestCase;

class BookmarkTest extends TestCase
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
            'title' => 'Post title for bookmark test here',
            'body' => 'Post body content for bookmark testing purposes.',
            'status' => 'open',
        ]);
    }

    // ── GET /api/me/bookmarks ─────────────────────────────────────────

    public function test_index_mengembalikan_daftar_bookmark(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        Bookmark::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
        ]);

        $this->withHeaders($headers)->getJson('/api/me/bookmarks')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_index_ditolak_tanpa_auth(): void
    {
        $this->getJson('/api/me/bookmarks')->assertStatus(401);
    }

    // ── POST /api/bookmarks ───────────────────────────────────────────

    public function test_store_bookmark_berhasil(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($headers)->postJson('/api/bookmarks', [
            'post_id' => $post->id,
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Post berhasil di-bookmark.');

        $this->assertDatabaseHas('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_store_ditolak_jika_sudah_bookmark(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        // Bookmark pertama
        $this->withHeaders($headers)->postJson('/api/bookmarks', [
            'post_id' => $post->id,
        ])->assertStatus(201);

        // Bookmark kedua → ditolak
        $this->withHeaders($headers)->postJson('/api/bookmarks', [
            'post_id' => $post->id,
        ])->assertStatus(422);
    }

    public function test_store_ditolak_post_deleted(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $category = Category::create(['name' => 'DeletedCat', 'slug' => 'deleted-cat']);
        $post = Post::create([
            'user_id' => $user->id,
            'category_id' => $category->id,
            'title' => 'Deleted post for bookmark test',
            'body' => 'Body of the deleted post for bookmark testing.',
            'status' => 'deleted',
        ]);

        $this->withHeaders($headers)->postJson('/api/bookmarks', [
            'post_id' => $post->id,
        ])->assertStatus(422);
    }

    public function test_store_ditolak_tanpa_auth(): void
    {
        [$owner] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->postJson('/api/bookmarks', [
            'post_id' => $post->id,
        ])->assertStatus(401);
    }

    // ── DELETE /api/bookmarks/{post} ──────────────────────────────────

    public function test_destroy_bookmark_berhasil(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        Bookmark::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
        ]);

        $this->withHeaders($headers)->deleteJson("/api/bookmarks/{$post->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Bookmark berhasil dihapus.');

        $this->assertDatabaseMissing('bookmarks', [
            'user_id' => $user->id,
            'post_id' => $post->id,
        ]);
    }

    public function test_destroy_bookmark_tidak_ditemukan_404(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        $this->withHeaders($headers)->deleteJson("/api/bookmarks/{$post->id}")
            ->assertStatus(404);
    }

    public function test_destroy_ditolak_tanpa_auth(): void
    {
        [$owner] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->deleteJson("/api/bookmarks/{$post->id}")->assertStatus(401);
    }

    // ── GET /api/bookmarks/{post}/check ───────────────────────────────

    public function test_check_mengembalikan_false_jika_belum_bookmark(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        $this->withHeaders($headers)->getJson("/api/bookmarks/{$post->id}/check")
            ->assertStatus(200)
            ->assertJsonPath('is_bookmarked', false);
    }

    public function test_check_mengembalikan_true_jika_sudah_bookmark(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        Bookmark::create([
            'user_id' => $user->id,
            'post_id' => $post->id,
            'created_at' => now(),
        ]);

        $this->withHeaders($headers)->getJson("/api/bookmarks/{$post->id}/check")
            ->assertStatus(200)
            ->assertJsonPath('is_bookmarked', true);
    }
}
