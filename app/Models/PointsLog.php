<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PointsLog extends Model
{
    use HasUuids;

    protected $table = 'points_log';
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'points',
        'action_type',
        'reference_id',
        'description',
        'created_at'
    ];

    public function user(): BelongsTo 
    { 
        return $this->belongsTo(User::class); 
    }
}