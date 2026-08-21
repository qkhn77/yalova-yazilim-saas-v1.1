<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cariler', function (Blueprint $table): void {
            if (! Schema::hasColumn('cariler', 'kullanici_id')) {
                $table->foreignId('kullanici_id')->nullable()->after('firma_id')->constrained('users')->nullOnDelete();
                $table->index(['firma_id', 'kullanici_id'], 'cariler_firma_kullanici_index');
            }
        });

        Schema::table('siparisler', function (Blueprint $table): void {
            if (! Schema::hasColumn('siparisler', 'ecommerce_odeme_yontemi_id')) {
                $table->foreignId('ecommerce_odeme_yontemi_id')
                    ->nullable()
                    ->after('odeme_provider')
                    ->constrained('ecommerce_odeme_yontemleri')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('siparisler', 'muhasebe_cari_id')) {
                $table->foreignId('muhasebe_cari_id')
                    ->nullable()
                    ->after('operasyon_notu')
                    ->constrained('cariler')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('siparisler', 'proforma_fatura_id')) {
                $table->foreignId('proforma_fatura_id')
                    ->nullable()
                    ->after('muhasebe_cari_id')
                    ->constrained('faturalar')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('siparisler', 'tahsilat_finans_hareketi_id')) {
                $table->foreignId('tahsilat_finans_hareketi_id')
                    ->nullable()
                    ->after('proforma_fatura_id')
                    ->constrained('finans_hareketleri')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('siparisler', 'muhasebe_entegrasyon_durumu')) {
                $table->string('muhasebe_entegrasyon_durumu', 32)->nullable()->after('tahsilat_finans_hareketi_id');
            }

            if (! Schema::hasColumn('siparisler', 'muhasebe_entegrasyon_notu')) {
                $table->text('muhasebe_entegrasyon_notu')->nullable()->after('muhasebe_entegrasyon_durumu');
            }

            if (! Schema::hasColumn('siparisler', 'muhasebe_entegrasyon_at')) {
                $table->timestamp('muhasebe_entegrasyon_at')->nullable()->after('muhasebe_entegrasyon_notu');
            }
        });
    }

    public function down(): void
    {
        Schema::table('siparisler', function (Blueprint $table): void {
            foreach ([
                'muhasebe_entegrasyon_at',
                'muhasebe_entegrasyon_notu',
                'muhasebe_entegrasyon_durumu',
                'tahsilat_finans_hareketi_id',
                'proforma_fatura_id',
                'muhasebe_cari_id',
                'ecommerce_odeme_yontemi_id',
            ] as $column) {
                if (Schema::hasColumn('siparisler', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }
        });

        Schema::table('cariler', function (Blueprint $table): void {
            if (Schema::hasColumn('cariler', 'kullanici_id')) {
                $table->dropIndex('cariler_firma_kullanici_index');
                $table->dropConstrainedForeignId('kullanici_id');
            }
        });
    }
};
