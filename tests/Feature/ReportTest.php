<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Feature\CreatesUser;
use Tests\TestCase;

class ReportTest extends TestCase
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
            'title' => 'Post title for report test here',
            'body' => 'Post body content for report testing purposes here.',
            'status' => 'open',
        ]);
    }

    // ── POST /api/reports ─────────────────────────────────────────────

    public function test_store_report_post_berhasil(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($headers)->postJson('/api/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'spam',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Laporan berhasil dikirim.');

        $this->assertDatabaseHas('reports', [
            'reporter_id' => $user->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'status' => 'pending',
        ]);
    }

    public function test_store_report_comment_berhasil(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $owner->id,
            'body' => 'Comment body content for report test.',
        ]);

        $this->withHeaders($headers)->postJson('/api/reports', [
            'target_type' => 'comment',
            'target_id' => $comment->id,
            'reason' => 'harassment',
            'description' => 'This comment is harassing me personally.',
        ])->assertStatus(201);
    }

    public function test_store_ditolak_report_konten_sendiri(): void
    {
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($user->id);

        $this->withHeaders($headers)->postJson('/api/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'spam',
        ])->assertStatus(422);
    }

    public function test_store_ditolak_jika_sudah_pernah_report(): void
    {
        [$owner] = $this->actingAsUser();
        [$user, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        // Report pertama
        $this->withHeaders($headers)->postJson('/api/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'spam',
        ])->assertStatus(201);

        // Report kedua → ditolak
        $this->withHeaders($headers)->postJson('/api/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'spam',
        ])->assertStatus(422);
    }

    public function test_store_gagal_reason_tidak_valid(): void
    {
        [$owner] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->withHeaders($headers)->postJson('/api/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'invalid_reason',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_store_ditolak_tanpa_auth(): void
    {
        [$owner] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $this->postJson('/api/reports', [
            'target_type' => 'post',
            'target_id' => $post->id,
            'reason' => 'spam',
        ])->assertStatus(401);
    }

    // ── GET /api/reports ──────────────────────────────────────────────

    public function test_index_berhasil_oleh_admin(): void
    {
        [, $adminHeaders] = $this->actingAsAdmin();

        $this->withHeaders($adminHeaders)->getJson('/api/reports')
            ->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_index_ditolak_user_biasa(): void
    {
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->getJson('/api/reports')
            ->assertStatus(403);
    }

    public function test_index_ditolak_tanpa_auth(): void
    {
        $this->getJson('/api/reports')->assertStatus(401);
    }

    // ── GET /api/reports/{report} ─────────────────────────────────────

    public function test_show_berhasil_oleh_admin(): void
    {
        [$owner] = $this->actingAsUser();
        [$user] = $this->actingAsUser();
        [, $adminHeaders] = $this->actingAsAdmin();
        $post = $this->makeOpenPost($owner->id);

        $report = Report::create([
            'reporter_id' => $user->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => 'spam',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->withHeaders($adminHeaders)->getJson("/api/reports/{$report->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['data', 'target']);
    }

    public function test_show_ditolak_user_biasa(): void
    {
        [$owner] = $this->actingAsUser();
        [$user] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $report = Report::create([
            'reporter_id' => $user->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => 'spam',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->withHeaders($headers)->getJson("/api/reports/{$report->id}")
            ->assertStatus(403);
    }

    // ── PATCH /api/reports/{report}/resolve ───────────────────────────

    public function test_resolve_berhasil_oleh_admin(): void
    {
        [$owner] = $this->actingAsUser();
        [$reporter] = $this->actingAsUser();
        [, $adminHeaders] = $this->actingAsAdmin();
        $post = $this->makeOpenPost($owner->id);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => 'spam',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->withHeaders($adminHeaders)->patchJson("/api/reports/{$report->id}/resolve", [
            'status' => 'resolved',
        ])->assertStatus(200);

        // Post harus jadi deleted
        $this->assertDatabaseHas('posts', [
            'id' => $post->id,
            'status' => 'deleted',
        ]);
    }

    public function test_resolve_dismiss_berhasil(): void
    {
        [$owner] = $this->actingAsUser();
        [$reporter] = $this->actingAsUser();
        [, $adminHeaders] = $this->actingAsAdmin();
        $post = $this->makeOpenPost($owner->id);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => 'spam',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->withHeaders($adminHeaders)->patchJson("/api/reports/{$report->id}/resolve", [
            'status' => 'dismissed',
        ])->assertStatus(200)
            ->assertJsonPath('message', 'Report ditolak.');
    }

    public function test_resolve_ditolak_jika_sudah_diproses(): void
    {
        [$owner] = $this->actingAsUser();
        [$reporter] = $this->actingAsUser();
        [, $adminHeaders] = $this->actingAsAdmin();
        $post = $this->makeOpenPost($owner->id);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => 'spam',
            'status' => 'resolved',
            'created_at' => now(),
        ]);

        $this->withHeaders($adminHeaders)->patchJson("/api/reports/{$report->id}/resolve", [
            'status' => 'dismissed',
        ])->assertStatus(422);
    }

    public function test_resolve_ditolak_user_biasa(): void
    {
        [$owner] = $this->actingAsUser();
        [$reporter] = $this->actingAsUser();
        [, $headers] = $this->actingAsUser();
        $post = $this->makeOpenPost($owner->id);

        $report = Report::create([
            'reporter_id' => $reporter->id,
            'target_id' => $post->id,
            'target_type' => 'post',
            'reason' => 'spam',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->withHeaders($headers)->patchJson("/api/reports/{$report->id}/resolve", [
            'status' => 'resolved',
        ])->assertStatus(403);
    }
}
