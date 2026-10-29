<?php

namespace App\Http\Requests;

class ListProductsRequest extends PaginatedRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return $this->paginationRules();
    }
}
