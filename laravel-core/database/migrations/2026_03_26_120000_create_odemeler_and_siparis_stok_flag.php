<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            $table->boolean('stok_dusuldu_mi')->default(false)->after('durum');
        });

        // Eski akış: checkout’ta stok düşülüyordu, sipariş durumu beklemede idi.
        DB::table('siparisler')->where('durum', 'beklemede')->update(['stok_dusuldu_mi' => true]);
        DB::table('siparisler')->where('durum', 'onaylandi')->update(['durum' => 'odendi', 'stok_dusuldu_mi' => true]);

        Schema::create('odemeler', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siparis_id')->constrained('siparisler')->cascadeOnDelete();
            $table->string('odeme_no', 32)->unique();
            $table->decimal('tutar', 14, 2);
            $table->string('para_birimi', 3)->default('TRY');
            $table->string('durum', 32)->index();
            $table->string('provider', 64)->nullable();
            $table->string('provider_ref', 191)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odemeler');
        Schema::table('siparisler', function (Blueprint $table): void {
            $table->dropColumn('stok_dusuldu_mi');
        });
    }
};
