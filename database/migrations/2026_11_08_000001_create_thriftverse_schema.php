<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $t->string('title', 150);
            $t->string('slug')->unique();
            $t->string('category', 80);
            $t->string('brand', 80)->nullable();
            $t->string('size', 40)->nullable();
            $t->string('color', 40)->nullable();
            $t->string('material', 80)->nullable();
            $t->text('description')->nullable();
            $t->string('condition_grade', 1);                 // A | B | C  (dihitung dari checklist)
            $t->json('condition_checklist')->nullable();
            $t->timestamp('verified_at')->nullable();         // FR-017 lencana terverifikasi
            $t->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('original_price', 12, 2)->default(0); // harga baru
            $t->decimal('current_price', 12, 2);
            $t->decimal('secret_min_price', 12, 2)->nullable(); // FR-070 harga minimum rahasia
            $t->unsignedInteger('stock')->default(1);
            $t->json('images')->nullable();                   // depan, belakang, label, detail
            $t->json('minus_photos')->nullable();             // close-up setiap minus
            $t->boolean('harga_tetap')->default(true);        // FR-019
            $t->boolean('bisa_tawar')->default(false);
            $t->boolean('terbuka_barter')->default(false);
            $t->string('barter_preference')->nullable();
            $t->boolean('bisa_cod')->default(false);
            $t->unsignedSmallInteger('cod_radius_km')->default(10);
            $t->string('cod_location')->nullable();
            $t->decimal('cod_latitude', 10, 7)->nullable();
            $t->decimal('cod_longitude', 10, 7)->nullable();
            $t->string('status', 20)->default('active')->index(); // draft|active|reserved|sold|archived
            $t->timestamp('published_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::create('price_drop_histories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->decimal('from_price', 12, 2);
            $t->decimal('to_price', 12, 2);
            $t->string('source', 20)->default('manual');      // manual | auto | deal
            $t->timestamp('changed_at');
            $t->timestamps();
            $t->index(['listing_id', 'changed_at']);
        });

        Schema::create('meeting_points', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('address');
            $t->string('city', 60);
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->json('features')->nullable();
            $t->boolean('recommended')->default(false);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });

        Schema::create('bids', function (Blueprint $t) {
            $t->id();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('proposer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('bids')->nullOnDelete();
            $t->decimal('amount', 12, 2);
            $t->string('delivery_method', 12)->default('ekspedisi'); // FR-072
            $t->text('message')->nullable();
            $t->unsignedTinyInteger('round')->default(1);
            $t->string('status', 20)->default('pending')->index();   // pending|accepted|rejected|expired|closed|countered
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('responded_at')->nullable();
            $t->decimal('deal_price', 12, 2)->nullable();
            $t->timestamps();
            $t->index(['listing_id', 'buyer_id']);
        });

        Schema::create('barters', function (Blueprint $t) {
            $t->id();
            $t->foreignId('target_listing_id')->constrained('listings')->cascadeOnDelete();
            $t->foreignId('offerer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('owner_id')->constrained('users')->cascadeOnDelete(); // pemilik listing target
            $t->foreignId('awaiting_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('cash_top_up', 12, 2)->default(0);
            $t->string('top_up_payer', 10)->nullable();        // offerer | owner
            $t->string('method', 12)->default('ketemuan');     // ketemuan | kirim
            $t->text('message')->nullable();
            $t->unsignedTinyInteger('round')->default(1);
            $t->boolean('offerer_approved')->default(false);
            $t->boolean('owner_approved')->default(false);
            $t->string('status', 20)->default('pending')->index(); // pending|accepted|rejected|cancelled|expired|completed
            $t->timestamp('expires_at')->nullable();
            $t->json('history')->nullable();
            $t->timestamps();
        });

        Schema::create('barter_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('barter_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->decimal('estimated_value', 12, 2);
            $t->timestamps();
            $t->unique(['barter_id', 'listing_id']);
        });

        Schema::create('transactions', function (Blueprint $t) {
            $t->id();
            $t->string('transaction_number')->unique();
            $t->foreignId('listing_id')->constrained()->restrictOnDelete();
            $t->foreignId('seller_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $t->foreignId('payer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('bid_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('barter_id')->nullable()->constrained()->nullOnDelete();
            $t->string('type', 10)->default('purchase');       // purchase | barter
            $t->string('delivery_method', 12)->nullable();     // ekspedisi | cod
            $t->string('payment_method', 12)->nullable();      // app | cash
            $t->decimal('agreed_price', 12, 2)->default(0);
            $t->decimal('shipping_cost', 12, 2)->default(0);
            $t->decimal('service_fee', 12, 2)->default(0);
            $t->decimal('total', 12, 2)->default(0);
            // pending (belum bayar/atur) | packed (dikemas / menunggu pertemuan) | shipped | completed | cancelled | dispute
            $t->string('status', 20)->default('pending')->index();
            $t->string('escrow_status', 12)->default('none');  // none|pending|funded|released|refunded
            $t->timestamp('hold_until')->nullable();           // batas bayar / jadwalkan (FR-075)
            $t->timestamp('paid_at')->nullable();
            $t->timestamp('ship_by')->nullable();              // batas kirim 2x24 jam
            $t->string('courier', 40)->nullable();
            $t->string('tracking_no', 60)->nullable();
            $t->timestamp('shipped_at')->nullable();
            $t->timestamp('auto_complete_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();
        });

        Schema::create('cod_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $t->foreignId('meeting_point_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('proposed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('scheduled_at');
            $t->string('status', 12)->default('proposed')->index(); // proposed|confirmed|completed|cancelled
            $t->text('code_enc')->nullable();                  // kode 6 digit terenkripsi
            $t->unsignedTinyInteger('code_attempts')->default(0);
            $t->timestamp('buyer_arrived_at')->nullable();
            $t->boolean('buyer_arrival_verified')->default(false);
            $t->timestamp('seller_arrived_at')->nullable();
            $t->boolean('seller_arrival_verified')->default(false);
            $t->timestamp('handover_at')->nullable();
            $t->timestamps();
        });

        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $t->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('reviewee_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('rating');
            $t->boolean('grade_accurate')->default(true);      // FR-053
            $t->text('comment')->nullable();
            $t->timestamps();
            $t->unique(['transaction_id', 'reviewer_id']);
        });

        Schema::create('reports', function (Blueprint $t) {
            $t->id();
            $t->string('type', 20);                            // dispute | listing | user | cod_reject | no_show
            $t->foreignId('reporter_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $t->text('reason');
            $t->json('evidence')->nullable();
            $t->string('status', 12)->default('open')->index(); // open | resolved
            $t->string('resolution', 20)->nullable();          // refund | release | dismissed
            $t->text('admin_note')->nullable();
            $t->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('resolved_at')->nullable();
            $t->timestamps();
        });

        Schema::create('wishlists', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $t->timestamps();
            $t->unique(['user_id', 'listing_id']);
        });
    }

    public function down(): void
    {
        foreach (['wishlists','reports','reviews','cod_schedules','transactions','barter_items','barters','bids','meeting_points','price_drop_histories','listings'] as $tb) {
            Schema::dropIfExists($tb);
        }
    }
};
