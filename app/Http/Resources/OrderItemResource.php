<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exposes the snapshot taken when the order was placed, never the live product row.
 *
 * @mixin \App\Models\OrderItem
 */
class OrderItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'product_id' => $this->product_id,
            'product_code' => $this->product_code,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'tax_percent' => $this->tax_percent,
            'line_subtotal' => $this->line_subtotal,
            'line_tax' => $this->line_tax,
            'line_total' => $this->line_total,
        ];
    }
}
