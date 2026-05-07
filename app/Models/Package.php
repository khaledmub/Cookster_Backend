<?php
  
namespace App\Models;
  
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
  
class Package extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    // Use UUIDs for primary keys
    protected $keyType = 'string'; // Ensure that the key type is string
    public $incrementing = false;  // Disable auto-incrementing IDs
    protected $fillable = [
        'id', 'amount', 'duration', 'status'
    ];
}