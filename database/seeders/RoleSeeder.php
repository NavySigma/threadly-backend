<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'admin',
                'permissions' => [
                    'manage_categories'  => true,
                    'manage_tags'        => true,
                    'manage_roles'       => true,
                    'delete_any_post'    => true,
                    'delete_any_comment' => true,
                ],
            ],
            [
                'name' => 'moderator',
                'permissions' => [
                    'edit_tag'           => true,
                    'delete_tag'         => true,
                    'delete_any_post'    => true,
                    'delete_any_comment' => true,
                ],
            ],
            [
                'name' => 'user',
                'permissions' => [
                    'create_post'        => true,
                    'edit_own_post'      => true,
                    'delete_own_post'    => true,
                    'create_comment'     => true,
                    'edit_own_comment'   => true,
                    'close_own_post'     => true,
                    'reopen_own_post'    => true,
                    'vote'               => true,
                    'bookmark'           => true,
                    'follow_user'        => true,
                    'create_tag'         => true,
                ],
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(
                ['name' => $role['name']],
                ['permissions' => $role['permissions']]
            );
        }
    }
}
