<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tag extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = ['name', 'slug', 'color', 'usage_count', 'created_at'];

    public function posts()
    {
        return $this->belongsToMany(Post::class, 'post_tags')
            ->using(PostTag::class);
    }
}
