<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('total_price', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('cart_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->unique(['cart_id', 'product_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_products');
        Schema::dropIfExists('carts');
    }
};
