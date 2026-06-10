<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    public function register(Request $request)
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:100', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', 'min:3'],
            // Pastikan ada field 'password_confirmation' di form/request
        ]);

        try {
            $user = DB::transaction(function () use ($validated) {
                $user = User::create([
                    'username' => $validated['username'],
                    'email' => $validated['email'],
                    'password_hash' => Hash::make($validated['password']),
                ]);

                $this->notificationService->send(
                    recipient    : $user,
                    actor        : null,
                    type         : 'complete_profile',
                    referenceId  : null,
                    referenceType: null,
                );

                $defaultRole = Role::where('name', 'user')->first();

                if ($defaultRole) {
                    $user->roles()->attach($defaultRole->id, [
                        'id' => Str::uuid(),
                    ]);
                }

                return $user;
            });

            return response()->json([
                'message' => 'Registrasi berhasil!',
                'data' => $user->load('roles'),
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan saat registrasi.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (! $user || ! Hash::check($validated['password'], $user->password_hash)) {
            return response()->json([
                'message' => 'Email atau password yang kamu masukkan salah.',
            ], 401);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login berhasil!',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'user' => $user->load('roles'),
        ], 200);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout. Token telah dihapus.'
        ], 200);
    }
}
