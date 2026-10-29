<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Order #{{ $order->id }} confirmed</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f5f7; font-family:Arial, Helvetica, sans-serif; color:#1f2933;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f5f7; padding:24px 0;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%; max-width:600px; background-color:#ffffff; border:1px solid #e4e7eb; border-radius:6px;">
                <tr>
                    <td style="padding:24px 28px 8px 28px;">
                        <h1 style="margin:0; font-size:20px; color:#102a43;">Thank you for your order, {{ $order->customer->name }}!</h1>
                        <p style="margin:12px 0 0 0; font-size:14px; line-height:1.5;">
                            Your order <strong>#{{ $order->id }}</strong> placed on {{ $order->created_at->format('d M Y, H:i T') }} is confirmed.
                            Here is your bill.
                        </p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:16px 28px;">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
                            <thead>
                                <tr style="background-color:#f0f4f8;">
                                    <th align="left" style="padding:8px; border-bottom:1px solid #d9e2ec;">Item</th>
                                    <th align="right" style="padding:8px; border-bottom:1px solid #d9e2ec;">Qty</th>
                                    <th align="right" style="padding:8px; border-bottom:1px solid #d9e2ec;">Unit price</th>
                                    <th align="right" style="padding:8px; border-bottom:1px solid #d9e2ec;">Tax</th>
                                    <th align="right" style="padding:8px; border-bottom:1px solid #d9e2ec;">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($order->items as $item)
                                    <tr>
                                        <td align="left" style="padding:8px; border-bottom:1px solid #e4e7eb;">
                                            {{ $item->product_name }}<br>
                                            <span style="font-size:12px; color:#627d98;">{{ $item->product_code }}</span>
                                        </td>
                                        <td align="right" style="padding:8px; border-bottom:1px solid #e4e7eb;">{{ $item->quantity }}</td>
                                        <td align="right" style="padding:8px; border-bottom:1px solid #e4e7eb;">₹{{ $item->unit_price }}</td>
                                        <td align="right" style="padding:8px; border-bottom:1px solid #e4e7eb;">{{ $item->tax_percent }}%</td>
                                        <td align="right" style="padding:8px; border-bottom:1px solid #e4e7eb;">₹{{ $item->line_total }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" align="right" style="padding:8px 8px 4px 8px;">Subtotal</td>
                                    <td align="right" style="padding:8px 8px 4px 8px;">₹{{ $order->subtotal }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" align="right" style="padding:4px 8px;">Tax</td>
                                    <td align="right" style="padding:4px 8px;">₹{{ $order->tax_total }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" align="right" style="padding:8px; border-top:2px solid #102a43; font-weight:bold;">Grand total</td>
                                    <td align="right" style="padding:8px; border-top:2px solid #102a43; font-weight:bold;">₹{{ $order->grand_total }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:8px 28px 24px 28px; font-size:12px; color:#627d98; line-height:1.5;">
                        Prices are in Indian Rupees (₹). Unit prices exclude tax; tax is added per line.<br>
                        This is an automated confirmation sent to {{ $order->customer->email }}.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
