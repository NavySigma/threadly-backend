<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PostTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    private function makeCategory(): Category
    {
        return Category::create([
            'name' => 'Cat '.Str::random(4),
            'slug' => 'cat-'.Str::random(4),
        ]);
    }

    private function makeOpenPost(string $userId, array $overrides = []): Post
    {
        return Post::create(array_merge([
            'user_id' => $userId,
            'category_id' => $this->makeCategory()->id,
            'title' => 'This is a test post title here',
            'body' => 'This is the body of the test post, it is long enough.',
            'status' => 'open',
        ], $overrides));
    }

    // ── GET /api/posts ────────────────────────────────────────────────

    public function test_ge_t_list_mengembalikan_daftar_post(): void
    {
        [$user] = $this->actingAsUser();
        $this->makeOpenPost($user->id);

        $this->getJson('/api/posts')
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'links']);
    }

    public function test_ge_t_list_tidak_tampilkan_post_deleted(): void
    {
        [$user] = $this->actingAsUser();
        $this->makeOpenPost($user->id, ['status' => 'deleted']);

        $res = $this->getJson('/api/posts');
        $this->assertCount(0, $res->json('data'));
    }

    // ── GET /api/posts/{post} ─────────────────────────────────────────

    public function test_ge_t_mengembalikan_detail_post(): void
    {
        [$user] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        $this->getJson("/api/posts/{$post->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.id', $post->id);
    }

    public function test_ge_t_post_tidak_ada_404(): void
    {
        $this->getJson('/api/posts/'.Str::uuid())->assertStatus(404);
    }

    // ── POST /api/posts ───────────────────────────────────────────────

    public function test_pos_t_berhasil(): void
    {
        [, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $category = $this->makeCategory();

        $this->withHeaders($headers)->postJson('/api/posts', [
            'category_id' => $category->id,
            'title' => 'This is my new question title here',
            'body' => 'This is the body of my question and it is detailed enough.',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Post berhasil dibuat.');
    }

    public function test_pos_t_gagal_reputation_kurang(): void
    {
        [, $headers] = $this->actingAsUser(['reputation_points' => 5]);
        $category = $this->makeCategory();

        $this->withHeaders($headers)->postJson('/api/posts', [
            'category_id' => $category->id,
            'title' => 'My question title here',
            'body' => 'Body of the question is here and long enough.',
        ])->assertStatus(422);
    }

    public function test_pos_t_gagal_tanpa_auth(): void
    {
        $category = $this->makeCategory();

        $this->postJson('/api/posts', [
            'category_id' => $category->id,
            'title' => 'Some question title here',
            'body' => 'Body of the question here.',
        ])->assertStatus(401);
    }

    public function test_pos_t_gagal_title_terlalu_pendek(): void
    {
        [, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $category = $this->makeCategory();

        $this->withHeaders($headers)->postJson('/api/posts', [
            'category_id' => $category->id,
            'title' => 'Short',
            'body' => 'Body of the question here and it is long enough to pass.',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    // ── PUT /api/posts/{post} ─────────────────────────────────────────

    public function test_pu_t_berhasil_oleh_owner(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($user->id);

        $this->withHeaders($headers)->putJson("/api/posts/{$post->id}", [
            'title' => 'Updated title of this question here',
        ])->assertStatus(200);
    }

    public function test_pu_t_ditolak_bukan_owner(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $otherHeaders] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($otherHeaders)->putJson("/api/posts/{$post->id}", [
            'title' => 'Hacked title here',
        ])->assertStatus(403);
    }

    public function test_pu_t_ditolak_post_closed(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($user->id, ['status' => 'closed']);

        $this->withHeaders($headers)->putJson("/api/posts/{$post->id}", [
            'title' => 'Try to edit closed post',
        ])->assertStatus(422);
    }

    // ── DELETE /api/posts/{post} ──────────────────────────────────────

    public function test_delet_e_berhasil_oleh_owner(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($user->id);

        $this->withHeaders($headers)->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(200);

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'deleted']);
    }

    public function test_delet_e_ditolak_bukan_owner_bukan_admin(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $otherHeaders] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($otherHeaders)->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(403);
    }

    public function test_delet_e_berhasil_oleh_admin(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $adminHeaders] = $this->actingAsAdmin();
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($adminHeaders)->deleteJson("/api/posts/{$post->id}")
            ->assertStatus(200);
    }

    // ── PATCH /api/posts/{post}/close ─────────────────────────────────

    public function test_patc_h_close_berhasil_oleh_owner(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($user->id);

        $this->withHeaders($headers)->patchJson("/api/posts/{$post->id}/close")
            ->assertStatus(200);

        $this->assertDatabaseHas('posts', ['id' => $post->id, 'status' => 'closed']);
    }

    public function test_patc_h_close_ditolak_bukan_owner(): void
    {
        [$owner] = $this->actingAsUser(['reputation_points' => 20]);
        [, $otherHeaders] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($otherHeaders)->patchJson("/api/posts/{$post->id}/close")
            ->assertStatus(403);
    }

    // ── PATCH /api/posts/{post}/reopen ────────────────────────────────

    public function test_patc_h_reopen_berhasil_dalam_24jam(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($user->id, [
            'status' => 'closed',
            'closed_at' => now()->subHours(2),
        ]);

        $this->withHeaders($headers)->patchJson("/api/posts/{$post->id}/reopen")
            ->assertStatus(200);
    }

    public function test_patc_h_reopen_ditolak_lewat_24jam(): void
    {
        [$user, $headers] = $this->actingAsUser(['reputation_points' => 20]);
        $post = $this->makeOpenPost($user->id, [
            'status' => 'closed',
            'closed_at' => now()->subHours(25),
        ]);

        $this->withHeaders($headers)->patchJson("/api/posts/{$post->id}/reopen")
            ->assertStatus(422);
    }
}
