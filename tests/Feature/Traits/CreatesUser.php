<?php

namespace Tests\Feature\Traits;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

trait CreatesUser
{
    protected function makeUser(array $overrides = []): User
    {
        return User::create(array_merge([
            'username'          => 'user_' . Str::random(6),
            'email'             => Str::random(6) . '@test.com',
            'password_hash'     => Hash::make('Password1a'),
            'reputation_points' => 20,
            'level'             => 1,
            'is_banned'         => false,
        ], $overrides));
    }

    protected function makeAdmin(array $overrides = []): User
    {
        $user = $this->makeUser($overrides);
        $role = Role::firstOrCreate(['name' => 'admin'], ['name' => 'admin']);
        $user->roles()->attach($role->id, ['id' => Str::uuid()]);
        return $user;
    }

    protected function actingAsUser(array $overrides = []): array
    {
        $user  = $this->makeUser($overrides);
        $token = $user->createToken('test')->plainTextToken;
        return [$user, ['Authorization' => "Bearer $token"]];
    }

    protected function actingAsAdmin(array $overrides = []): array
    {
        $user  = $this->makeAdmin($overrides);
        $token = $user->createToken('test')->plainTextToken;
        return [$user, ['Authorization' => "Bearer $token"]];
    }
}
