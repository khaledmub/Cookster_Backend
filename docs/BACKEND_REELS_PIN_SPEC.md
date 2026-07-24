# Reels upload pin (`pin_video_id`)

Backend-owned rule for “show my just-uploaded post first in matching feeds”. Clients should **not** permanently reorder feeds locally once this is available.

## Problem split

| Scenario | Expected behavior | Client pin? |
|----------|-------------------|-------------|
| Brand-new upload in matching city | Already `#1` via `sort_by=newest` + `created_at` | No |
| Brand-new upload, first filtered fetch | Optional one-shot pin for immediate UX | Use `feed_hint` once |
| Old post (e.g. May 2025) + location filter | Correct rank by sort (may be page 4+) | **No** — not a pin case |

Location filter + chronological sort working correctly is **not a bug**.

## Upload response (`POST` create video/photo)

After a successful upload, API returns:

```json
{
  "status": true,
  "video_id": "f0f28ea9-b735-4845-a241-2ffc85932ec2",
  "feed_hint": {
    "reels_query": {
      "country": 194,
      "city": 102874,
      "sort_by": "newest",
      "pin_video_id": "f0f28ea9-b735-4845-a241-2ffc85932ec2"
    },
    "pin_expires_at": "2026-07-25T20:00:00+00:00"
  }
}
```

Mobile should use `feed_hint.reels_query` for the **first** General (or Near Me) refresh after upload, then drop `pin_video_id` on later pages.

## Feed request

```
GET /api/reels?country=194&city=102874&sort_by=newest&pin_video_id={video_id}
Authorization: Bearer …   (required for pin)
```

Also accepts `country_id` / `city_id` aliases (same as search/B2B).

### Pin rules (server enforced)

1. **First page only** — ignored when `cursor` is present.
2. **Authenticated** — guest requests ignore `pin_video_id`.
3. **Ownership** — pinned video must belong to the viewer.
4. **Recency** — upload must be **≤ 24 hours** old (`PIN_MAX_AGE_HOURS`).
5. **Eligibility** — same publish/transcode rules as the feed (`ready` or `is_image=1`, published, not soft-deleted).
6. **Geo match** — when the request has an active location filter, pinned video must match it (`country` / city group).
7. **Feeds** — `general` and `near_me` only.

### Pagination / dedupe

- Page 1: pinned item prepended if valid; duplicates removed; still returns `per_page` items.
- `meta.pinned_video_id` set when pin applied.
- `next_cursor` encodes `consumed_pin_id` so page 2+ **never repeats** the pinned item at its natural rank.

### Response meta

```json
{
  "meta": {
    "per_page": 10,
    "has_more": true,
    "next_cursor": "...",
    "sort_by": "newest",
    "pinned_video_id": "f0f28ea9-b735-4845-a241-2ffc85932ec2"
  }
}
```

Omit `pinned_video_id` when pin was not applied (invalid, expired, geo mismatch, or not first page).

## Client migration

1. On upload success, read `feed_hint`.
2. First General refresh: merge `feed_hint.reels_query` into the reels request (include auth token).
3. Page 2+: pass only `cursor` + same filter/sort — **do not** send `pin_video_id` again.
4. Remove local “prepend my upload” logic once backend is deployed.

## Verification

```bash
curl -s 'https://cookster.org/api/reels?country=194&city=102874&sort_by=newest&pin_video_id=YOUR_ID&per_page=10' \
  -H 'Authorization: Bearer TOKEN' \
  | jq '{first: .data[0].id, pinned: .meta.pinned_video_id}'
```

## Related

- Sort/filter baseline: [BACKEND_REELS_SORT_SPEC.md](./BACKEND_REELS_SORT_SPEC.md)
- General location filter: `ReelsController::resolveGeneralLocationGeoContext`
