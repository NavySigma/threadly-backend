<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    // Redirect ke Google/GitHub
    public function redirect(string $provider, \Illuminate\Http\Request $request)
    {
        $this->validateProvider($provider);

        // Ambil URL frontend dari query parameter, jika ada
        $state = base64_encode(json_encode([
            'frontend_url' => $request->query('frontend_url')
        ]));

        return Socialite::driver($provider)
            ->stateless()
            ->with(['state' => $state])
            ->redirect();
    }

    // Callback dari Google/GitHub
    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->stateless()->user();
        } catch (\Exception $e) {
            return response()->json(['message' => 'Gagal autentikasi dengan ' . $provider . '.'], 422);
        }

        $providerIdField = $provider . '_id';

        DB::beginTransaction();

        try {
            $user = User::where($providerIdField, $socialUser->getId())->first();

            if (! $user) {
                $user = User::where('email', $socialUser->getEmail())->first();

                if ($user) {
                    // Auto link akun yang ada
                    $user->update([
                        $providerIdField => $socialUser->getId(),
                        'avatar_url'     => $user->avatar_url ?? $socialUser->getAvatar(),
                    ]);
                } else {
                    // Buat akun baru
                    $user = User::create([
                        'username'         => $this->generateUsername($socialUser->getName() ?? $socialUser->getNickname()),
                        'email'            => $socialUser->getEmail(),
                        'password_hash'    => Hash::make(Str::random(32)),
                        $providerIdField   => $socialUser->getId(),
                        'avatar_url'       => $socialUser->getAvatar(),
                        'reputation_points' => 1,
                        'level'            => 1,
                    ]);

                    // Assign role user
                    $userRole = Role::where('name', 'user')->first();
                    if ($userRole) {
                        $user->roles()->attach($userRole->id, [
                            'id'          => Str::uuid(),
                            'assigned_at' => now(),
                        ]);
                    }

                    // Kirim notif complete_profile
                    $this->notificationService->send(
                        recipient    : $user,
                        actor        : null,
                        type         : 'complete_profile',
                        referenceId  : null,
                        referenceType: null,
                    );
                }
            }

            DB::commit();

            $token       = $user->createToken('auth_token')->plainTextToken;
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

            // Coba ekstrak frontend_url dari state (jika dikirim dari lokal)
            $request = request();
            if ($request->has('state')) {
                try {
                    $stateData = json_decode(base64_decode($request->query('state')), true);
                    $passedUrl = $stateData['frontend_url'] ?? null;
                    // Hanya izinkan jika benar-benar berawalan http://localhost
                    if ($passedUrl && str_starts_with($passedUrl, 'http://localhost')) {
                        $frontendUrl = rtrim($passedUrl, '/');
                    }
                } catch (\Exception $e) {
                    // Abaikan jika state tidak bisa di-decode
                }
            }

            return redirect("{$frontendUrl}/auth/callback?token={$token}");

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['message' => 'Terjadi kesalahan, coba lagi.', 'debug' => $e->getMessage()], 500);
        }
    }

    private function validateProvider(string $provider): void
    {
        if (! in_array($provider, ['google', 'github'])) {
            abort(404, 'Provider tidak didukung.');
        }
    }

    private function generateUsername(string $name): string
    {
        $base     = Str::slug(Str::lower($name), '_');
        $base     = substr($base, 0, 20);
        $username = $base;

        while (User::where('username', $username)->exists()) {
            $username = $base . '_' . rand(100, 999);
        }

        return $username;
    }
}
