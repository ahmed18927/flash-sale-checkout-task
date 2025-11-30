<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Hold;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HoldController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $productId = $request->input('product_id');
        $quantity = (int)$request->input('quantity');

        $hold = DB::transaction(function() use ($productId, $quantity) {
            $product = Product::where('id', $productId)->lockForUpdate()->firstOrFail();

            $now = Carbon::now();
            $activeHoldsQty = DB::table('holds')
                ->where('product_id', $productId)
                ->whereIn('status', ['reserved','attached'])
                ->where('expires_at', '>', $now)
                ->sum('quantity');

            $paidQty = DB::table('orders')
                ->join('holds', 'orders.hold_id', '=', 'holds.id')
                ->where('holds.product_id', $productId)
                ->where('orders.status', 'paid')
                ->sum('orders.quantity');

            $available = max(0, $product->stock - $activeHoldsQty - $paidQty);

            if ($quantity > $available) {
                throw new \Exception('not enough stock available');
            }

            $expiresAt = Carbon::now()->addMinutes(2);

            return Hold::create([
                'product_id' => $productId,
                'quantity' => $quantity,
                'status' => 'reserved',
                'expires_at' => $expiresAt,
            ]);
        });

        return response()->json([
            'hold_id' => $hold->id,
            'expires_at' => $hold->expires_at->toDateTimeString(),
        ], 201);
    }
}
