<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('code', 50)->unique();
            $table->decimal('price', 10, 2);
            $table->decimal('tax_percent', 5, 2)->default(0);
            $table->unsignedInteger('stock_quantity')->default(0)->index();
            $table->timestamps();
        });

        // Defence in depth: the application validates these too, but the database
        // must never accept a negative price/stock even from a buggy code path.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_non_negative CHECK (price >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_tax_percent_range CHECK (tax_percent >= 0 AND tax_percent <= 100)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_non_negative CHECK (stock_quantity >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
