<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Auto price drop (listing)
        Schema::table('listings', function (Blueprint $t) {
            $t->boolean('auto_drop')->default(false);
            $t->unsignedTinyInteger('drop_percent')->default(5);
            $t->unsignedTinyInteger('drop_every_days')->default(7);
            $t->decimal('floor_price', 12, 2)->nullable();
            $t->timestamp('last_drop_at')->nullable();
        });

        // Voucher
        Schema::create('vouchers', function (Blueprint $t) {
            $t->id();
            $t->string('code', 40)->unique();
            $t->string('description')->nullable();
            $t->string('type', 10)->default('percent');          // percent | fixed
            $t->decimal('value', 12, 2);
            $t->decimal('min_spend', 12, 2)->default(0);
            $t->decimal('max_discount', 12, 2)->nullable();       // batas potongan untuk tipe percent
            $t->unsignedInteger('quota')->nullable();             // null = tanpa batas
            $t->timestamp('starts_at')->nullable();
            $t->timestamp('ends_at')->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        Schema::create('voucher_redemptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('group_key', 40)->index();                 // nomor transaksi / kode grup keranjang
            $t->timestamps();
        });
        Schema::table('transactions', function (Blueprint $t) {
            $t->unsignedBigInteger('voucher_id')->nullable();
            $t->string('voucher_group', 40)->nullable()->index();
            $t->decimal('discount', 12, 2)->default(0);
            $t->string('cart_group', 40)->nullable()->index();
        });

        // Chat
        Schema::create('conversations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->timestamp('last_message_at')->nullable();
            $t->timestamps();
            $t->unique(['listing_id', 'buyer_id']);
        });
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $t->text('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['conversation_id', 'id']);
        });

        // Keranjang
        Schema::create('cart_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        foreach (['cart_items', 'messages', 'conversations', 'voucher_redemptions', 'vouchers'] as $tb) Schema::dropIfExists($tb);
        Schema::table('transactions', function (Blueprint $t) { $t->dropColumn(['voucher_id', 'voucher_group', 'discount', 'cart_group']); });
        Schema::table('listings', function (Blueprint $t) { $t->dropColumn(['auto_drop', 'drop_percent', 'drop_every_days', 'floor_price', 'last_drop_at']); });
    }
};
