# Reels feed sort (`sort_by`)

Mobile sends `sort_by` on every home feed request. Backend must honor it and expose parseable timestamps.

## Endpoints

| Endpoint | Parameter |
|----------|-----------|
| `GET /api/reels` | `sort_by=newest\|oldest` (query) |
| `POST /api/videos/list` | `sort_by` in body when `paginate=1` |

## Sort behavior

- `newest` (default): `ORDER BY created_at DESC, id DESC`
- `oldest`: `ORDER BY created_at ASC, id ASC`
- Unknown values → `newest`

Implementation: `App\Support\VideoFeedSort`.

### `/api/reels`

- Pure chronological feed (no `feed_seed`, no sponsored slot injection).
- Cursor encodes `created_at`, `id`, and `sort_by` of the last item.
- Response `meta.sort_by` echoes the active sort.

### `/api/videos/list`

- When `sort_by` is **sent**: pure chronological (Option A).
- When `sort_by` is **omitted**: legacy curated feed (`feed_seed` + sponsored merge) unchanged.

## Required fields on every video

```json
{
  "id": "77926c0f-4ff0-4238-9757-e24ed44861a2",
  "created_at": "2024-12-13T10:57:49.000000Z",
  "updated_at": "2024-12-13T10:57:49.000000Z"
}
```

- ISO 8601 UTC strings with microseconds (`App\Support\ApiTimestamp`).
- Reels: `ReelResource`.
- Lists/details: `AppHelper::decorateVideoRow()`.

## Response shape (`/api/reels`)

Items are under **`data`**, not `videos`:

```json
{
  "status": true,
  "data": [ { "id": "...", "created_at": "..." } ],
  "meta": {
    "per_page": 10,
    "has_more": true,
    "next_cursor": "...",
    "sort_by": "newest"
  }
}
```

## Verification

```bash
# Newest first
curl -s 'https://cookster.org/api/reels?sort_by=newest&per_page=5' \
  -H 'Accept: application/json' \
  | jq '.data[0:3] | .[] | {id, created_at}'

# Oldest first (first id must differ)
curl -s 'https://cookster.org/api/reels?sort_by=oldest&per_page=5' \
  -H 'Accept: application/json' \
  | jq '.data[0:3] | .[] | {id, created_at}'
```

Pass: different first `id`; `created_at` present; timestamps decrease (newest) or increase (oldest).

## Tests

```bash
php artisan test --filter=VideoFeedSortTest
```
