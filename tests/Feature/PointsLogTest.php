<?php

namespace Tests\Feature;

use App\Models\PointsLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PointsLogTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    private function seedLog(string $userId, int $points, string $action = 'test_action'): PointsLog
    {
        return PointsLog::create([
            'user_id' => $userId,
            'points' => $points,
            'action_type' => $action,
            'description' => 'Test log entry',
            'reference_id' => null,
            'created_at' => now(),
        ]);
    }

    // ── GET /api/me/points ────────────────────────────────────────────

    public function test_index_mengembalikan_history_poin_sendiri(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $this->seedLog($user->id, 5, 'post_created');
        $this->seedLog($user->id, -2, 'downvote_given');

        $res = $this->withHeaders($headers)->getJson('/api/me/points');
        $res->assertStatus(200)
            ->assertJsonStructure(['summary', 'data'])
            ->assertJsonPath('summary.current_points', $user->reputation_points);
    }

    public function test_index_summary_earned_dan_deducted_benar(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $this->seedLog($user->id, 10, 'content_upvoted');
        $this->seedLog($user->id, 5, 'answer_accepted');
        $this->seedLog($user->id, -3, 'downvote_given');

        $res = $this->withHeaders($headers)->getJson('/api/me/points');
        $this->assertEquals(15, $res->json('summary.total_earned'));
        $this->assertEquals(-3, $res->json('summary.total_deducted'));
    }

    public function test_index_tidak_tampilkan_log_user_lain(): void
    {
        [$other] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $this->seedLog($other->id, 10, 'content_upvoted');

        $res = $this->withHeaders($headers)->getJson('/api/me/points');
        $this->assertCount(0, $res->json('data.data'));
    }

    public function test_index_ditolak_tanpa_auth(): void
    {
        $this->getJson('/api/me/points')->assertStatus(401);
    }

    // ── GET /api/users/{userId}/points ────────────────────────────────

    public function test_user_history_berhasil_oleh_admin(): void
    {
        [$user] = $this->actingAsUser();
        [, $adminHeaders] = $this->actingAsAdmin();
        $this->seedLog($user->id, 5, 'post_created');

        $this->withHeaders($adminHeaders)->getJson("/api/users/{$user->id}/points")
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_user_history_ditolak_user_biasa(): void
    {
        [$target] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->getJson("/api/users/{$target->id}/points")
            ->assertStatus(403);
    }

    public function test_user_history_ditolak_tanpa_auth(): void
    {
        $user = $this->makeUser();
        $this->getJson("/api/users/{$user->id}/points")->assertStatus(401);
    }

    // ── POST /api/users/{userId}/points/recalculate ───────────────────

    public function test_recalculate_berhasil_oleh_admin(): void
    {
        [$user] = $this->actingAsUser();
        [, $adminHeaders] = $this->actingAsAdmin();

        $this->withHeaders($adminHeaders)->postJson("/api/users/{$user->id}/points/recalculate")
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'reputation_points']);
    }

    public function test_recalculate_ditolak_user_biasa(): void
    {
        [$target] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->postJson("/api/users/{$target->id}/points/recalculate")
            ->assertStatus(403);
    }

    public function test_recalculate_ditolak_tanpa_auth(): void
    {
        $user = $this->makeUser();
        $this->postJson("/api/users/{$user->id}/points/recalculate")->assertStatus(401);
    }
}
