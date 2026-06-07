<?php

namespace App\Models;

use App\Services\CdnService;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Video extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'videos';

    protected $guarded = [];

    protected $casts = [
        'is_image' => 'boolean',
        'is_sponsored' => 'boolean',
        'is_soft_delete' => 'boolean',
        'status' => 'integer',
        'publish_type' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(FrontUser::class, 'front_user_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(VideoComment::class, 'video_id');
    }

    /** Saved videos — used as the SQL-backed likes counter for the reels feed. */
    public function saves(): HasMany
    {
        return $this->hasMany(UserSavedVideo::class, 'video_id');
    }

    /**
     * Object key on the s3 disk (e.g. videos/1234.mp4).
     */
    public function getStorageKeyAttribute(): ?string
    {
        if (empty($this->video)) {
            return null;
        }

        $video = (string) $this->video;

        return str_starts_with($video, 'videos/')
            ? $video
            : 'videos/'.ltrim($video, '/');
    }

    /**
     * CloudFront signed URL when enabled, otherwise public CDN or S3/GCS URL.
     */
    protected function cdnUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $key = $this->storage_key;
            if ($key === null) {
                return null;
            }

            $cdn = app(CdnService::class);

            if ($cdn->isCloudFrontSigningEnabled()) {
                return $cdn->generateSignedUrl($key);
            }

            return $cdn->urlForPath($key, signed: false);
        });
    }
}
