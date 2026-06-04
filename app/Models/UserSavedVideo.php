<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSavedVideo extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'user_saved_videos';

    protected $guarded = [];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class, 'video_id');
    }
}
