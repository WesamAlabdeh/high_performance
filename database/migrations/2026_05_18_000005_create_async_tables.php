<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel');
            $table->string('status')->default('pending');
            $table->text('payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('daily_sales_snapshots', function (Blueprint $table) {
            $table->id();
            $table->date('snapshot_date');
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('units_sold')->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->unique(['snapshot_date', 'product_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_sales_snapshots');
        Schema::dropIfExists('order_notifications');
        Schema::dropIfExists('order_invoices');
    }
};
