<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    private function makeCategory(array $overrides = []): Category
    {
        $name = $overrides['name'] ?? 'Category '.Str::random(4);

        return Category::create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name),
        ], $overrides));
    }

    // ── GET /api/categories ──────────────────────────────────────────

    public function test_index_mengembalikan_daftar_kategori(): void
    {
        $this->makeCategory(['name' => 'PHP', 'slug' => 'php']);

        $this->getJson('/api/categories')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_index_tidak_perlu_auth(): void
    {
        $this->getJson('/api/categories')->assertStatus(200);
    }

    // ── GET /api/categories/{category} ───────────────────────────────

    public function test_show_mengembalikan_detail_kategori(): void
    {
        $cat = $this->makeCategory(['name' => 'Laravel', 'slug' => 'laravel']);

        $this->getJson("/api/categories/{$cat->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.slug', 'laravel');
    }

    public function test_show_kategori_tidak_ada_404(): void
    {
        $this->getJson('/api/categories/'.Str::uuid())->assertStatus(404);
    }

    // ── POST /api/categories ─────────────────────────────────────────

    public function test_store_berhasil_oleh_admin(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();

        $this->withHeaders($adminHeaders)->postJson('/api/categories', [
            'name' => 'Vue JS',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Category berhasil dibuat.');

        $this->assertDatabaseHas('categories', ['slug' => 'vue-js']);
    }

    public function test_store_ditolak_oleh_user_biasa(): void
    {
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->postJson('/api/categories', [
            'name' => 'React JS',
        ])->assertStatus(403);
    }

    public function test_store_ditolak_tanpa_auth(): void
    {
        $this->postJson('/api/categories', ['name' => 'Node JS'])->assertStatus(401);
    }

    public function test_store_gagal_nama_duplikat(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();
        $this->makeCategory(['name' => 'Python', 'slug' => 'python']);

        $this->withHeaders($adminHeaders)->postJson('/api/categories', [
            'name' => 'Python',
        ])->assertStatus(422);
    }

    // ── PUT /api/categories/{category} ───────────────────────────────

    public function test_update_berhasil_oleh_admin(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();
        $cat = $this->makeCategory(['name' => 'Go Lang', 'slug' => 'go-lang']);

        $this->withHeaders($adminHeaders)->putJson("/api/categories/{$cat->id}", [
            'name' => 'Golang Updated',
        ])->assertStatus(200)
            ->assertJsonPath('message', 'Category berhasil diupdate.');
    }

    public function test_update_ditolak_bukan_admin(): void
    {
        [, $headers] = $this->actingAsUser();
        $cat = $this->makeCategory();

        $this->withHeaders($headers)->putJson("/api/categories/{$cat->id}", [
            'name' => 'Hacked',
        ])->assertStatus(403);
    }

    public function test_update_ditolak_set_parent_ke_diri_sendiri(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();
        $cat = $this->makeCategory();

        $this->withHeaders($adminHeaders)->putJson("/api/categories/{$cat->id}", [
            'parent_id' => $cat->id,
        ])->assertStatus(422);
    }

    // ── DELETE /api/categories/{category} ────────────────────────────

    public function test_destroy_berhasil_oleh_admin(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();
        $cat = $this->makeCategory(['name' => 'To Delete', 'slug' => 'to-delete']);

        $this->withHeaders($adminHeaders)->deleteJson("/api/categories/{$cat->id}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $cat->id]);
    }

    public function test_destroy_ditolak_jika_masih_ada_post(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();
        [$user] = $this->actingAsUser(['reputation_points' => 20]);
        $cat = $this->makeCategory(['name' => 'Has Posts', 'slug' => 'has-posts']);

        Post::create([
            'user_id' => $user->id,
            'category_id' => $cat->id,
            'title' => 'Post yang ada di kategori ini',
            'body' => 'Body post yang ada di kategori ini.',
            'status' => 'open',
        ]);

        $this->withHeaders($adminHeaders)->deleteJson("/api/categories/{$cat->id}")
            ->assertStatus(422);
    }

    public function test_destroy_ditolak_bukan_admin(): void
    {
        [, $headers] = $this->actingAsUser();
        $cat = $this->makeCategory();

        $this->withHeaders($headers)->deleteJson("/api/categories/{$cat->id}")
            ->assertStatus(403);
    }
}
