<?php

namespace Tests\Feature;

use App\Jobs\SendOrderConfirmationJob;
use App\Mail\OrderConfirmationMail;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SendOrderConfirmationJobTest extends TestCase
{
    use RefreshDatabase;

    private int $orderId;

    protected function setUp(): void
    {
        parent::setUp();

        // Stop the job from running inline while the order is placed, so each test
        // starts with an order whose confirmation has not been sent yet.
        Queue::fake();

        $product = Product::factory()->create([
            'name' => 'Product A',
            'code' => 'PRD-A',
            'price' => '50.00',
            'tax_percent' => '8.00',
            'stock_quantity' => 20,
        ]);

        $this->orderId = $this->app->make(OrderService::class)->create([
            'customer' => ['name' => 'John Doe', 'email' => 'john@example.com'],
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ])->id;
    }

    public function test_it_emails_the_customer_and_marks_the_order_as_sent(): void
    {
        Mail::fake();

        $this->assertNull($this->confirmationSentAt());

        $this->runJob($this->orderId);

        Mail::assertSent(OrderConfirmationMail::class, 1);
        Mail::assertSent(OrderConfirmationMail::class, function (OrderConfirmationMail $mail) {
            return $mail->hasTo('john@example.com')
                && $mail->order->id === $this->orderId
                && $mail->hasSubject('Your order #'.$this->orderId.' is confirmed');
        });
        $this->assertNotNull($this->confirmationSentAt());
    }

    public function test_running_the_job_again_does_not_send_a_second_email(): void
    {
        Mail::fake();

        $this->runJob($this->orderId);
        $sentAt = $this->confirmationSentAt();

        $this->travel(2)->hours();

        $this->runJob($this->orderId);

        Mail::assertSent(OrderConfirmationMail::class, 1);
        $this->assertSame($sentAt, $this->confirmationSentAt());
    }

    public function test_a_missing_order_is_logged_and_skipped_without_an_exception(): void
    {
        Mail::fake();
        Log::spy();

        $this->runJob(999999);

        Mail::assertNothingSent();
        Log::shouldHaveReceived('warning')->once();
    }

    public function test_failed_logs_an_error(): void
    {
        Log::spy();

        (new SendOrderConfirmationJob($this->orderId))->failed(new RuntimeException('SMTP is down'));

        Log::shouldHaveReceived('error')->once();
    }

    public function test_the_job_is_queued_with_retries_and_waits_for_commits(): void
    {
        $job = new SendOrderConfirmationJob($this->orderId);

        $this->assertInstanceOf(ShouldQueue::class, $job);
        $this->assertSame($this->orderId, $job->orderId);
        $this->assertSame(3, $job->tries);
        $this->assertTrue($job->afterCommit);
    }

    public function test_the_mail_renders_the_bill_without_touching_unloaded_relations(): void
    {
        // Lazy loading is forbidden, so load exactly what the job loads.
        $order = Order::with(['customer', 'items'])->findOrFail($this->orderId);

        $mail = new OrderConfirmationMail($order);

        $mail->assertSeeInHtml('Product A');
        $mail->assertSeeInHtml('PRD-A');
        $mail->assertSeeInHtml('108.00');
        $mail->assertSeeInText('Product A');
        $mail->assertSeeInText('PRD-A');
        $mail->assertSeeInText('108.00');
    }

    private function runJob(int $orderId): void
    {
        // Through the container so any handle() dependencies are resolved like the queue worker would.
        $this->app->call([new SendOrderConfirmationJob($orderId), 'handle']);
    }

    private function confirmationSentAt(): ?string
    {
        return DB::table('orders')->where('id', $this->orderId)->value('confirmation_sent_at');
    }
}
