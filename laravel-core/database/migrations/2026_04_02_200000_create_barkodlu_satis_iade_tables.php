<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_barkodlu_satis_iadeler')) {
            Schema::create('muhasebe_barkodlu_satis_iadeler', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->foreignId('satis_id')->constrained('muhasebe_barkodlu_satislar')->cascadeOnDelete();
                $table->string('iade_no', 64);
                $table->dateTime('iade_tarihi');
                $table->text('neden')->nullable();
                $table->decimal('toplam_iade_tutari', 18, 2)->default(0);
                $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['firma_id', 'iade_no'], 'muhasebe_barkodlu_satis_iadeler_firma_iade_no_uq');
                $table->index(['firma_id', 'satis_id'], 'muhasebe_barkodlu_satis_iadeler_firma_satis_idx');
            });
        }

        if (! Schema::hasTable('muhasebe_barkodlu_satis_iade_kalemleri')) {
            Schema::create('muhasebe_barkodlu_satis_iade_kalemleri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->foreignId('iade_id')->constrained('muhasebe_barkodlu_satis_iadeler')->cascadeOnDelete();
                $table->foreignId('satis_kalem_id')->constrained('muhasebe_barkodlu_satis_kalemleri')->cascadeOnDelete();
                $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
                $table->decimal('miktar', 14, 4)->default(1);
                $table->decimal('birim_fiyat', 18, 2)->default(0);
                $table->decimal('kdv_orani', 5, 2)->default(0);
                $table->decimal('kdv_tutari', 18, 2)->default(0);
                $table->decimal('satir_toplami', 18, 2)->default(0);
                $table->timestamps();

                $table->index(['firma_id', 'satis_kalem_id'], 'muhasebe_bs_iade_kalemleri_firma_satis_kalem_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('muhasebe_barkodlu_satis_iade_kalemleri');
        Schema::dropIfExists('muhasebe_barkodlu_satis_iadeler');
    }
};

