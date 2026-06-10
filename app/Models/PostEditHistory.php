<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostEditHistory extends Model
{
    use HasUuids;

    protected $table = 'post_edit_history';
    public $timestamps = false;

    protected $fillable = [
        'post_id', 'edited_by', 'body_before', 'body_after', 'reason', 'edited_at'
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
