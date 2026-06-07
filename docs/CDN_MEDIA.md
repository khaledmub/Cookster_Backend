# CDN media delivery (Cookster)

## Object layout (per video `{id}`)

| Path | Purpose |
|------|---------|
| `videos/{id}/thumb.webp` | Reel/grid poster (generated on upload) |
| `videos/{id}/thumb_blur.webp` | Tiny blur placeholder |
| `videos/{id}/360.mp4` | Progressive MP4 (360p height) |
| `videos/{id}/720.mp4` | Progressive MP4 (720p) |
| `videos/{id}/1080.mp4` | Progressive MP4 (1080p) |
| `videos/{id}/hls/master.m3u8` | HLS master playlist |
| `videos/{id}/hls/*.ts` | HLS segments |

Legacy: `videos/thumbnail/{filename}.jpg` (100×100) remains for older clients.

## API fields

- **Reels** `GET /api/reels`: `thumbnail`, `thumbnail_url`, `thumbnail_blur`, `video_url`, `hls_playlist_url`, `video_sources`, `transcode_status`
- **Feeds** (`/api/videos/list`, profile videos, etc.): same via `AppHelper::decorateVideoRow()`
- **Users**: `user.image` / `user_image_url` / `image_url` are always a single absolute URL at `https://cdn.cookster.org/storage/front_users/{filename}` (no double prefix)

## GCS / CDN (ops)

Configure on the **bucket** backing `cdn.cookster.org`:

1. **CORS** — allow `GET`, `HEAD`; expose `Content-Length`, `Content-Range`, `Accept-Ranges` for MP4/HLS.
2. **Range requests** — enabled by default on GCS; ensure CDN/backend does not strip `Range` / `206` responses.
3. **Cache-Control** — uploads set `public, max-age=31536000, immutable` on posters, MP4 renditions, and HLS **segments**; HLS **playlists** (`.m3u8`) use `public, max-age=60`.

### Example bucket CORS (JSON)

```json
[
  {
    "origin": ["*"],
    "method": ["GET", "HEAD"],
    "responseHeader": ["Content-Type", "Content-Length", "Content-Range", "Accept-Ranges"],
    "maxAgeSeconds": 3600
  }
]
```

## Monitoring

Alert on elevated **404** rates for:

- `cdn.cookster.org/videos/*/thumb.webp`
- `cdn.cookster.org/videos/*/360.mp4`

Indicates failed thumbnail or transcode jobs.

## Backfill existing content

```bash
php artisan videos:backfill-media --posters --limit=100
php artisan videos:backfill-media --transcode --limit=20
php artisan videos:backfill-media --posters --transcode --dry-run
```

Ensure queue workers run `video-processing` with timeout ≥ `FFMPEG_TIMEOUT` (default 7200s).
