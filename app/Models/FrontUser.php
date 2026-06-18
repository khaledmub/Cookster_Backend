<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class FrontUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    // Use UUIDs for primary keys
    protected $keyType = 'string'; // Ensure that the key type is string
    public $incrementing = false;  // Disable auto-incrementing IDs

    protected $fillable = [
        'id',
        'name',
        'user_name',
        'email',
        'email_verified_at',
        'phone',
        'password',
        'dob',
        'image',
        'cover_image',
        'country',
        'state',
        'city',
        'entity',
        'uuid',
        'is_soft_delete',
        'sd_email',
        'current_subscription_id',
        'total_loyalty_points',
        'total_outstanding_balance',
        'is_one_time_discount_given',
        'status',
    ];

    protected $hidden = [
        'password'
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}
