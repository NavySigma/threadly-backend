<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class PostTag extends Pivot
{
    use HasUuids;

    protected $table = 'post_tags';
    public $timestamps = false;

    protected $fillable = [
        'post_id', 
        'tag_id'
    ];
}