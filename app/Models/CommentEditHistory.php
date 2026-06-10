<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentEditHistory extends Model
{
    use HasUuids;

    protected $table = 'comment_edit_history';
    public $timestamps = false;

    protected $fillable = [
        'comment_id', 'edited_by', 'body_before', 'body_after', 'edited_at'
    ];

    public function comment(): BelongsTo
    {
        return $this->belongsTo(Comment::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
