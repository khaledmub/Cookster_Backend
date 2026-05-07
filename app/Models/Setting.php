<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
  
class Setting extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = [
        'email', 'phone', 'address', 'facebook', 'twitter', 'instagram', 'linkedin', 'basic_sponsored_video_price', 'premium_sponsored_video_price', 'sponsor_video_discount', 'allow_general_videos', 'currency_symbol', 'play_store_link', 'app_store_link', 'allow_following_videos', 'tiktok', 'snapchat', 'first_loyalty_points', 'loyalty_points', 'loyalty_points_exchange_rate', 'loyalty_points_status', 'allow_one_time_qr_reward'
    ];
}