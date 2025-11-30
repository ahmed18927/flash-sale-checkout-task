<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $data = $request->validate([
            'idempotency_key' => 'required|string',
            'order_id' => 'required|integer|exists:orders,id',
            'status' => 'required|string|in:success,failure',
        ]);

        $key = $data['idempotency_key'];
        $orderId = $data['order_id'];
        $status = $data['status'];

        try {
            DB::beginTransaction();

            $existing = WebhookLog::where('idempotency_key', $key)->first();
            if ($existing) {
                DB::commit();
                return response()->json(['message' => 'already processed'], 200);
            }

            WebhookLog::create([
                'idempotency_key' => $key,
                'order_id' => $orderId,
                'payload' => $request->all(),
                'processed_at' => Carbon::now(),
            ]);

            $order = Order::where('id', $orderId)->lockForUpdate()->firstOrFail();

            if ($order->status === 'paid' && $status === 'success') {
                DB::commit();
                return response()->json(['message' => 'already paid'], 200);
            }

            if ($status === 'success') {
                $order->status = 'paid';
                $order->paid_at = Carbon::now();
                $order->save();

                $hold = $order->hold()->lockForUpdate()->first();
                if ($hold) {
                    $hold->status = 'consumed';
                    $hold->save();
                }
            } else {
                $order->status = 'cancelled';
                $order->save();

                $hold = $order->hold()->lockForUpdate()->first();
                if ($hold) {
                    $hold->status = 'released';
                    $hold->save();
                }
            }

            DB::commit();
            return response()->json(['message' => 'processed'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('webhook processing failed', ['error' => $e->getMessage(), 'payload' => $request->all()]);
            return response()->json(['message' => 'processing failed','error' => $e->getMessage()], 500);
        }
    }
}
