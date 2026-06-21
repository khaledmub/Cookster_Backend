# Backend video + CDN spec (Cookster mobile reels)

Mobile reels scroll vertically (TikTok-style). The Flutter app supports HLS, multi-quality MP4, CDN URLs, and preloading — but only when feed/profile APIs return the correct fields **before** the video appears in feeds.

**Success:** After upload, within seconds (or when `transcode_status = ready`), feed/profile APIs return playable HLS + low-quality MP4 + poster URLs that return HTTP 200 from CDN. First frame should start in < 500 ms on Wi‑Fi when the user swipes to the next reel (preload helps).

## Mobile app contract

For each video object in `GET /api/reels`, profile video lists, hashtag feeds, saved/liked, etc., the app parses:

| JSON field | Purpose |
|------------|---------|
| `transcode_status` | Must be `"ready"` when HLS/ladder is available |
| `processing_status` | While pending, app uses poster/grid image, not HLS guess |
| `hls_playlist_url` or `hls_url` | Preferred playback (master `.m3u8`) — absolute `https://cdn.cookster.org/...` |
| `video_sources.url_360` | First MP4 fallback (fast start) |
| `video_sources.url_720` | Mid quality |
| `video_sources.url_1080` | High quality |
| `video_url` | Legacy full MP4 fallback |
| `video` | Legacy path only if no CDN URLs |
| `thumbnail_url` / `thumbnail` | Grid + reel poster while video buffers |
| `thumbnail_blur` | Optional tiny blur placeholder (absolute URL) |
| `image_url` / `image` | Fallback poster while processing |
| `id` | Stable key for player pool + preload |

### Playback priority when `transcode_status == "ready"`

**Mobile HD-first (current):** `url_1080` → `url_720` → `url_360` → `hls_playlist_url` → `video_url`

Partial MP4 playback requires **fast-start** (`moov` before `mdat`) on **every** ladder tier — especially `1080.mp4`.

When **not** ready: poster fields only — no HLS or ladder URLs.

## Example ready video payload

```json
{
  "id": "12345",
  "transcode_status": "ready",
  "processing_status": "ready",
  "hls_playlist_url": "https://cdn.cookster.org/videos/12345/hls/master.m3u8",
  "hls_url": "https://cdn.cookster.org/videos/12345/hls/master.m3u8",
  "video_sources": {
    "url_360": "https://cdn.cookster.org/videos/12345/360.mp4",
    "url_720": "https://cdn.cookster.org/videos/12345/720.mp4",
    "url_1080": "https://cdn.cookster.org/videos/12345/1080.mp4"
  },
  "video_url": "https://cdn.cookster.org/videos/original.mp4",
  "thumbnail_url": "https://cdn.cookster.org/videos/12345/thumb.webp",
  "thumbnail_blur": "https://cdn.cookster.org/videos/12345/thumb_blur.webp",
  "image_url": "https://cdn.cookster.org/videos/12345/thumb.webp"
}
```

**Rules:**

- All media URLs must be absolute HTTPS (prefer `cdn.cookster.org`).
- Same shape on: reels feed, user profile videos, hashtag, single video, saved/liked.
- New uploads must appear in profile API after transcode **or** with clear `pending` + poster (not broken links).

## Backend implementation (this repo)

### Transcoding pipeline

After every video upload (`POST /api/videos/create`):

1. `ProcessVideoJob` (queue: `video-processing`) generates:
   - HLS: `videos/{id}/hls/master.m3u8` + segments
   - MP4: `videos/{id}/360.mp4`, `720.mp4`, `1080.mp4`
   - Poster from cover image (`ProcessVideoThumbnailJob`) **or** first video frame when no cover
2. `transcode_status: "ready"` is set only after `VideoMediaVerifier` confirms HLS master + required MP4 ladder (360 + 720 + 1080 when HLS variants exist) + `thumb.webp` + `thumb_blur.webp` exist on object storage.
3. Until ready: `transcode_status: "pending"`, `processing_status: "processing"`, return `thumbnail_url` / `image_url` where available. **No** raw upload MP4 or phantom ladder URLs.

### MP4 ladder (mobile preload contract)

| File | Resolution | Encoding |
|------|------------|----------|
| `360.mp4` | 360p | H.264 main, `-movflags +faststart`, GOP ~2s (`FFMPEG_GOP_SIZE=48`) |
| `720.mp4` | 720p | Same (when HLS has 720p variant) |
| `1080.mp4` | 1080p | Same (when source + HLS support 1080p — **required for HD-first mobile**) |

