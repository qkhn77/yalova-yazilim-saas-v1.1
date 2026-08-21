<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('personel_maas_hareketleri')) {
            return;
        }

        Schema::table('personel_maas_hareketleri', function (Blueprint $table): void {
            if (! Schema::hasColumn('personel_maas_hareketleri', 'sgk_isveren_tutari')) {
                $table->decimal('sgk_isveren_tutari', 15, 2)->default(0)->after('net_tutar');
            }
            if (! Schema::hasColumn('personel_maas_hareketleri', 'issizlik_isveren_tutari')) {
                $table->decimal('issizlik_isveren_tutari', 15, 2)->default(0)->after('sgk_isveren_tutari');
            }
            if (! Schema::hasColumn('personel_maas_hareketleri', 'gelir_vergisi_tutari')) {
                $table->decimal('gelir_vergisi_tutari', 15, 2)->default(0)->after('issizlik_isveren_tutari');
            }
            if (! Schema::hasColumn('personel_maas_hareketleri', 'damga_vergisi_tutari')) {
                $table->decimal('damga_vergisi_tutari', 15, 2)->default(0)->after('gelir_vergisi_tutari');
            }
            if (! Schema::hasColumn('personel_maas_hareketleri', 'diger_maliyet_tutari')) {
                $table->decimal('diger_maliyet_tutari', 15, 2)->default(0)->after('damga_vergisi_tutari');
            }
            if (! Schema::hasColumn('personel_maas_hareketleri', 'maliyet_notu')) {
                $table->text('maliyet_notu')->nullable()->after('diger_maliyet_tutari');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('personel_maas_hareketleri')) {
            return;
        }

        $columns = [
            'sgk_isveren_tutari',
            'issizlik_isveren_tutari',
            'gelir_vergisi_tutari',
            'damga_vergisi_tutari',
            'diger_maliyet_tutari',
            'maliyet_notu',
        ];

        Schema::table('personel_maas_hareketleri', function (Blueprint $table) use ($columns): void {
            $table->dropColumn(array_values(array_filter(
                $columns,
                static fn (string $column): bool => Schema::hasColumn('personel_maas_hareketleri', $column),
            )));
        });
    }
};
