<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('balance', 14, 2)->default(10000)->after('password');
            $table->unsignedInteger('version')->default(0)->after('balance');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->default('pending')->after('order_status');
            $table->string('payment_reference')->nullable()->after('payment_status');
            $table->timestamp('paid_at')->nullable()->after('payment_reference');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('status');
            $table->unsignedInteger('simulated_delay_ms')->default(0);
            $table->text('gateway_response')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['payment_status', 'payment_reference', 'paid_at']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance', 'version']);
        });
    }
};
