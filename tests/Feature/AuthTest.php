<?php

namespace Tests\Feature;

use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use CreatesUser, RefreshDatabase;

    // ── Register ─────────────────────────────────────────────────────

    public function test_register_berhasil(): void
    {
        Role::create(['name' => 'user']);

        $this->postJson('/api/register', [
            'username' => 'newuser',
            'email' => 'newuser@example.com',
            'password' => 'Password1a',
            'password_confirmation' => 'Password1a',
        ])->assertStatus(201)
            ->assertJsonPath('message', 'Registrasi berhasil!');

        $this->assertDatabaseHas('users', ['email' => 'newuser@example.com']);
    }

    public function test_register_gagal_username_sudah_ada(): void
    {
        Role::create(['name' => 'user']);
        $this->makeUser(['username' => 'existing', 'email' => 'existing@example.com']);

        $this->postJson('/api/register', [
            'username' => 'existing',
            'email' => 'other@example.com',
            'password' => 'Password1a',
            'password_confirmation' => 'Password1a',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['username']);
    }

    public function test_register_gagal_email_sudah_ada(): void
    {
        Role::create(['name' => 'user']);
        $this->makeUser(['username' => 'someuser', 'email' => 'taken@example.com']);

        $this->postJson('/api/register', [
            'username' => 'newuser2',
            'email' => 'taken@example.com',
            'password' => 'Password1a',
            'password_confirmation' => 'Password1a',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_register_gagal_password_terlalu_pendek(): void
    {
        $this->postJson('/api/register', [
            'username' => 'newuser3',
            'email' => 'new3@example.com',
            'password' => 'Ab1',
            'password_confirmation' => 'Ab1',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_register_gagal_password_tidak_cocok(): void
    {
        $this->postJson('/api/register', [
            'username' => 'newuser4',
            'email' => 'new4@example.com',
            'password' => 'Password1a',
            'password_confirmation' => 'DifferentPass1B',
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    // ── Login ─────────────────────────────────────────────────────────

    public function test_login_berhasil(): void
    {
        $this->makeUser(['email' => 'login@example.com']);

        $this->postJson('/api/login', [
            'email' => 'login@example.com',
            'password' => 'Password1a',
        ])->assertStatus(200)
            ->assertJsonStructure(['access_token', 'token_type', 'user']);
    }

    public function test_login_gagal_password_salah(): void
    {
        $this->makeUser(['email' => 'login2@example.com']);

        $this->postJson('/api/login', [
            'email' => 'login2@example.com',
            'password' => 'WrongPassword',
        ])->assertStatus(401);
    }

    public function test_login_gagal_email_tidak_ada(): void
    {
        $this->postJson('/api/login', [
            'email' => 'notexist@example.com',
            'password' => 'Password1a',
        ])->assertStatus(401);
    }

    public function test_login_gagal_akun_banned(): void
    {
        $this->makeUser(['email' => 'banned@example.com', 'is_banned' => true]);

        $this->postJson('/api/login', [
            'email' => 'banned@example.com',
            'password' => 'Password1a',
        ])->assertStatus(403);
    }

    // ── Logout ────────────────────────────────────────────────────────

    public function test_logout_berhasil(): void
    {
        [, $headers] = $this->actingAsUser();

        $this->withHeaders($headers)->postJson('/api/logout')
            ->assertStatus(200);
    }

    public function test_logout_tanpa_token_ditolak(): void
    {
        $this->postJson('/api/logout')->assertStatus(401);
    }
}