`video_sources` only includes URLs for files that **exist on CDN** (no phantom tiers).

### HLS encoding

- Segment duration: **2 s** default (`FFMPEG_HLS_SEGMENT_SECONDS=2`)

### API endpoints

| Endpoint | Serializer |
|----------|------------|
| `GET /api/reels` | `ReelResource` |
| `GET /api/reels?feed=general` | General home tab (default) |
| `GET /api/reels?feed=near_me&latitude=&longitude=` | Near Me (geo filter + `meta.geo_fallback`) |
| `GET /api/reels?feed=following` | Following (auth required) |
| `GET /api/reels?feed=user&user_id=` | Profile reel viewer (optional `video_type`, `anchor_id`) |
| Profile, list, search, saved/liked, details | `AppHelper::decorateVideoRow()` |
| `GET /api/videos/processing_status?video_id=` | Full decorated video + status fields |

### Profile / avatar images

- Object key: `storage/front_users/{filename}.jpg`
- CDN URL: `https://cdn.cookster.org/storage/front_users/{filename}.jpg`
- `POST /api/edit_profile` writes locally **and** syncs to object storage.

### CDN cache headers (upload time)

| Asset | Cache-Control |
|-------|---------------|
| HLS segments (`.ts`, `.m4s`) | `public, max-age=31536000, immutable` |
| HLS playlists (`.m3u8`) | `public, max-age=60` |
| MP4 renditions + posters | `public, max-age=31536000, immutable` |

### HLS encoding

- Segment duration: 2–4 s (`FFMPEG_HLS_SEGMENT_SECONDS`, default **2**)
- Variants: 360p + 720p + 1080p in master playlist
- Audio: AAC (Android MediaKit / ExoPlayer compatible)
- First segment at t=0 (`-ss 0` for frame-based posters; HLS independent segments)

## Acceptance tests

1. Upload a 30 s video → within transcode window `transcode_status=ready`, all URLs return 200.
2. `curl -I` on `hls_playlist_url` → 200, valid `Content-Type`.
3. First HLS segment loads quickly from CDN (same region as users).
4. `GET /api/reels` first ready item has `hls_playlist_url` + `video_sources.url_360`.
5. User profile video list returns same media fields as reels feed.
6. New upload visible on profile API within ~30 s (`pending` or `ready`).
7. Avatar URL from profile API returns 200.

## Priority

| Priority | Work |
|----------|------|
| P0 | Transcode → HLS + 360p + poster; correct `transcode_status`; fix 404s |
| P1 | Same JSON on all video endpoints |
| P2 | CDN cache headers + segment tuning |
| P3 | Processing status endpoint for upload UI |

## Ops

```bash
# Queue workers (both queues required for cover-image posters)
php artisan queue:work --queue=video-processing,default --timeout=7200

# Backfill legacy videos (missing transcode)
php artisan videos:backfill-media --posters --transcode --limit=20

# Re-encode ready videos missing 720.mp4 fast-start or thumb_blur.webp (no transcode_status flip)
php artisan videos:backfill-media --upgrade-ladder --limit=500

# Re-mux existing 360/720/1080 MP4s with moov before mdat (transcode_status stays ready)
php artisan videos:backfill-media --reencode-faststart --heights=360,720,1080 --limit=500

# Add missing 1080.mp4 (and other tiers) to ready catalog
php artisan videos:backfill-media --upgrade-ladder --limit=500

# Validate CDN + API contract on random sample
php artisan videos:validate-media --sample=10 --api-check
```

### Mobile smooth-playback validation (per video)

```bash
# moov before mdat (fast-start)
ffprobe -v error -show_format videos/{id}/360.mp4

# Range support on CDN
curl -I "https://cdn.cookster.org/videos/{id}/360.mp4"
curl -H "Range: bytes=0-262143" -I "https://cdn.cookster.org/videos/{id}/1080.mp4"  # expect 206

# API must not advertise missing tiers
curl -s "https://cookster.org/api/reels?feed=user&user_id=..." | jq '.data[0].video_sources'
```

See also: [CDN_MEDIA.md](./CDN_MEDIA.md), [PERFORMANCE_DEPLOYMENT.md](./PERFORMANCE_DEPLOYMENT.md).
