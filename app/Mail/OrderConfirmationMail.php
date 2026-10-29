<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * The order must arrive with its `customer` and `items` relations already loaded:
     * the views read them, and lazy loading is forbidden in this application.
     */
    public function __construct(public Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your order #{$this->order->id} is confirmed",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.order-confirmation',
            text: 'emails.order-confirmation-text',
        );
    }
}
