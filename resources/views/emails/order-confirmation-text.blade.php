Thank you for your order, {!! $order->customer->name !!}!

Your order #{{ $order->id }} placed on {{ $order->created_at->format('d M Y, H:i T') }} is confirmed.

Items
-----
@foreach ($order->items as $item)
* {!! $item->product_name !!} ({!! $item->product_code !!})
  {{ $item->quantity }} x ₹{{ $item->unit_price }} + {{ $item->tax_percent }}% tax = ₹{{ $item->line_total }}
@endforeach

Subtotal:    ₹{{ $order->subtotal }}
Tax:         ₹{{ $order->tax_total }}
Grand total: ₹{{ $order->grand_total }}

Prices are in Indian Rupees. Unit prices exclude tax; tax is added per line.
This is an automated confirmation sent to {!! $order->customer->email !!}.
{{-- text/plain part: {!! !!} is deliberate. HTML-escaping would turn O'Brien into O&#039;Brien, and there is no HTML context to inject into. --}}
