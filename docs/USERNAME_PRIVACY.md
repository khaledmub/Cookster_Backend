# Username & public profile privacy

## Field semantics

| Field | Meaning | Example |
|-------|---------|---------|
| `name` | Display name (not unique) | Khaled Ali |
| `user_name` | Unique public handle (stored lowercase) | `khaled_cooks` |

Mobile should show **title** = `name`, **subtitle** = `@user_name`.

## Registration & profile

- `user_name` is **required** on `POST /api/validate_register` and `POST /api/register`.
- Format: `^[a-z0-9_]{3,30}$` (lowercase letters, numbers, underscore).
- Uniqueness is **case-insensitive** (`ChefTest` and `cheftest` conflict).
- `POST /api/edit_profile` accepts `user_name` with the same rules (excluding the current user).
- Optional: `POST /api/check_username` with body `{ "user_name": "khaled" }` → `{ "status": true, "available": true }`.

## Existing users (migration)

On deploy, `2026_06_15_000001_add_user_name_to_front_users`:

1. Adds nullable `user_name` column.
2. Backfills every account without a handle from `name`, or email local-part if name is empty.
3. Appends `_2`, `_3`, … on collisions.
4. Adds a unique index.

Users are **not** forced to pick a new handle on next login; they receive an auto-generated one. They may change it via edit profile subject to uniqueness rules.

## Public vs private responses

**Public** endpoints (`profile_details`, search user lists, followers, blocked users, B2B lists) return only:

- `id`, `name`, `user_name`, `image`, `image_url`, `entity`
- Plus intentional public business fields where applicable (e.g. business contact on type-7 search).

They **never** return: `password`, `email`, `phone`, `uuid`, loyalty balances, or other internal fields.

**Authenticated own profile** (`GET /api/profile`, register response) still includes private account fields (`email`, `phone`, etc.) plus `user_name`.

## Search (`POST /api/search`)

- **Type 6 (users):** matches `name` OR `user_name` (case-insensitive). Does **not** search email or phone.
- **Type 7 (business):** matches user `name` / `user_name` plus public business fields (location, website, business type, contact fields). Does **not** search user email/phone.

## Video payloads

Video list/detail responses keep `user_name` as the **creator display name** (`front_users.name`) for backward compatibility. Creator handle is exposed as `creator_handle` where user rows are joined.
