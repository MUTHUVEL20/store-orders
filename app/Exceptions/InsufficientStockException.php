<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    /**
     * @param  list<array{product_id: int, code: ?string, name: ?string, requested: int, available: int}>  $shortages
     */
    public function __construct(public readonly array $shortages)
    {
        parent::__construct('Insufficient stock for one or more products.');
    }

    /**
     * Running out of stock is an expected business outcome, not an application error,
     * so it is not written to the log.
     */
    public function report(): void
    {
        //
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'insufficient_stock',
            'shortages' => $this->shortages,
        ], 409);
    }
}
