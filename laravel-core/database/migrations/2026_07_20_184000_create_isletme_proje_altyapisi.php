<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('isletme_projeleri')) {
            Schema::create('isletme_projeleri', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
                $table->string('kod', 64);
                $table->string('ad', 160);
                $table->string('durum', 32)->default('taslak')->index();
                $table->date('baslangic_tarihi')->nullable();
                $table->date('bitis_tarihi')->nullable();
                $table->decimal('butce_tutari', 18, 2)->nullable();
                $table->char('para_birimi', 3)->default('TRY');
                $table->text('aciklama')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['firma_id', 'kod'], 'isletme_proje_firma_kod_unique');
                $table->index(['firma_id', 'durum', 'baslangic_tarihi'], 'isletme_proje_firma_durum_tarih_idx');
            });
        }

        if (Schema::hasTable('masraflar') && ! Schema::hasColumn('masraflar', 'isletme_proje_id')) {
            Schema::table('masraflar', function (Blueprint $table): void {
                $table->foreignId('isletme_proje_id')
                    ->nullable()
                    ->after('masraf_kategorisi_id')
                    ->constrained('isletme_projeleri')
                    ->nullOnDelete();
                $table->index(['firma_id', 'isletme_proje_id', 'tarih'], 'masraf_proje_tarih_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('masraflar') && Schema::hasColumn('masraflar', 'isletme_proje_id')) {
            Schema::table('masraflar', function (Blueprint $table): void {
                $table->dropForeign(['isletme_proje_id']);
                $table->dropIndex('masraf_proje_tarih_idx');
                $table->dropColumn('isletme_proje_id');
            });
        }

        Schema::dropIfExists('isletme_projeleri');
    }
};
