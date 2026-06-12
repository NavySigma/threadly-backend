<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    // ── GET /api/me ──────────────────────────────────────────────────

    public function test_me_mengembalikan_data_user_terautentikasi(): void
    {
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->getJson('/api/me')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['id', 'username', 'email']]);
    }

    public function test_me_ditolak_tanpa_auth(): void
    {
        $this->getJson('/api/me')->assertStatus(401);
    }

    // ── GET /api/users/{user} ────────────────────────────────────────

    public function test_GET_profile_publik(): void
    {
        $user = $this->makeUser();

        $this->getJson("/api/users/{$user->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.username', $user->username);
    }

    public function test_GET_user_tidak_ada_404(): void
    {
        $this->getJson('/api/users/00000000-0000-0000-0000-000000000000')
            ->assertStatus(404);
    }

    // ── PUT /api/me ───────────────────────────────────────────────────

    public function test_PUT_profile_berhasil(): void
    {
        [$user, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->putJson('/api/me', [
            'bio' => 'Bio baru yang keren.',
        ])->assertStatus(200)
            ->assertJsonPath('message', 'Profile berhasil diupdate.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'bio' => 'Bio baru yang keren.',
        ]);
    }

    public function test_PUT_profile_gagal_username_duplikat(): void
    {
        $this->makeUser(['username' => 'takenuser']);
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->putJson('/api/me', [
            'username' => 'takenuser',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_PUT_profile_ditolak_tanpa_auth(): void
    {
        $this->putJson('/api/me', ['bio' => 'Anonymous update.'])->assertStatus(401);
    }

    // ── PUT /api/me/password ─────────────────────────────────────────

    public function test_ganti_password_berhasil(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $user->update(['password_hash' => Hash::make('OldPass1a')]);

        $this->withHeaders($headers)->putJson('/api/me/password', [
            'current_password' => 'OldPass1a',
            'new_password' => 'NewPass1a',
            'new_password_confirmation' => 'NewPass1a',
        ])->assertStatus(200);
    }

    public function test_ganti_password_gagal_current_salah(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $user->update(['password_hash' => Hash::make('CorrectOld1')]);

        $this->withHeaders($headers)->putJson('/api/me/password', [
            'current_password' => 'WrongPassword1',
            'new_password' => 'NewPass1a',
            'new_password_confirmation' => 'NewPass1a',
        ])->assertStatus(422);
    }

    public function test_ganti_password_gagal_konfirmasi_tidak_cocok(): void
    {
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->putJson('/api/me/password', [
            'current_password' => 'anything',
            'new_password' => 'NewPass1a',
            'new_password_confirmation' => 'DifferentPass1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['new_password']);
    }

    public function test_ganti_password_gagal_tanpa_auth(): void
    {
        $this->putJson('/api/me/password', [
            'current_password' => 'OldPass1a',
            'new_password' => 'NewPass1a',
            'new_password_confirmation' => 'NewPass1a',
        ])->assertStatus(401);
    }

    // ── GET /api/users/{user}/posts ───────────────────────────────────

    public function test_posts_user_publik(): void
    {
        $user = $this->makeUser();

        $this->getJson("/api/users/{$user->id}/posts")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    // ── Banned check ─────────────────────────────────────────────────

    public function test_banned_user_ditolak_di_protected_endpoint(): void
    {
        [, $headers] = $this->actingAsUser(['is_banned' => true]);

        $this->withHeaders($headers)->getJson('/api/me')->assertStatus(403);
    }
}

