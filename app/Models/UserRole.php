<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class UserRole extends Pivot
{
    use HasUuids;

    protected $table = 'user_roles';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'user_id',
        'role_id',
        'assigned_at',
    ];
}
