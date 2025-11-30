<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = ['hold_id','quantity','total','status','paid_at'];
    protected $dates = ['paid_at'];

    public function hold()
    {
        return $this->belongsTo(Hold::class, 'hold_id');
    }

    public function markPaid()
    {
        $this->status = 'paid';
        $this->paid_at = now();
        $this->save();
    }

    public function markCancelled()
    {
        $this->status = 'cancelled';
        $this->save();
    }
}
