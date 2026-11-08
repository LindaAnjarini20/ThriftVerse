<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('username')->nullable()->unique();
            $t->string('email')->unique();
            $t->timestamp('email_verified_at')->nullable();
            $t->string('password');
            $t->string('role', 20)->default('user');        // user | admin
            $t->string('no_hp', 20)->nullable();
            $t->string('area')->nullable();                  // area (bukan alamat tepat) – FR-004/FR-091
            $t->decimal('area_lat', 10, 7)->nullable();
            $t->decimal('area_lng', 10, 7)->nullable();
            $t->string('address')->nullable();
            $t->boolean('seller_mode')->default(false);      // FR-005
            $t->string('payout_account')->nullable();        // rekening / dompet pencairan
            $t->integer('trust_score')->default(100);        // skor kepercayaan
            $t->boolean('is_active')->default(true);
            $t->rememberToken();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('users'); }
};
