<?php

/*
 * Worker process for ConcurrentOrderTest. Not a test itself: the parent test starts
 * several copies of this script as separate PHP processes so that their database
 * transactions genuinely overlap.
 *
 * Usage:  php worker.php <work-dir> <index>
 *
 * Protocol (all files live in <work-dir>):
 *   payload-<index>.json  written by the parent: the OrderService::create() input
 *   ready-<index>         created here once the app is booted and the DB connected
 *   go                    created by the parent once every worker is ready
 *
 * Exactly one JSON line is printed on stdout:
 *   {"status":"created","order_id":123}
 *   {"status":"insufficient_stock"}
 *   {"status":"error","message":"..."}
 */

use App\Exceptions\InsufficientStockException;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;

// Keep PHP warnings off stdout, which is reserved for the JSON result.
ini_set('display_errors', 'stderr');

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

function emit_result(array $result): void
{
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE).PHP_EOL;
}

$workDir = $argv[1] ?? null;
$index = $argv[2] ?? null;

if ($workDir === null || $index === null) {
    emit_result(['status' => 'error', 'message' => 'Usage: php worker.php <work-dir> <index>']);
    exit(2);
}

try {
    $payload = json_decode(
        (string) file_get_contents($workDir.DIRECTORY_SEPARATOR.'payload-'.$index.'.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();

    // Connect now, so the race below is about row locks and not about connection set-up time.
    $app->make('db')->connection()->getPdo();

    touch($workDir.DIRECTORY_SEPARATOR.'ready-'.$index);

    $goFile = $workDir.DIRECTORY_SEPARATOR.'go';
    $deadline = microtime(true) + 60;

    while (! is_file($goFile)) {
        if (microtime(true) > $deadline) {
            throw new RuntimeException('Timed out waiting for the start signal.');
        }

        usleep(500);
    }

    try {
        $order = $app->make(OrderService::class)->create($payload);

        emit_result(['status' => 'created', 'order_id' => $order->id]);
    } catch (InsufficientStockException) {
        emit_result(['status' => 'insufficient_stock']);
    }
} catch (Throwable $e) {
    emit_result(['status' => 'error', 'message' => get_class($e).': '.$e->getMessage()]);
    exit(1);
}
