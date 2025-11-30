<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class Product extends Model
{
    protected $fillable = ['name','price','stock'];

    public function holds()
    {
        return $this->hasMany(Hold::class);
    }

    public function getAvailableStockAttribute()
    {
        $now = Carbon::now();

        $activeHoldsQty = DB::table('holds')
            ->where('product_id', $this->id)
            ->whereIn('status', ['reserved','attached'])
            ->when(Schema::hasColumn('holds','expires_at'), function($q) use ($now) {
                $q->where('expires_at', '>', $now);
            })
            ->sum('quantity');

        $consumedQty = DB::table('orders')
            ->where('hold_id', function($q){
            });

        $paidQty = DB::table('orders')
            ->join('holds', 'orders.hold_id', '=', 'holds.id')
            ->where('holds.product_id', $this->id)
            ->where('orders.status', 'paid')
            ->sum('orders.quantity');

        return max(0, $this->stock - $activeHoldsQty - $paidQty);
    }
}
