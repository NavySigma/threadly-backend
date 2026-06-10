<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Like extends Model
{
    use HasUuids;
    public $timestamps = false;
    protected $fillable = ['user_id', 'target_id', 'target_type', 'created_at'];

    public function target(): MorphTo { return $this->morphTo(); }
}
