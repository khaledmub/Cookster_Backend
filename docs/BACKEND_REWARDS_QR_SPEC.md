# Partner QR Rewards — API contract

Generic item + quantity deals. Separate from the profile-share QR and from `one_time_discount_history`.

Base: `https://cookster.org/api`  
Auth: Sanctum Bearer (`auth:sanctum`)

## Endpoints

| Method | Path | Who | Purpose |
|--------|------|-----|---------|
| GET | `/rewards/my-code` | any signed-in user | Rotating client QR payload |
| GET | `/rewards/deals/current` | `entity == 2` | Latest deal (any status) or `no_active_deal` |
| POST | `/rewards/deals` | `entity == 2` | Create first/next deal `{ title, quantity }` — fails if an **active** deal exists |
| POST | `/rewards/deals/renew` | `entity == 2` | New deal `{ title?, quantity }` — only if current is exhausted or paused |
| POST | `/rewards/deals/pause` | `entity == 2` | Pause the active deal |
| POST | `/rewards/scan` | `entity == 2` | `{ token }` — redeem |
| GET | `/rewards/deals/history` | `entity == 2` | Past deals + `redemption_count` |

v1 create/renew activates immediately (`payment_status=waived`). Later: send `amount`, keep deal pending until URWAY confirm (`GET /urway_credentials` + webhook), then set `payment_status=paid` and `status=active`.

## Client QR

```json
{
  "status": true,
  "payload": "cookster_redeem:<signed-token>",
  "expires_in": 60,
  "eligible": true
}
```

Encode only `payload` in the QR. Refresh before `expires_in`. Token is HMAC-signed `{ type, user_id, exp, jti }`. Raw user id is not in the QR string. Expired / bad type / bad sig → `invalid_or_expired_token`.

`eligible` is `true` when logged in. Once-per-deal is enforced at scan against **that partner’s current deal**.

## Scan

Atomic: lock partner + active deal → validate token → reject own QR → insert redemption (unique `(deal_id, client_user_id)`) → decrement → exhaust at 0.

Rate limit: 10 requests / minute / partner.

Success:

```json
{
  "status": true,
  "error_code": "success",
  "deal_id": "...",
  "title": "Coffee",
  "quantity_total": 100,
  "quantity_remaining": 99,
  "status": "active"
}
```

## Error body

```json
{ "status": false, "error_code": "already_redeemed", "message": "..." }
```

| `error_code` | When |
|--------------|------|
| `success` | Redeem / create / renew / pause ok |
| `already_redeemed` | Same client + same deal |
| `deal_exhausted` | Remaining 0 |
| `deal_paused` | Current deal paused |
| `no_active_deal` | No deal, or renew with nothing to replace |
| `invalid_or_expired_token` | Bad/expired QR |
| `not_a_partner` | Caller `entity != 2` |
| `cannot_redeem_own_qr` | Partner scanned their own code |
| `active_deal_exists` | Create/renew while an active deal exists |
| `partner_blocked` | Admin blocked this partner |
| `validation_failed` | Missing title/quantity |

## Partner screens

- No rows → create (`title` + `quantity`)
- `status=active` → remaining / total, Scan, Pause
- `status=exhausted` or `paused` → Renew

Renew inserts a new row. Old row stays for history. Same client can redeem the new deal.

## Admin

`/admin/reward-deals` — list remaining, pause a deal, block/unblock a partner, redemption log. Not in Flutter.
