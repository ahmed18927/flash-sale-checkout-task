This project implements a high-concurrency flash-sale checkout system using Laravel 12 and MySQL (InnoDB).
It ensures correct stock handling under heavy parallel requests, supports temporary holds, safe order creation, and an idempotent payment webhook.

The implementation strictly avoids overselling, handles race conditions using MySQL row-level locking, and guarantees consistent final order state even with duplicate or out-of-order webhooks.

How to Run

Clone the repository:

git clone <repo-url>
cd flash-sale-checkout


Install dependencies:

composer install


Configure environment:

cp .env.example .env


Set database credentials.

Run migrations and seed:

php artisan migrate --seed


Start the server:

php artisan serve

Seeded Data

The database seeder creates a single product with:

Name

Price

Fixed stock quantity

You can retrieve it using:

GET /api/products/1

API Endpoints (Implemented)
1. Get Product — Accurate Live Availability
GET /api/products/{id}


Returns:

id

name

price

available_stock (calculated from DB, not cached)

Logic:
Availability = product stock − active holds − paid orders
(active holds = status: reserved / attached, not expired)

Controller: ProductController@show

2. Create Hold — Atomic Stock Reservation
POST /api/holds
Body:
{
  "product_id": 1,
  "quantity": 1
}


Returns:

hold_id

expires_at

Key Behaviors:

Uses lockForUpdate() on product row.

Prevents overselling even under parallel load.

Validates active holds + paid quantities.

Hold duration: 2 minutes.

Status set to reserved.

Controller: HoldController@store

3. Create Order — Valid Hold Only
POST /api/orders
Body:
{
  "hold_id": 123
}


Returns:

order_id

status

Rules enforced:

Hold must be reserved.

Hold must not be expired.

Creates order with:

quantity

total = quantity * product price

status = pending

Hold is updated to attached.

Controller: OrderController@store

4. Payment Webhook — Fully Idempotent
POST /api/payments/webhook
Body:
{
  "idempotency_key": "unique-key",
  "order_id": 10,
  "status": "success" | "failure"
}


Idempotency guarantees:

Webhooks can be retried indefinitely.

Duplicate keys are ignored.

Out-of-order arrival is handled safely.

Webhook flow:

Deduped using WebhookLog table.

Order row is locked using lockForUpdate().

On success:

order → paid

hold → consumed

On failure:

order → cancelled

hold → released

Controller: PaymentWebhookController@handle

Concurrency Handling

MySQL row-level locks (lockForUpdate())
Ensures:

No two requests read same stock simultaneously.

Stock is always recalculated inside a DB transaction.

Hold availability calculation:

activeHolds = holds where status in (reserved, attached) and not expired
paidQty = orders where status = paid
available = stock - activeHolds - paidQty


Hold expiry safety:
If an expired hold is used to create an order, it is immediately set to expired.

Webhook correctness:
Even if webhook arrives before order response, database locking ensures final state is correct.

Logs / Debug Info

Application logs:

storage/logs/laravel.log


Contains:

Webhook deduplication logs

Webhook failures

Transaction rollback messages

Assumptions & Invariants

Holds expire after 2 minutes.

A hold can be used exactly once.

Paid orders permanently reduce stock.

Cancelled orders release their holds.

Webhook idempotency is based on a unique idempotency_key per payment attempt.

Stock is never cached, always computed in SQL under locking to avoid stale reads.

Summary

This implementation ensures:

No overselling under high concurrency

Strict hold & order workflow

Safe and idempotent payment processing

Race-condition-free logic using DB transactions

Correct final state regardless of webhook retry or ordering