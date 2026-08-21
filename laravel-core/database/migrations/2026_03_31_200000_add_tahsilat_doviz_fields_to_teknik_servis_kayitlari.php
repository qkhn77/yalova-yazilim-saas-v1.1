<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_hedef_para_birimi')) {
                $table->char('tahsilat_hedef_para_birimi', 3)->nullable()->after('tahsilat_para_birimi');
            }
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_doviz_kuru_turu')) {
                $table->string('tahsilat_doviz_kuru_turu', 16)->nullable()->after('tahsilat_hedef_para_birimi');
            }
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_doviz_kuru')) {
                $table->decimal('tahsilat_doviz_kuru', 18, 8)->nullable()->after('tahsilat_doviz_kuru_turu');
            }
            if (! Schema::hasColumn('teknik_servis_kayitlari', 'tahsilat_hedef_tutar')) {
                $table->decimal('tahsilat_hedef_tutar', 18, 2)->nullable()->after('tahsilat_tutari');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitlari')) {
            return;
        }

        Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
            foreach ([
                'tahsilat_hedef_para_birimi',
                'tahsilat_doviz_kuru_turu',
                'tahsilat_doviz_kuru',
                'tahsilat_hedef_tutar',
            ] as $kolon) {
                if (Schema::hasColumn('teknik_servis_kayitlari', $kolon)) {
                    $table->dropColumn($kolon);
                }
            }
        });
    }
};
