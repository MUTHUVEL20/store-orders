<?php

namespace Tests\Concurrency;

use App\Models\Product;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * Race-condition tests that run real, separate PHP processes against MySQL/MariaDB.
 *
 * These are the only tests in the suite that exercise genuine database concurrency:
 * every worker is its own PHP process with its own connection, released at the same
 * moment by a file-based barrier. The Feature tests verify the transaction, rollback
 * and locking code paths on a single connection only; they cannot prove that two
 * buyers racing for the last unit of stock are serialised correctly.
 *
 * Because transactions must be visible across processes, this class does NOT use
 * RefreshDatabase: it migrates the (dedicated, "_test") database fresh and truncates
 * the tables again afterwards. Run it on its own with:
 *
 *     php artisan test --testsuite=Concurrency
 */
#[Group('concurrency')]
class ConcurrentOrderTest extends TestCase
{
    private bool $databaseIsMigrated = false;

    private string $workDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('The concurrency tests need a real MySQL/MariaDB connection.');
        }

        $this->assertSame(0, Artisan::call('migrate:fresh', ['--force' => true]), 'migrate:fresh failed.');
        $this->databaseIsMigrated = true;

        $this->workDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'store-orders-'.bin2hex(random_bytes(6));
        mkdir($this->workDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if ($this->databaseIsMigrated) {
            Schema::disableForeignKeyConstraints();

            foreach (['order_items', 'orders', 'customers', 'products', 'jobs'] as $table) {
                DB::table($table)->truncate();
            }

            Schema::enableForeignKeyConstraints();
        }

        $this->removeWorkDir();

        parent::tearDown();
    }

    public function test_two_buyers_racing_for_the_last_unit_produce_exactly_one_order(): void
    {
        $product = $this->product(stock: 1);

        $results = $this->runConcurrently([
            $this->orderFor('buyer-0@example.com', [[$product, 1]]),
            $this->orderFor('buyer-1@example.com', [[$product, 1]]),
        ]);

        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame(['created', 'insufficient_stock'], $statuses);
        $this->assertSame(0, $this->stockOf($product));
        $this->assertSame(1, DB::table('orders')->count());
        $this->assertSame(1, DB::table('order_items')->count());
        $this->assertSame(1, DB::table('jobs')->count());
        // The buyer who lost the race must not leave a customer row behind.
        $this->assertSame(1, DB::table('customers')->count());
    }

    public function test_eight_buyers_cannot_oversell_ten_units(): void
    {
        $product = $this->product(stock: 10);

        $payloads = [];

        for ($i = 0; $i < 8; $i++) {
            $payloads[] = $this->orderFor("buyer-{$i}@example.com", [[$product, 2]]);
        }

        $results = $this->runConcurrently($payloads);

        $counts = array_count_values(array_column($results, 'status'));

        $this->assertSame(5, $counts['created'] ?? 0);
        $this->assertSame(3, $counts['insufficient_stock'] ?? 0);
        $this->assertSame(0, $this->stockOf($product));
        $this->assertSame(5, DB::table('orders')->count());
        $this->assertSame(10, (int) DB::table('order_items')->sum('quantity'));
        $this->assertSame(5, DB::table('jobs')->count());
    }

    public function test_orders_listing_the_same_products_in_opposite_order_do_not_deadlock(): void
    {
        $first = $this->product(stock: 100);
        $second = $this->product(stock: 100);

        $results = $this->runConcurrently([
            $this->orderFor('buyer-0@example.com', [[$first, 2], [$second, 3]]),
            $this->orderFor('buyer-1@example.com', [[$first, 2], [$second, 3]]),
            $this->orderFor('buyer-2@example.com', [[$second, 1], [$first, 4]]),
            $this->orderFor('buyer-3@example.com', [[$second, 1], [$first, 4]]),
        ]);

        $this->assertSame(['created', 'created', 'created', 'created'], array_column($results, 'status'));
        $this->assertSame(100 - (2 + 2 + 4 + 4), $this->stockOf($first));
        $this->assertSame(100 - (3 + 3 + 1 + 1), $this->stockOf($second));
        $this->assertSame(4, DB::table('orders')->count());
        $this->assertSame(8, DB::table('order_items')->count());
    }

    public function test_concurrent_orders_from_the_same_new_email_create_a_single_customer(): void
    {
        $products = [$this->product(stock: 5), $this->product(stock: 5), $this->product(stock: 5)];

        $payloads = [];

        foreach ($products as $product) {
            $payloads[] = $this->orderFor('shared.buyer@example.com', [[$product, 1]]);
        }

        $results = $this->runConcurrently($payloads);

        $this->assertSame(['created', 'created', 'created'], array_column($results, 'status'));
        $this->assertSame(1, DB::table('customers')->count());
        $this->assertSame(3, DB::table('orders')->count());
        $this->assertSame(1, DB::table('orders')->pluck('customer_id')->unique()->count());
        foreach ($products as $product) {
            $this->assertSame(4, $this->stockOf($product));
        }
    }

    // ------------------------------------------------------------------
    // Harness
    // ------------------------------------------------------------------

    /**
     * Start one worker process per payload, wait until all of them are booted and
     * connected, release them together and return their decoded results in payload order.
     *
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array<string, mixed>>
     */
    private function runConcurrently(array $payloads): array
    {
        $payloads = array_values($payloads);

        // Payloads travel through files: no shell quoting issues, on Windows or elsewhere.
        foreach ($payloads as $index => $payload) {
            file_put_contents(
                $this->workDir.DIRECTORY_SEPARATOR.'payload-'.$index.'.json',
                json_encode($payload, JSON_THROW_ON_ERROR),
            );
        }

        $processes = [];

        foreach (array_keys($payloads) as $index) {
            $process = new Process(
                [PHP_BINARY, __DIR__.DIRECTORY_SEPARATOR.'worker.php', $this->workDir, (string) $index],
                base_path(),
                $this->workerEnvironment(),
                null,
                180,
            );
            $process->start();

            $processes[$index] = $process;
        }

        $this->waitUntilReady($processes);

        touch($this->workDir.DIRECTORY_SEPARATOR.'go');

        $results = [];

        foreach ($processes as $index => $process) {
            $process->wait();

            $results[$index] = $this->decodeResult($index, $process);
        }

        $errors = [];

        foreach ($results as $index => $result) {
            if ($result['status'] === 'error') {
                $errors[] = "worker {$index}: ".($result['message'] ?? 'unknown error');
            }
        }

        $this->assertSame([], $errors, 'One or more workers failed with an error.');

        return $results;
    }

    /**
     * @param  array<int, Process>  $processes
     */
    private function waitUntilReady(array $processes): void
    {
        $deadline = microtime(true) + 60;

        do {
            $pending = [];

            foreach ($processes as $index => $process) {
                if (is_file($this->workDir.DIRECTORY_SEPARATOR.'ready-'.$index)) {
                    continue;
                }

                if (! $process->isRunning()) {
                    $this->stopAll($processes);

                    $this->fail(sprintf(
                        "Worker %d exited before it was ready (exit code %s).\nstdout: %s\nstderr: %s",
                        $index,
                        (string) $process->getExitCode(),
                        $process->getOutput(),
                        $process->getErrorOutput(),
                    ));
                }

                $pending[] = $index;
            }

            if ($pending === []) {
                return;
            }

            usleep(10000);
        } while (microtime(true) < $deadline);

        $this->stopAll($processes);

        $this->fail('Timed out waiting for workers to become ready: '.implode(', ', $pending));
    }

    /**
     * @param  array<int, Process>  $processes
     */
    private function stopAll(array $processes): void
    {
        foreach ($processes as $process) {
            $process->stop(0);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeResult(int $index, Process $process): array
    {
        $lines = array_values(array_filter(
            array_map('trim', explode("\n", $process->getOutput())),
            fn (string $line) => $line !== '',
        ));

        $decoded = $lines === [] ? null : json_decode($lines[count($lines) - 1], true);

        if (! is_array($decoded) || ! isset($decoded['status'])) {
            $this->fail(sprintf(
                "Worker %d did not print a JSON result (exit code %s).\nstdout: %s\nstderr: %s",
                $index,
                (string) $process->getExitCode(),
                $process->getOutput(),
                $process->getErrorOutput(),
            ));
        }

        return $decoded;
    }

    /**
     * Explicit environment for the children, so they use exactly this test database
     * no matter what the surrounding shell or a developer's .env says.
     *
     * @return array<string, string>
     */
    private function workerEnvironment(): array
    {
        $database = (array) config('database.connections.mysql');

        return [
            'APP_ENV' => 'testing',
            'APP_KEY' => (string) config('app.key'),
            'APP_MAINTENANCE_DRIVER' => 'file',
            'BCRYPT_ROUNDS' => '4',
            'DB_CONNECTION' => 'mysql',
            'DB_HOST' => (string) ($database['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($database['port'] ?? '3306'),
            'DB_DATABASE' => (string) ($database['database'] ?? ''),
            'DB_USERNAME' => (string) ($database['username'] ?? ''),
            'DB_PASSWORD' => (string) ($database['password'] ?? ''),
            'QUEUE_CONNECTION' => 'database',
            'MAIL_MAILER' => 'array',
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'LOG_CHANNEL' => 'null',
        ];
    }

    private function removeWorkDir(): void
    {
        if ($this->workDir === '' || ! is_dir($this->workDir)) {
            return;
        }

        foreach (glob($this->workDir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->workDir);
    }

    private function product(int $stock): Product
    {
        return Product::factory()->create([
            'price' => '10.00',
            'tax_percent' => '8.00',
            'stock_quantity' => $stock,
        ]);
    }

    private function stockOf(Product $product): int
    {
        return (int) DB::table('products')->where('id', $product->id)->value('stock_quantity');
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines  [product, quantity] pairs
     * @return array<string, mixed>
     */
    private function orderFor(string $email, array $lines): array
    {
        return [
            'customer' => ['name' => 'Concurrent Buyer', 'email' => $email],
            'items' => array_map(
                fn (array $line) => ['product_id' => $line[0]->id, 'quantity' => $line[1]],
                $lines,
            ),
        ];
    }
}
