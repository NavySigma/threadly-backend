<?php

namespace Tests\Feature;

use App\Models\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\CreatesUser;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    private function makeNotification(string $userId, bool $isRead = false): Notification
    {
        return Notification::create([
            'user_id' => $userId,
            'actor_id' => null,
            'type' => 'comment',
            'reference_id' => Str::uuid(),
            'reference_type' => 'comment',
            'is_read' => $isRead,
            'created_at' => now(),
        ]);
    }

    // ── GET /api/notifications ────────────────────────────────────────

    public function test_index_mengembalikan_notifikasi_milik_user(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $this->makeNotification($user->id);

        $res = $this->withHeaders($headers)->getJson('/api/notifications');
        $res->assertStatus(200)
            ->assertJsonStructure(['unread_count', 'data']);
    }

    public function test_index_unread_count_benar(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $this->makeNotification($user->id, false); // unread
        $this->makeNotification($user->id, true);  // read

        $res = $this->withHeaders($headers)->getJson('/api/notifications');
        $this->assertEquals(1, $res->json('unread_count'));
    }

    public function test_index_tidak_tampilkan_notifikasi_user_lain(): void
    {
        [$other] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $this->makeNotification($other->id);

        $res = $this->withHeaders($headers)->getJson('/api/notifications');
        $this->assertCount(0, $res->json('data.data'));
    }

    public function test_index_ditolak_tanpa_auth(): void
    {
        $this->getJson('/api/notifications')->assertStatus(401);
    }

    // ── PATCH /api/notifications/{notification}/read ──────────────────

    public function test_read_berhasil(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $notif = $this->makeNotification($user->id, false);

        $this->withHeaders($headers)->patchJson("/api/notifications/{$notif->id}/read")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Notifikasi ditandai sudah dibaca.');

        $this->assertDatabaseHas('notifications', [
            'id' => $notif->id,
            'is_read' => true,
        ]);
    }

    public function test_read_ditolak_jika_bukan_pemilik(): void
    {
        [$other] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $notif = $this->makeNotification($other->id, false);

        $this->withHeaders($headers)->patchJson("/api/notifications/{$notif->id}/read")
            ->assertStatus(403);
    }

    public function test_read_ditolak_tanpa_auth(): void
    {
        [$user] = $this->actingAsUser();
        $notif = $this->makeNotification($user->id);

        $this->patchJson("/api/notifications/{$notif->id}/read")->assertStatus(401);
    }

    // ── PATCH /api/notifications/read-all ────────────────────────────

    public function test_read_all_berhasil(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $this->makeNotification($user->id, false);
        $this->makeNotification($user->id, false);

        $this->withHeaders($headers)->patchJson('/api/notifications/read-all')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Semua notifikasi ditandai sudah dibaca.');

        $unread = Notification::where('user_id', $user->id)
            ->where('is_read', false)
            ->count();

        $this->assertEquals(0, $unread);
    }

    public function test_read_all_ditolak_tanpa_auth(): void
    {
        $this->patchJson('/api/notifications/read-all')->assertStatus(401);
    }

    // ── DELETE /api/notifications/{notification} ──────────────────────

    public function test_destroy_berhasil(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $notif = $this->makeNotification($user->id);

        $this->withHeaders($headers)->deleteJson("/api/notifications/{$notif->id}")
            ->assertStatus(200)
            ->assertJsonPath('message', 'Notifikasi dihapus.');

        $this->assertDatabaseMissing('notifications', ['id' => $notif->id]);
    }

    public function test_destroy_ditolak_jika_bukan_pemilik(): void
    {
        [$other] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $notif = $this->makeNotification($other->id);

        $this->withHeaders($headers)->deleteJson("/api/notifications/{$notif->id}")
            ->assertStatus(403);
    }

    public function test_destroy_ditolak_tanpa_auth(): void
    {
        [$user] = $this->actingAsUser();
        $notif = $this->makeNotification($user->id);

        $this->deleteJson("/api/notifications/{$notif->id}")->assertStatus(401);
    }

    // ── DELETE /api/notifications/read ───────────────────────────────

    public function test_destroy_read_menghapus_semua_yang_sudah_dibaca(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $read = $this->makeNotification($user->id, true);
        $unread = $this->makeNotification($user->id, false);

        $this->withHeaders($headers)->deleteJson('/api/notifications/read')
            ->assertStatus(200)
            ->assertJsonPath('message', 'Semua notifikasi yang sudah dibaca dihapus.');

        $this->assertDatabaseMissing('notifications', ['id' => $read->id]);
        $this->assertDatabaseHas('notifications', ['id' => $unread->id]);
    }

    public function test_destroy_read_ditolak_tanpa_auth(): void
    {
        $this->deleteJson('/api/notifications/read')->assertStatus(401);
    }
}
