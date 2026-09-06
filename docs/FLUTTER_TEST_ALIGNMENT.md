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

Send **lat/lng only** for Near Me. Do not attach stored upload `country` / `city` IDs — stale prefs (e.g. Saudi/Riyadh) are ignored when they do not match the GPS country, but omitting them is cleaner.

Optional same-country city (Change Location):

```
GET /api/reels?feed=near_me&latitude=29.97&longitude=30.94&city=31802
```

`city` is honored only when it sits in the GPS-resolved country.

## Response shape

```json
{
  "status": true,
  "data": [ /* ReelResource[] */ ],
  "meta": {
    "per_page": 10,
    "has_more": true,
    "next_cursor": "...",
    "geo_fallback": false,
    "geo_empty_country": false,
    "geo_country_id": 64,
    "geo_country_name": "Egypt",
    "geo_city_id": 31802,
    "geo_city_name": "Cairo"
  }
}
```

Each item in `data` includes: `transcode_status`, `video_sources`, `hls_playlist_url`, `thumbnail_url`, `thumbnail_blur`, `user.image`, etc. (see `ReelResource`).

## Near Me geo meta

| Field | Meaning |
|-------|---------|
| `geo_fallback` | `true` only when GPS could not be resolved and the server returned the unfiltered general dump |
| `geo_empty_country` | GPS country has **zero** published videos. `data` is empty — show “no videos near you in {country}” + browse-another-country |
| `geo_country_id` / `geo_country_name` | Country resolved from lat/lng (not from client catalog IDs) |
| `geo_city_id` / `geo_city_name` | Nearest city used for the city-scope query |

Rules:

- Applies only to `feed=near_me`
- Country/city are resolved from lat/lng; mismatched client IDs are ignored
- City group empty but the country has videos → country-scope results (`geo_fallback: false`)
- Country has zero videos → empty list + `geo_empty_country: true` (no worldwide Riyadh dump)
- `feed=following` never falls back to general (empty list if user follows nobody)

Use `geo_empty_country` for “Browse another country” (picker → General tab with that location filter). Use `geo_fallback` only for the rare unresolved-GPS dump.

## Unseen-first ranking (`unseen_first=1`)

Home tabs should send:

```
GET /api/reels?feed=general|near_me|following&sort_by=newest&unseen_first=1&device_id={id}
```

- Logged-in: ranking uses the user id (token). Guest: `device_id`.
- Order: never viewed (by `sort_by`), then viewed. Pin still `#0` when pin rules match.
- Cursor encodes `phase` (`unseen`|`seen`) + `created_at` + `id`. After unseen is empty, the next page is the watched catalog (`meta.unseen_exhausted: true`).
- `feed=user` ignores `unseen_first`.
- Without `device_id` and without auth, the param is a no-op (same as today).

Record watches so pagination stays correct (not Firebase-only):

```
POST /api/reels/{videoId}/view
POST /api/reels/views
```

Body: `{ "device_id": "..." }` and, for batch, `{ "device_id": "...", "video_ids": ["...", "..."] }` (max 20). Auth optional. Idempotent.

Meta when applied: `unseen_first`, `unseen_exhausted`.

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
- Near Me Egypt: GPS at 6th of October → expands to Cairo + Giza (~31 km) → `geo_empty_country: false`
- Cairo (`31802`) has local videos → `geo_fallback: false`
- Stale Saudi/Riyadh IDs + Egypt GPS → Egypt, not Riyadh
- GPS in a country with no published videos → empty `data` + `geo_empty_country: true`
