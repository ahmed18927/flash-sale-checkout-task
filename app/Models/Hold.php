<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Carbon\Carbon;

class Hold extends Model
{
    use HasFactory;

    protected $fillable = ['product_id','quantity','status','expires_at'];
    protected $dates = ['expires_at'];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    protected $casts = [
    'expires_at' => 'datetime',
    ];

    public function order()
    {
        return $this->hasOne(Order::class, 'hold_id');
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['reserved','attached']) && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    public function markExpired()
    {
        $this->status = 'expired';
        $this->save();
    }
}
