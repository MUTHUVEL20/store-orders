<?php

namespace App\Http\Requests;

use App\Models\Customer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    private ?bool $existingCustomer = null;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $customer = $this->input('customer');

        if (is_array($customer) && is_string($customer['email'] ?? null)) {
            $customer['email'] = Customer::normalizeEmail($customer['email']);
            $this->merge(['customer' => $customer]);
        }
    }

    /**
     * Prices, tax and totals are deliberately absent: the server always computes them.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer' => ['required', 'array'],
            'customer.email' => ['required', 'string', 'email', 'max:255'],
            // The name is only needed to create a customer; an existing one keeps the stored name.
            'customer.name' => [
                Rule::requiredIf(fn (): bool => ! $this->customerExists()),
                'nullable',
                'string',
                'max:255',
            ],
            'items' => ['required', 'array', 'min:1', 'max:'.config('inventory.max_lines_per_order')],
            'items.*.product_id' => ['bail', 'required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.quantity' => ['bail', 'required', 'integer', 'min:1', 'max:'.config('inventory.max_quantity_per_line')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer.required' => 'Customer details are required.',
            'customer.email.required' => 'The customer email address is required.',
            'customer.email.email' => 'Enter a valid customer email address.',
            'customer.name.required' => 'The customer name is required for a new customer.',
            'items.required' => 'Add at least one product to the order.',
            'items.min' => 'Add at least one product to the order.',
            'items.max' => 'An order can contain at most :max different products.',
            'items.*.product_id.required' => 'Each line needs a product.',
            'items.*.product_id.distinct' => 'Each product can appear only once per order; combine the quantities instead.',
            'items.*.product_id.exists' => 'One of the selected products does not exist.',
            'items.*.quantity.required' => 'Each line needs a quantity.',
            'items.*.quantity.integer' => 'Quantity must be a whole number.',
            'items.*.quantity.min' => 'Quantity must be at least :min.',
            'items.*.quantity.max' => 'Quantity cannot exceed :max per product.',
        ];
    }

    /**
     * The validated order payload in the shape OrderService expects (typed ints, no extra keys).
     *
     * @return array{customer: array{name: ?string, email: string}, items: list<array{product_id: int, quantity: int}>}
     */
    public function orderData(): array
    {
        $data = $this->validated();

        return [
            'customer' => [
                'name' => $data['customer']['name'] ?? null,
                'email' => $data['customer']['email'],
            ],
            'items' => array_map(
                fn (array $item): array => [
                    'product_id' => (int) $item['product_id'],
                    'quantity' => (int) $item['quantity'],
                ],
                array_values($data['items']),
            ),
        ];
    }

    private function customerExists(): bool
    {
        if ($this->existingCustomer === null) {
            $email = $this->input('customer.email');

            $this->existingCustomer = is_string($email)
                && Customer::query()->where('email', Customer::normalizeEmail($email))->exists();
        }

        return $this->existingCustomer;
    }
}
