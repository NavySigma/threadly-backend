<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FollowTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    // ── GET /api/users/{user}/followers ───────────────────────────────

    public function test_followers_mengembalikan_daftar(): void
    {
        $user = $this->makeUser();

        $this->getJson("/api/users/{$user->id}/followers")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    // ── GET /api/users/{user}/following ───────────────────────────────

    public function test_following_mengembalikan_daftar(): void
    {
        $user = $this->makeUser();

        $this->getJson("/api/users/{$user->id}/following")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    // ── POST /api/users/{user}/follow ─────────────────────────────────

    public function test_follow_berhasil(): void
    {
        [$me, $headers] = $this->actingAsUser();
        $target = $this->makeUser();

        $this->withHeaders($headers)->postJson("/api/users/{$target->id}/follow")
            ->assertStatus(201)
            ->assertJsonPath('message', 'Berhasil mengikuti.');

        $this->assertTrue($me->following()->where('following_id', $target->id)->exists());
    }

    public function test_follow_gagal_follow_diri_sendiri(): void
    {
        [$me, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->postJson("/api/users/{$me->id}/follow")
            ->assertStatus(422);
    }

    public function test_follow_gagal_sudah_follow(): void
    {
        [$me, $headers] = $this->actingAsUser();
        $target = $this->makeUser();

        $this->withHeaders($headers)->postJson("/api/users/{$target->id}/follow")
            ->assertStatus(201);

        $this->withHeaders($headers)->postJson("/api/users/{$target->id}/follow")
            ->assertStatus(422);
    }

    public function test_follow_ditolak_tanpa_auth(): void
    {
        $target = $this->makeUser();

        $this->postJson("/api/users/{$target->id}/follow")->assertStatus(401);
    }

    // ── DELETE /api/users/{user}/follow ───────────────────────────────

    public function test_unfollow_berhasil(): void
    {
        [$me, $headers] = $this->actingAsUser();
        $target = $this->makeUser();

        $me->following()->attach($target->id, ['created_at' => now()]);

        $this->withHeaders($headers)->deleteJson("/api/users/{$target->id}/follow")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Berhasil unfollow.');

        $this->assertFalse($me->following()->where('following_id', $target->id)->exists());
    }

    public function test_unfollow_user_yang_tidak_difollow_tetap_200(): void
    {
        [$me, $headers] = $this->actingAsUser();
        $target = $this->makeUser();

        $this->withHeaders($headers)->deleteJson("/api/users/{$target->id}/follow")
            ->assertStatus(200);
    }

    public function test_unfollow_ditolak_tanpa_auth(): void
    {
        $target = $this->makeUser();

        $this->deleteJson("/api/users/{$target->id}/follow")->assertStatus(401);
    }

    // ── Count ─────────────────────────────────────────────────────────

    public function test_followers_count_bertambah_setelah_follow(): void
    {
        [$me, $headers] = $this->actingAsUser();
        $target = $this->makeUser();

        $this->withHeaders($headers)->postJson("/api/users/{$target->id}/follow");

        $res = $this->getJson("/api/users/{$target->id}/followers");
        $this->assertCount(1, $res->json('data'));
    }

    public function test_following_count_bertambah_setelah_follow(): void
    {
        [$me, $headers] = $this->actingAsUser();
        $target = $this->makeUser();

        $this->withHeaders($headers)->postJson("/api/users/{$target->id}/follow");

        $res = $this->getJson("/api/users/{$me->id}/following");
        $this->assertCount(1, $res->json('data'));
    }
}
