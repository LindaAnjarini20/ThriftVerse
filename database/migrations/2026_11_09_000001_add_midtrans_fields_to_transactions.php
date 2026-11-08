<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('midtrans_status', 20)->nullable()->index();
            $table->string('midtrans_transaction_id')->nullable();
            $table->timestamp('midtrans_paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['midtrans_status', 'midtrans_transaction_id', 'midtrans_paid_at']);
        });
    }
};
