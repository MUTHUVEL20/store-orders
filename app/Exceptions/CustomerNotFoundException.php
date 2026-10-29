<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CustomerNotFoundException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No customer found with the given email address.');
    }

    /**
     * An unknown email is a normal client error, so it is not written to the log.
     */
    public function report(): void
    {
        //
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => 'customer_not_found',
        ], 404);
    }
}
