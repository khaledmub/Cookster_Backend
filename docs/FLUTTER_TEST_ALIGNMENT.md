# Flutter test alignment — home feed tabs

Home tabs (General, Near Me, Following) should all use **`GET /api/reels`** with a `feed` query parameter. Do **not** use `POST /api/videos/list` for home tabs — that legacy path is slower (sponsored merge, page cache, legacy shape).

## Endpoints

| Tab | Request |
|-----|---------|
| General | `GET /api/reels` or `GET /api/reels?feed=general` |
| Near Me | `GET /api/reels?feed=near_me&latitude={lat}&longitude={lng}` |
| Following | `GET /api/reels?feed=following` (requires auth) |
| Profile reels | `GET /api/reels?feed=user&user_id={uuid}` |
| Profile tab | `GET /api/reels?feed=user&user_id={uuid}&video_type={typeId}` |
| Grid tap | `GET /api/reels?feed=user&user_id={uuid}&anchor_id={videoUuid}` |
| Load more | `GET /api/reels?cursor={base64}` (cursor encodes feed + context) |

Optional manual city (Change Location):

```
GET /api/reels?feed=near_me&latitude=29.97&longitude=30.94&city=31802
```

## Response shape (unchanged)

```json
{
  "status": true,
  "data": [ /* ReelResource[] */ ],
  "meta": {
    "per_page": 10,
    "has_more": true,
    "next_cursor": "...",
    "geo_fallback": false
  }
}
```

Each item in `data` includes: `transcode_status`, `video_sources`, `hls_playlist_url`, `thumbnail_url`, `thumbnail_blur`, `user.image`, etc. (see `ReelResource`).

## `meta.geo_fallback` (Near Me only)

| Value | Meaning |
|-------|---------|
| `false` | Videos matched the user's city/geo filter |
| `true` | No local videos in resolved city group; server returned the general feed |

Use this to show copy such as “No local videos — showing all” vs “Showing videos near you”.

Geo fallback rule (same as legacy `VideoFeedService`):

- Applies only to `feed=near_me`
- When resolved city group has 0 videos on the first page, retry without geo filter
- `feed=following` never falls back to general (empty list if user follows nobody)

## Flutter migration

```dart
bool get _usesReelsApi =>
    selectedType == 'General' ||
    selectedType == 'Near Me' ||
    selectedType == 'Following';
```

Remove `_fetchNearMeFeedPage`, `_fetchLegacyFeedPage` for home tabs, and client-side geo fallback hacks once the app points all three tabs at `GET /api/reels`.

## Profile reel viewer (`feed=user`)

Use `GET /api/reels?feed=user&user_id={uuid}` for `ProfileReelScreen` — same `ReelResource` + cursor + Redis cache as home tabs.

- `video_type` — optional; filter to one profile grid tab
- `anchor_id` — optional; first page starts at the tapped grid video
- `meta.geo_fallback` — always `false`
- Visit profile (not own): expired subscription hides videos (same as `profile_details`)
- Keep `GET /api/profile` / `GET /api/profile_details` for profile shell + grid thumbnails only

## Legacy endpoint (deprecate for home + profile viewer)

`POST /api/videos/list` remains for hashtag, search, admin, and other non-home use cases only.

## Test server notes

- Posters: `thumb.webp` + `thumb_blur.webp` backfilled for all `transcode_status=ready` videos
- Near Me Egypt: GPS at 6th of October → expands to Cairo + Giza (~31 km) → `geo_fallback: false`
- Cairo (`31802`) has local videos → `geo_fallback: false`
