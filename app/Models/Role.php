<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasUuids;
    
    public $timestamps = false;

    protected $fillable = [
        'name', 
        'permissions', 
        'created_at'
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function users(): BelongsToMany 
    {
        return $this->belongsToMany(User::class, 'user_roles')
                    ->using(UserRole::class)
                    ->withPivot('assigned_at');
    }
}