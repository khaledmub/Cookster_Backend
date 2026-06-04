# Cookster performance rollout

## Backend (`cookster_admin`)

### Deploy steps

1. Run migrations on a **live-imported** database (skip default Laravel tables that already exist):
   ```bash
   php artisan migrate --path=database/migrations/2026_05_15_000001_add_video_feed_performance_indexes.php --force
   ```
   For a fresh install, use `php artisan migrate` as usual.
2. Run remaining performance migrations on live DB (skip tables that already exist):
   ```bash
   php artisan migrate --path=database/migrations/2026_05_23_000001_add_query_performance_indexes.php --force
   php artisan migrate --path=database/migrations/2026_05_24_000001_add_video_hls_transcode_columns.php --force
   ```
3. Set `CACHE_STORE=redis`, `QUEUE_CONNECTION=redis`, and `REDIS_QUEUE_RETRY_AFTER=7500` (must exceed `FFMPEG_TIMEOUT`, default 7200).
4. Start queue workers (thumbnails + long transcodes):
   ```bash
   php artisan queue:work --queue=video-processing,thumbnails,notifications,default --sleep=3 --tries=3 --timeout=7200
   ```
   Use Supervisor in production, for example:
   ```ini
   [program:cookster-queue]
   command=php /path/to/cookster_admin/artisan queue:work --queue=video-processing,thumbnails,notifications,default --sleep=3 --tries=3 --timeout=7200
   autostart=true
   autorestart=true
   user=www-data
   ```

### API changes

| Endpoint | Paginated usage |
|----------|-----------------|
| `POST /api/videos/list` | `paginate=1`, `per_page` (max 30), `page` or `cursor` |
| `POST /api/search` (type=1 videos) | `paginate=1`, `per_page`, `page` |
| `POST /api/videos/saved_list` | `paginate=1`, `per_page`, `page` |
| `POST /api/videos/liked_videos_list` | `paginate=1`, `per_page`, `page` (with `video_ids`) |
| `GET /api/videos/list2` | `paginate=1`, `per_page`, `page` |

Legacy clients without `paginate` still receive the full-list response shape (up to 100k rows) for backward compatibility.

- `POST /api/videos/create` — thumbnail processing is queued when an image is uploaded; response includes `video_id` immediately.
- `GET /api/videos/processing_status?video_id={uuid}` — poll `processing_status` (`processing` | `ready` | `failed`).

### Legacy sunset

- `VideoFeedService::legacyList()` remains for clients that omit `paginate`.
- Dead controller methods `videos_list_old` and `videos_list_legacy_removed` were removed; use `videos_list` only.
- Plan to disable `legacyList()` after app store adoption enforces `paginate=1` (target: next major release).

### Environment

- `QUEUE_CONNECTION=database` (default in `.env.example`)
- `CACHE_STORE=database` (used for city-group caching)

### Smoke test

```bash
curl -X POST http://127.0.0.1:8000/api/videos/list \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"paginate":1,"per_page":15,"page":1}'
```

Expect ~15 videos and `meta.has_more`, `meta.feed_seed`.

## Flutter (`cookster_mobileapp`)

### Behavior

- Feed requests use `paginate=1` and infinite scroll via `fetchMoreVideos`.
- Saved reels, liked reels, and search video grids load more on scroll (`fetchMore*` + `meta.has_more`).
- Hashtag taps open `HashtagReelScreen` (vertical reels + `VideoPlayerWidget` + debounced Firestore views).
- Hashtag feed uses the same pagination pattern (`HashtagController.fetchMoreVideos`).
- JSON parsing runs in a background isolate (`compute`).
- Firestore view writes are debounced (2s) and deduplicated per session on reels.
- Reels, single-video, and hashtag playback use `media_kit` via `VideoPlayerWidget` and `MediaKitPlayerPool`.
- Upload/camera flows may still use `video_player` / `chewie`.
- `VideoAnalyticsTracker` supports both `video_player` and `media_kit`.

### QA checklist (device)

| Test | Pass criteria |
|------|----------------|
| Cold feed open | First 15 videos visible &lt; 1s on 4G/WiFi |
| Fast scroll | 20+ swipes without multi-second freezes |
| Memory | 5 min scroll, no steady RAM climb |
| Revisit reel | No black screen &gt; 1s when swiping back one video |
| Upload with image | API returns quickly; thumbnail after queue |
| Old app (no paginate) | Legacy feed still loads |
| Saved / liked lists | Load first page without timeout |
| Search (videos) | First page returns without loading 100k rows |

### Rollout order

1. Deploy backend + run performance migration + queue worker on staging/production.
2. Ship Flutter build with `paginate=1` to internal/beta.
3. Soak test 2–3 days; monitor API p95, DB CPU, Firebase write volume.
4. Production release; disable legacy full-list after adoption threshold.
