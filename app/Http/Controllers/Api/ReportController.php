<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Report;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\PointService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(
        private PointService $pointService,
        private NotificationService $notificationService,
    ) {}

    // User bikin report — hanya post & comment
    public function store(Request $request): JsonResponse
    {
        if ($request->user()->is_banned) {
            return response()->json(['message' => 'Akun kamu telah diblokir.'], 403);
        }

        $validated = $request->validate([
            'target_type' => 'required|in:post,comment',
            'target_id'   => 'required|uuid',
            'reason'      => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        // Validasi target exists
        $target = match($validated['target_type']) {
            'post'    => Post::findOrFail($validated['target_id']),
            'comment' => Comment::findOrFail($validated['target_id']),
        };

        // Tidak bisa report konten sendiri
        if ($target->user_id === $request->user()->id) {
            return response()->json(['message' => 'Tidak bisa melaporkan konten sendiri.'], 422);
        }

        // Cek sudah pernah report target yang sama
        $alreadyReported = Report::where('reporter_id', $request->user()->id)
            ->where('target_id', $validated['target_id'])
            ->where('target_type', $validated['target_type'])
            ->whereIn('status', ['pending', 'reviewed'])
            ->exists();

        if ($alreadyReported) {
            return response()->json(['message' => 'Kamu sudah melaporkan konten ini.'], 422);
        }

        Report::create([
            'reporter_id' => $request->user()->id,
            'target_id'   => $validated['target_id'],
            'target_type' => $validated['target_type'],
            'reason'      => $validated['reason'],
            'description' => $validated['description'] ?? null,
            'status'      => 'pending',
            'created_at'  => now(),
        ]);

        return response()->json(['message' => 'Laporan berhasil dikirim.'], 201);
    }

    // Mod & Admin — list semua report
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $reports = Report::with([
                'reporter:id,username,avatar_url',
                'resolver:id,username,avatar_url',
            ])
            ->when($request->status, fn($q) =>
                $q->where('status', $request->status)
            )
            ->when($request->target_type, fn($q) =>
                $q->where('target_type', $request->target_type)
            )
            ->latest('created_at')
            ->paginate(20);

        return response()->json($reports);
    }

    // Mod & Admin — lihat detail report
    public function show(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $report->load([
            'reporter:id,username,avatar_url',
            'resolver:id,username,avatar_url',
        ]);

        $target = match($report->target_type) {
            'post'    => Post::with('user:id,username,avatar_url')->find($report->target_id),
            'comment' => Comment::with('user:id,username,avatar_url')->find($report->target_id),
        };

        return response()->json([
            'data'   => $report,
            'target' => $target,
        ]);
    }

    // Mod & Admin — resolve report
    public function resolve(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (in_array($report->status, ['resolved', 'dismissed'])) {
            return response()->json(['message' => 'Report sudah diproses.'], 422);
        }

        $validated = $request->validate([
            'status' => 'required|in:resolved,dismissed',
        ]);

        // Jika disetujui → hapus konten + sanksi -10 poin
        if ($validated['status'] === 'resolved') {
            $target = match($report->target_type) {
                'post'    => Post::find($report->target_id),
                'comment' => Comment::find($report->target_id),
            };

            if ($target) {
                $owner = User::findOrFail($target->user_id);

                // Hapus konten
                if ($report->target_type === 'post') {
                    $target->update(['status' => 'deleted']);
                } else {
                    // Simpan body asli ke description report biar ke-track
                    if (empty($report->description)) {
                        $report->update(['description' => $target->body]);
                    }
                    $target->update(['body' => '[komentar telah dihapus oleh admin]']);
                }

                // Sanksi -10 poin ke pembuat konten
                $this->pointService->adjust(
                    $owner,
                    -10,
                    'content_reported',
                    $report->target_id,
                    'Konten kamu dihapus karena melanggar aturan'
                );

                $reporter = User::findOrFail($report->reporter_id);
                    $this->notificationService->send(
                        recipient    : $reporter,
                        actor        : null,
                        type         : 'report_confirmed',
                        referenceId  : $report->id,
                        referenceType: 'report',
                    );

                    // Notif ke owner konten — kena sanksi -10 poin
                    $this->notificationService->send(
                        recipient    : $owner,
                        actor        : null,
                        type         : 'report_penalized',
                        referenceId  : $report->id,
                        referenceType: 'report',
                    );
            }
        }

        $report->update([
            'status'      => $validated['status'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        $message = $validated['status'] === 'resolved'
            ? 'Report disetujui, konten dihapus dan poin pembuat dikurangi.'
            : 'Report ditolak.';

        return response()->json(['message' => $message, 'data' => $report]);
    }

    // Mod & Admin — hard delete laporan
    public function destroy(Request $request, Report $report): JsonResponse
    {
        if (!$request->user()->isModeratorOrAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $report->delete();

        return response()->json(['message' => 'Laporan berhasil dihapus.']);
    }
}
