<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RewardDeal extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'reward_deals';

    protected $guarded = [];

    protected $casts = [
        'quantity_total' => 'integer',
        'quantity_remaining' => 'integer',
        'amount' => 'float',
        'expires_at' => 'datetime',
    ];

    public function partner(): BelongsTo
    {
        return $this->belongsTo(FrontUser::class, 'partner_user_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(RewardRedemption::class, 'deal_id');
    }
}
