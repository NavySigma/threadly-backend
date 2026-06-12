<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    // Step 1 — User input email
    public function sendResetLink(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Generate token
        $token = Str::random(64);

        // Hapus token lama kalau ada
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // Simpan token baru
        DB::table('password_reset_tokens')->insert([
            'email'      => $request->email,
            'token'      => Hash::make($token),
            'created_at' => now(),
        ]);

        // Buat reset URL ke frontend
        $frontendUrl = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        $passedUrl = $request->input('frontend_url');
        if ($passedUrl && str_starts_with($passedUrl, 'http://localhost')) {
            $frontendUrl = rtrim($passedUrl, '/');
        }

        $resetUrl    = "{$frontendUrl}/reset-password?token={$token}&email={$request->email}";

        // Kirim email
        Mail::to($user->email)->send(new ResetPasswordMail($resetUrl, $user->username));

        return response()->json([
            'message' => 'Link reset password telah dikirim ke email kamu.',
        ]);
    }

    // Step 2 — User input password baru
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email'                 => 'required|email|exists:users,email',
            'token'                 => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
        ]);

        // Cek token
        $record = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (! $record) {
            return response()->json(['message' => 'Token tidak valid.'], 422);
        }

        // Cek token expired (60 menit)
        if (now()->diffInMinutes($record->created_at) > 60) {
            DB::table('password_reset_tokens')
                ->where('email', $request->email)
                ->delete();

            return response()->json(['message' => 'Token sudah kadaluarsa.'], 422);
        }

        // Verifikasi token
        if (! Hash::check($request->token, $record->token)) {
            return response()->json(['message' => 'Token tidak valid.'], 422);
        }

        // Update password
        $user = User::where('email', $request->email)->first();
        $user->update([
            'password_hash' => Hash::make($request->password),
        ]);

        // Hapus token
        DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->delete();

        // Revoke semua token Sanctum
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Password berhasil direset. Silakan login.',
        ]);
    }
}
