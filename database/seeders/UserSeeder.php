<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@forum.com'],
            [
                'username'      => 'superadmin',
                'password_hash' => Hash::make('Admin123'),
                'bio'           => 'I am not a King I am not a God, I am Atomic.',
                'reputation_points' => 9999
            ]
        );

        $adminRole = Role::where('name', 'admin')->first();

        if ($adminRole && !$adminUser->roles()->where('role_id', $adminRole->id)->exists()) {
            $adminUser->roles()->attach($adminRole->id,[
                'id' => Str::uuid()
            ]);
        }
    }
}