<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Hold;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'hold_id' => 'required|integer|exists:holds,id',
        ]);

        $holdId = $request->input('hold_id');

        $order = DB::transaction(function() use ($holdId) {
            $hold = Hold::where('id', $holdId)->lockForUpdate()->firstOrFail();

            if ($hold->status !== 'reserved') {
                throw new \Exception('Hold is not valid for order creation');
            }

            if ($hold->expires_at && $hold->expires_at->isPast()) {
                $hold->status = 'expired';
                $hold->save();
                throw new \Exception('Hold has expired');
            }

            $order = Order::create([
                'hold_id' => $hold->id,
                'quantity' => $hold->quantity,
                'total' => $hold->quantity * $hold->product->price,
                'status' => 'pending',
            ]);

            $hold->status = 'attached';
            $hold->save();

            return $order;
        });

        return response()->json([
            'order_id' => $order->id,
            'status' => $order->status,
        ], 201);
    }
}
