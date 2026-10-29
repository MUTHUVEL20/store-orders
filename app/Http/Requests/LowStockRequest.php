<?php

namespace App\Http\Requests;

class LowStockRequest extends PaginatedRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge([
            'threshold' => ['nullable', 'integer', 'min:0', 'max:1000000'],
        ], $this->paginationRules());
    }

    public function threshold(): int
    {
        return (int) ($this->validated('threshold') ?? config('inventory.low_stock_threshold'));
    }
}
