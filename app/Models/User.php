<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasUuids, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'username', 'email', 'password_hash', 'avatar_url', 'bio', 'reputation_points',
        'level', 'is_banned', 'google_id', 'github_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'password_hash',
        'remember_token',
        'google_id',
        'github_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function newPivot(Model $parent, array $attributes, $table, $exists, $using = null)
    {
        $pivot = parent::newPivot($parent, $attributes, $table, $exists, $using);

        if ($using === Follow::class) {
            $pivot->timestamps = false;
        }

        return $pivot;
    }

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->using(UserRole::class)->withPivot('assigned_at');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    // Follows
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->using(Follow::class)->withPivot('created_at');
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->using(Follow::class)->withPivot('created_at');
    }

    // User.php
    public function hasRole(string|array $roles): bool
    {
        return $this->roles()->whereIn('name', (array) $roles)->exists();
    }

    public function isAdmin(): bool
    {
        return $this->hasRole('admin');
    }

    public function isModerator(): bool
    {
        return $this->hasRole('moderator');
    }

    public function isModeratorOrAdmin(): bool
    {
        return $this->hasRole(['admin', 'moderator']);
    }

    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'reporter_id');
    }

    // Tambah helper
    public function isBanned(): bool
    {
        return $this->is_banned;
    }

    public function getLevelTitleAttribute(): string
    {
        return match($this->level) {
            1  => 'Newbie',
            2  => 'Member',
            3  => 'Contributor',
            4  => 'Warrior',
            5  => 'Elite',
            6  => 'Master',
            7  => 'Grandmaster',
            8  => 'Epic',
            9  => 'Legend',
            10 => 'Mythic',
            default => 'Newbie',
        };
    }

    public function getNextLevelPointsAttribute(): int
    {
        $levels = [
            1 => 0, 2 => 15, 3 => 20, 4 => 30,
            5 => 40, 6 => 50, 7 => 60,
            8 => 70, 9 => 80, 10 => 90,
        ];

        $nextLevel = $this->level + 1;

        return $levels[$nextLevel] ?? $levels[10];
    }
}
