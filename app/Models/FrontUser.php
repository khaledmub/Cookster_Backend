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
        'email',
        'phone',
        'password',
        'dob',
        'image',
        'country',
        'state',
        'city',
        'entity',
        'uuid',
        'is_soft_delete',
        'sd_email',
    ];

    protected $hidden = [
        'password'
    ];
}
