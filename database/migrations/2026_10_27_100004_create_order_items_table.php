<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete();

            // Snapshots: an order must keep showing what was sold and at what price
            // even after the product is renamed or repriced.
            $table->string('product_code', 50);
            $table->string('product_name', 255);
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('tax_percent', 5, 2);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('line_tax', 12, 2);
            $table->decimal('line_total', 12, 2);
            $table->timestamps();

            // One line per product per order; also serves as the index for order_id lookups.
            $table->unique(['order_id', 'product_id']);
        });

        DB::statement('ALTER TABLE order_items ADD CONSTRAINT order_items_quantity_positive CHECK (quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
