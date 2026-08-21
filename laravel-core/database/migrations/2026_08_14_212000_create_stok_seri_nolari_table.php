<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stok_seri_nolari')) {
            Schema::create('stok_seri_nolari', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->foreignId('stok_id')->constrained('stok_kartlari')->restrictOnDelete();
                $table->foreignId('depo_id')->nullable()->constrained('muhasebe_depolar')->nullOnDelete();
                $table->string('seri_no', 191);
                $table->string('durum', 24)->default('stokta');
                $table->decimal('birim_maliyet', 18, 8)->default(0);
                $table->date('garanti_baslangic_tarihi')->nullable();
                $table->date('garanti_bitis_tarihi')->nullable();
                $table->timestamps();

                $table->unique(['firma_id', 'seri_no']);
                $table->index(['firma_id', 'stok_id', 'depo_id', 'durum']);
            });
        }

        if (! Schema::hasTable('stok_hareketi_serileri')) {
            Schema::create('stok_hareketi_serileri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->foreignId('stok_hareketi_id')->constrained('stok_hareketleri')->cascadeOnDelete();
                $table->foreignId('stok_seri_no_id')->constrained('stok_seri_nolari')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['stok_hareketi_id', 'stok_seri_no_id']);
                $table->index(['firma_id', 'stok_seri_no_id']);
            });
        }

        if (Schema::hasTable('fatura_kalemleri') && ! Schema::hasColumn('fatura_kalemleri', 'seri_nolari')) {
            Schema::table('fatura_kalemleri', function (Blueprint $table): void {
                $table->json('seri_nolari')->nullable()->after('stok_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('fatura_kalemleri', 'seri_nolari')) {
            Schema::table('fatura_kalemleri', fn (Blueprint $table): Blueprint => $table->dropColumn('seri_nolari'));
        }
        Schema::dropIfExists('stok_hareketi_serileri');
        Schema::dropIfExists('stok_seri_nolari');
    }
};
