<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class UserRole extends Pivot
{
    use HasUuids;

    protected $table = 'user_roles';
    public $timestamps = false; 

    protected $fillable = [
        'user_id', 
        'role_id', 
        'assigned_at'
    ];
}
