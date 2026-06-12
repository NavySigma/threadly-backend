<?php

namespace Tests\Feature;

use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TagTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    private function makeTag(array $overrides = []): Tag
    {
        $name = $overrides['name'] ?? 'tag-'.Str::random(4);

        return Tag::create(array_merge([
            'name' => $name,
            'slug' => Str::slug($name),
            'usage_count' => 0,
        ], $overrides));
    }

    // ── GET /api/tags ─────────────────────────────────────────────────

    public function test_index_mengembalikan_daftar_tag(): void
    {
        $this->makeTag(['name' => 'php', 'slug' => 'php']);

        $this->getJson('/api/tags')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_index_tidak_perlu_auth(): void
    {
        $this->getJson('/api/tags')->assertStatus(200);
    }

    public function test_index_bisa_search(): void
    {
        $this->makeTag(['name' => 'laravel', 'slug' => 'laravel']);
        $this->makeTag(['name' => 'django', 'slug' => 'django']);

        $res = $this->getJson('/api/tags?search=laravel');
        $res->assertStatus(200);
        $this->assertStringContainsString('laravel', json_encode($res->json('data')));
    }

    // ── GET /api/tags/{tag} ───────────────────────────────────────────

    public function test_show_mengembalikan_detail_tag(): void
    {
        $tag = $this->makeTag(['name' => 'vue', 'slug' => 'vue']);

        $this->getJson("/api/tags/{$tag->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.slug', 'vue');
    }

    public function test_show_tag_tidak_ada_404(): void
    {
        $this->getJson('/api/tags/'.Str::uuid())->assertStatus(404);
    }

    // ── POST /api/tags ────────────────────────────────────────────────

    public function test_store_berhasil_oleh_admin(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();

        $this->withHeaders($adminHeaders)->postJson('/api/tags', [
            'name' => 'typescript',
            'color' => '#3178c6',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Tag berhasil dibuat.');

        $this->assertDatabaseHas('tags', ['slug' => 'typescript']);
    }

    public function test_store_berhasil_user_biasa_karena_tidak_ada_guard(): void
    {
        // TagController store tidak cek isAdmin — hanya butuh auth
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->postJson('/api/tags', [
            'name' => 'react',
        ])->assertStatus(201);
    }

    public function test_store_ditolak_tanpa_auth(): void
    {
        $this->postJson('/api/tags', ['name' => 'rust'])->assertStatus(401);
    }

    public function test_store_gagal_tag_sudah_ada(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();
        $this->makeTag(['name' => 'python', 'slug' => 'python']);

        $this->withHeaders($adminHeaders)->postJson('/api/tags', [
            'name' => 'python',
        ])->assertStatus(422);
    }

    public function test_store_gagal_warna_format_salah(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();

        $this->withHeaders($adminHeaders)->postJson('/api/tags', [
            'name' => 'kotlin',
            'color' => 'not-a-color',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    }

    // ── PUT /api/tags/{tag} ───────────────────────────────────────────

    public function test_update_berhasil_oleh_admin(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();
        $tag = $this->makeTag(['name' => 'old-tag', 'slug' => 'old-tag']);

        $this->withHeaders($adminHeaders)->putJson("/api/tags/{$tag->id}", [
            'name' => 'new-tag-name',
            'color' => '#ff5733',
        ])->assertStatus(200)
            ->assertJsonPath('message', 'Tag berhasil diupdate.');
    }

    public function test_update_ditolak_user_biasa(): void
    {
        [, $headers] = $this->actingAsUser();
        $tag = $this->makeTag();

        $this->withHeaders($headers)->putJson("/api/tags/{$tag->id}", [
            'name' => 'hacked',
        ])->assertStatus(403);
    }

    // ── DELETE /api/tags/{tag} ────────────────────────────────────────

    public function test_destroy_berhasil_oleh_admin(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();
        $tag = $this->makeTag(['name' => 'delete-me', 'slug' => 'delete-me']);

        $this->withHeaders($adminHeaders)->deleteJson("/api/tags/{$tag->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Tag berhasil dihapus.');

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    public function test_destroy_ditolak_user_biasa(): void
    {
        [, $headers] = $this->actingAsUser();
        $tag = $this->makeTag();

        $this->withHeaders($headers)->deleteJson("/api/tags/{$tag->id}")
            ->assertStatus(403);
    }

    public function test_destroy_ditolak_tanpa_auth(): void
    {
        $tag = $this->makeTag();

        $this->deleteJson("/api/tags/{$tag->id}")->assertStatus(401);
    }
}
