<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RewardDealService
{
    public const ENTITY_BUSINESS = 2;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXHAUSTED = 'exhausted';

    public const STATUS_PAUSED = 'paused';

    public const PAYMENT_WAIVED = 'waived';

    /**
     * @param  object{id: mixed, entity: mixed}  $user
     */
    public function requirePartner(object $user): void
    {
        if ((int) ($user->entity ?? 0) !== self::ENTITY_BUSINESS) {
            throw new RewardDealException('not_a_partner');
        }

        if ($this->isBlocked((string) $user->id)) {
            throw new RewardDealException('partner_blocked');
        }
    }

    public function isBlocked(string $partnerUserId): bool
    {
        $blockedAt = DB::table('reward_partner_settings')
            ->where('partner_user_id', $partnerUserId)
            ->value('blocked_at');

        return $blockedAt !== null;
    }

    public function activeDeal(string $partnerUserId): ?object
    {
        return DB::table('reward_deals')
            ->where('partner_user_id', $partnerUserId)
            ->where('status', self::STATUS_ACTIVE)
            ->orderByDesc('created_at')
            ->first();
    }

    public function latestDeal(string $partnerUserId): ?object
    {
        return DB::table('reward_deals')
            ->where('partner_user_id', $partnerUserId)
            ->orderByDesc('created_at')
            ->first();
    }

    /**
     * @return list<object>
     */
    public function history(string $partnerUserId): array
    {
        $deals = DB::table('reward_deals')
            ->where('partner_user_id', $partnerUserId)
            ->orderByDesc('created_at')
            ->get();

        if ($deals->isEmpty()) {
            return [];
        }

        $counts = DB::table('reward_redemptions')
            ->whereIn('deal_id', $deals->pluck('id'))
            ->select('deal_id', DB::raw('COUNT(*) as redemption_count'))
            ->groupBy('deal_id')
            ->pluck('redemption_count', 'deal_id');

        return $deals->map(function ($deal) use ($counts) {
            $deal->redemption_count = (int) ($counts[$deal->id] ?? 0);

            return $deal;
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function create(string $partnerUserId, string $title, int $quantity): array
    {
        return DB::transaction(function () use ($partnerUserId, $title, $quantity) {
            $this->lockPartnerRow($partnerUserId);

            if ($this->isBlocked($partnerUserId)) {
                throw new RewardDealException('partner_blocked');
            }

            $active = $this->lockActiveDeal($partnerUserId);
            if ($active !== null) {
                throw new RewardDealException('active_deal_exists');
            }

            return $this->insertDeal($partnerUserId, $title, $quantity);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function renew(string $partnerUserId, ?string $title, int $quantity): array
    {
        return DB::transaction(function () use ($partnerUserId, $title, $quantity) {
            $this->lockPartnerRow($partnerUserId);

            if ($this->isBlocked($partnerUserId)) {
                throw new RewardDealException('partner_blocked');
            }

            $active = $this->lockActiveDeal($partnerUserId);
            if ($active !== null) {
                throw new RewardDealException('active_deal_exists');
            }

            $latest = $this->latestDeal($partnerUserId);
            if ($latest === null) {
                throw new RewardDealException('no_active_deal');
            }

            if (! in_array($latest->status, [self::STATUS_EXHAUSTED, self::STATUS_PAUSED], true)) {
                throw new RewardDealException('active_deal_exists');
            }

            $nextTitle = $title !== null && $title !== '' ? $title : (string) $latest->title;

            return $this->insertDeal($partnerUserId, $nextTitle, $quantity);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function pause(string $partnerUserId): array
    {
        return DB::transaction(function () use ($partnerUserId) {
            $this->lockPartnerRow($partnerUserId);

            if ($this->isBlocked($partnerUserId)) {
                throw new RewardDealException('partner_blocked');
            }

            $active = $this->lockActiveDeal($partnerUserId);
            if ($active === null) {
                $latest = $this->latestDeal($partnerUserId);
                if ($latest !== null && $latest->status === self::STATUS_PAUSED) {
                    throw new RewardDealException('deal_paused');
                }

                throw new RewardDealException('no_active_deal');
            }

            DB::table('reward_deals')->where('id', $active->id)->update([
                'status' => self::STATUS_PAUSED,
                'updated_at' => now(),
            ]);

            $active->status = self::STATUS_PAUSED;

            return $this->dealArray($active);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function scan(string $partnerUserId, string $rawToken): array
    {
        return DB::transaction(function () use ($partnerUserId, $rawToken) {
            $this->lockPartnerRow($partnerUserId);

            if ($this->isBlocked($partnerUserId)) {
                throw new RewardDealException('partner_blocked');
            }

            $claims = RewardQrToken::parse($rawToken);
            if ($claims === null) {
                throw new RewardDealException('invalid_or_expired_token');
            }

            $clientId = $claims['user_id'];
            if ($clientId === $partnerUserId) {
                throw new RewardDealException('cannot_redeem_own_qr');
            }

            $clientExists = DB::table('front_users')
                ->where('id', $clientId)
                ->where('is_soft_delete', 0)
                ->exists();
            if (! $clientExists) {
                throw new RewardDealException('invalid_or_expired_token');
            }

            $deal = $this->lockActiveDeal($partnerUserId);
            if ($deal === null) {
                $latest = $this->latestDeal($partnerUserId);
                if ($latest !== null && $latest->status === self::STATUS_PAUSED) {
                    throw new RewardDealException('deal_paused');
                }
                if ($latest !== null && $latest->status === self::STATUS_EXHAUSTED) {
                    throw new RewardDealException('deal_exhausted');
                }

                throw new RewardDealException('no_active_deal');
            }

            if ((int) $deal->quantity_remaining <= 0) {
                throw new RewardDealException('deal_exhausted');
            }

            try {
                DB::table('reward_redemptions')->insert([
                    'id' => (string) Str::uuid(),
                    'deal_id' => $deal->id,
                    'client_user_id' => $clientId,
                    'partner_user_id' => $partnerUserId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new RewardDealException('already_redeemed');
            } catch (QueryException $e) {
                if ((string) $e->getCode() === '23000') {
                    throw new RewardDealException('already_redeemed');
                }

                throw $e;
            }

            $remaining = (int) $deal->quantity_remaining - 1;
            $status = $remaining <= 0 ? self::STATUS_EXHAUSTED : self::STATUS_ACTIVE;

            DB::table('reward_deals')->where('id', $deal->id)->update([
                'quantity_remaining' => $remaining,
                'status' => $status,
                'updated_at' => now(),
            ]);

            return [
                'error_code' => 'success',
                'deal_id' => $deal->id,
                'title' => $deal->title,
                'quantity_total' => (int) $deal->quantity_total,
                'quantity_remaining' => $remaining,
                'status' => $status,
                'client_user_id' => $clientId,
            ];
        });
    }

    public function adminPauseDeal(string $dealId): bool
    {
        $updated = DB::table('reward_deals')
            ->where('id', $dealId)
            ->where('status', self::STATUS_ACTIVE)
            ->update([
                'status' => self::STATUS_PAUSED,
                'updated_at' => now(),
            ]);

        return $updated > 0;
    }

    public function setPartnerBlocked(string $partnerUserId, bool $blocked): void
    {
        DB::table('reward_partner_settings')->updateOrInsert(
            ['partner_user_id' => $partnerUserId],
            [
                'blocked_at' => $blocked ? now() : null,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function dealArray(object $deal): array
    {
        return [
            'id' => $deal->id,
            'partner_user_id' => $deal->partner_user_id,
            'title' => $deal->title,
            'quantity_total' => (int) $deal->quantity_total,
            'quantity_remaining' => (int) $deal->quantity_remaining,
            'status' => $deal->status,
            'created_at' => $deal->created_at,
            'updated_at' => $deal->updated_at ?? null,
        ];
    }

    private function lockPartnerRow(string $partnerUserId): void
    {
        $row = DB::table('reward_partner_settings')
            ->where('partner_user_id', $partnerUserId)
            ->lockForUpdate()
            ->first();

        if ($row !== null) {
            return;
        }

        try {
            DB::table('reward_partner_settings')->insert([
                'partner_user_id' => $partnerUserId,
                'blocked_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException|QueryException) {
            // Concurrent insert — lock the winner.
        }

        DB::table('reward_partner_settings')
            ->where('partner_user_id', $partnerUserId)
            ->lockForUpdate()
            ->first();
    }

    private function lockActiveDeal(string $partnerUserId): ?object
    {
        return DB::table('reward_deals')
            ->where('partner_user_id', $partnerUserId)
            ->where('status', self::STATUS_ACTIVE)
            ->orderByDesc('created_at')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function insertDeal(string $partnerUserId, string $title, int $quantity): array
    {
        $now = now();
        $id = (string) Str::uuid();

        DB::table('reward_deals')->insert([
            'id' => $id,
            'partner_user_id' => $partnerUserId,
            'title' => $title,
            'quantity_total' => $quantity,
            'quantity_remaining' => $quantity,
            'status' => self::STATUS_ACTIVE,
            'amount' => null,
            'payment_ref' => null,
            'payment_status' => self::PAYMENT_WAIVED,
            'image' => null,
            'expires_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return [
            'id' => $id,
            'partner_user_id' => $partnerUserId,
            'title' => $title,
            'quantity_total' => $quantity,
            'quantity_remaining' => $quantity,
            'status' => self::STATUS_ACTIVE,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ];
    }
}
