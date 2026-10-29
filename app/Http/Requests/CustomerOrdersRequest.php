<?php

namespace App\Http\Requests;

use App\Models\Customer;

class CustomerOrdersRequest extends PaginatedRequest
{
    protected int $defaultPerPage = 15;

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Customer::normalizeEmail($email)]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge([
            'email' => ['required', 'string', 'email', 'max:255'],
        ], $this->paginationRules());
    }

    public function messages(): array
    {
        return [
            'email.required' => 'The customer email address is required.',
            'email.email' => 'Enter a valid customer email address.',
        ];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }
}
