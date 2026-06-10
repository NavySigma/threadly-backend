<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Follow extends Pivot
{
    use HasUuids;

    protected $table = 'follows';
    public $timestamps = false;

    protected $fillable = [
        'follower_id',
        'following_id',
        'created_at'
    ];

    public $incrementing = false;
    protected $keyType = 'string';

    public function follower()
    {
        return $this->belongsTo(User::class, 'follower_id');
    }

    public function following()
    {
        return $this->belongsTo(User::class, 'following_id');
    }
}
