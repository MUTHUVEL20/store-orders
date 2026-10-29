<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Relations are only included when eager loaded, so the history endpoint
 * (items, no customer) and the create endpoint (both) share this resource.
 *
 * @mixin \App\Models\Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'subtotal' => $this->subtotal,
            'tax' => $this->tax_total,
            'grand_total' => $this->grand_total,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
