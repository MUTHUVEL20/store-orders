<?php

namespace App\Jobs;

use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendOrderConfirmationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Only the order id is queued (not the model) so the job always works with fresh data.
     */
    public function __construct(public int $orderId)
    {
        // Never let a worker see this job before the order's transaction has committed.
        // (Set through Queueable's method: the trait already declares $afterCommit, so
        // redeclaring the property here with another default would be a fatal error.)
        $this->afterCommit();
    }

    /**
     * Seconds to wait before the 2nd and 3rd attempt.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function handle(): void
    {
        // Lazy loading is forbidden, so load everything the mail needs up front.
        $order = Order::with(['customer', 'items'])->find($this->orderId);

        if ($order === null) {
            // A permanent condition: retrying will not make the order appear.
            Log::warning('Order confirmation skipped: order not found.', ['order_id' => $this->orderId]);

            return;
        }

        // Idempotency guard: a retry or duplicate delivery must not email the customer twice.
        if ($order->confirmation_sent_at !== null) {
            Log::info('Order confirmation already sent, skipping.', ['order_id' => $order->id]);

            return;
        }

        Mail::to($order->customer->email, $order->customer->name)
            ->send(new OrderConfirmationMail($order));

        $order->update(['confirmation_sent_at' => now()]);

        Log::info('Order confirmation email sent', [
            'order_id' => $order->id,
            'email' => $order->customer->email,
            'grand_total' => $order->grand_total,
        ]);
    }

    /**
     * Called once all attempts are exhausted.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('Order confirmation email failed permanently.', [
            'order_id' => $this->orderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
