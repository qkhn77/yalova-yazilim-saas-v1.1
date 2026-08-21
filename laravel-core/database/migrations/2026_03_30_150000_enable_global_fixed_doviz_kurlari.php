<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_doviz_kurlari')) {
            return;
        }

        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_doviz_kurlari', 'is_sabit')) {
                $table->boolean('is_sabit')->default(false)->after('firma_id');
            }
            if (! Schema::hasColumn('muhasebe_doviz_kurlari', 'tanim_firma_kapsami')) {
                $table->unsignedBigInteger('tanim_firma_kapsami')->default(0)->after('is_sabit');
            }
        });

        DB::table('muhasebe_doviz_kurlari')
            ->whereNull('tanim_firma_kapsami')
            ->update([
                'is_sabit' => DB::raw('COALESCE(is_sabit, 0)'),
                'tanim_firma_kapsami' => DB::raw('COALESCE(firma_id, 0)'),
            ]);

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE muhasebe_doviz_kurlari DROP FOREIGN KEY muhasebe_doviz_kurlari_firma_id_foreign');
            } catch (\Throwable) {
            }

            DB::statement('ALTER TABLE muhasebe_doviz_kurlari MODIFY firma_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE muhasebe_doviz_kurlari ADD CONSTRAINT muhasebe_doviz_kurlari_firma_id_foreign FOREIGN KEY (firma_id) REFERENCES firmalar(id) ON DELETE SET NULL');
        }

        try {
            Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
                $table->dropUnique('muhasebe_doviz_kurlari_unique');
            });
        } catch (\Throwable) {
        }

        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            $table->unique(
                ['tanim_firma_kapsami', 'kaynak_para_birimi', 'hedef_para_birimi', 'tarih'],
                'muhasebe_doviz_kurlari_kapsam_parite_tarih_unique'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('muhasebe_doviz_kurlari')) {
            return;
        }

        try {
            Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
                $table->dropUnique('muhasebe_doviz_kurlari_kapsam_parite_tarih_unique');
            });
        } catch (\Throwable) {
        }

        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            $table->unique(
                ['firma_id', 'kaynak_para_birimi', 'hedef_para_birimi', 'tarih'],
                'muhasebe_doviz_kurlari_unique'
            );
        });

        if (DB::getDriverName() === 'mysql') {
            try {
                DB::statement('ALTER TABLE muhasebe_doviz_kurlari DROP FOREIGN KEY muhasebe_doviz_kurlari_firma_id_foreign');
            } catch (\Throwable) {
            }

            DB::statement('ALTER TABLE muhasebe_doviz_kurlari MODIFY firma_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE muhasebe_doviz_kurlari ADD CONSTRAINT muhasebe_doviz_kurlari_firma_id_foreign FOREIGN KEY (firma_id) REFERENCES firmalar(id) ON DELETE CASCADE');
        }

        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            if (Schema::hasColumn('muhasebe_doviz_kurlari', 'tanim_firma_kapsami')) {
                $table->dropColumn('tanim_firma_kapsami');
            }
            if (Schema::hasColumn('muhasebe_doviz_kurlari', 'is_sabit')) {
                $table->dropColumn('is_sabit');
            }
        });
    }
};
