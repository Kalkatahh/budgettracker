<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->date('date')->nullable(); // Store date of purchase
            $table->string('store')->nullable(); // Store name
            $table->string('payment_method')->nullable(); // Payment method (e.g., Card)
            $table->decimal('cost', 8, 2)->nullable(); // Cost as a decimal with 2 decimal places
        });
    }

    public function down()
    {
        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['date', 'store', 'payment_method', 'cost']);
        });
    }
};