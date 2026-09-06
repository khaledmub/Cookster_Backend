<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RewardPartnerSetting extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $primaryKey = 'partner_user_id';

    protected $table = 'reward_partner_settings';

    protected $guarded = [];

    protected $casts = [
        'blocked_at' => 'datetime',
    ];
}
