<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RewardRedemption extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'reward_redemptions';

    protected $guarded = [];

    public function deal(): BelongsTo
    {
        return $this->belongsTo(RewardDeal::class, 'deal_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(FrontUser::class, 'client_user_id');
    }
}
