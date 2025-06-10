<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
  
class Notification extends Model
{
    use HasFactory;

    // Define the key type as string since it's UUID
    protected $keyType = 'string';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'to_type', 'title', 'text', 'front_user_id'
    ];
    protected $table = 'push_notifications';
}