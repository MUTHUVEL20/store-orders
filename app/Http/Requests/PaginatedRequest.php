<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

abstract class PaginatedRequest extends FormRequest
{
    protected int $defaultPerPage = 50;

    public function authorize(): bool
    {
        return true;
    }

    public function perPage(): int
    {
        return (int) ($this->validated('per_page') ?? $this->defaultPerPage);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function paginationRules(): array
    {
        return [
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
