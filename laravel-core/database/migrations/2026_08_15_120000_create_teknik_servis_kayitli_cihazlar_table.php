<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_kayitli_cihazlar')) {
            Schema::create('teknik_servis_kayitli_cihazlar', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar')->restrictOnDelete();
                $table->foreignId('cari_id')->constrained('cariler')->restrictOnDelete();
                $table->foreignId('cihaz_id')->nullable()->constrained('teknik_servis_tanim_cihazlar')->nullOnDelete();
                $table->foreignId('marka_id')->nullable()->constrained('teknik_servis_tanim_markalar')->nullOnDelete();
                $table->string('model_no', 128)->nullable();
                $table->string('seri_no', 128)->nullable();
                $table->string('ayirt_edici_bilgi', 255)->nullable();
                $table->text('notlar')->nullable();
                $table->boolean('aktif_mi')->default(true);
                $table->foreignId('olusturan_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('guncelleyen_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['firma_id', 'cari_id'], 'ts_kayitli_cihazlar_firma_cari_idx');
                $table->index(['firma_id', 'seri_no'], 'ts_kayitli_cihazlar_firma_seri_idx');
                $table->index(['firma_id', 'cihaz_id', 'marka_id', 'model_no'], 'ts_kayitli_cihazlar_kimlik_idx');
            });
        }

        if (! Schema::hasColumn('teknik_servis_kayitlari', 'kayitli_cihaz_id')) {
            Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
                $table->foreignId('kayitli_cihaz_id')
                    ->nullable()
                    ->after('cari_id')
                    ->constrained('teknik_servis_kayitli_cihazlar')
                    ->nullOnDelete();
                $table->index(['firma_id', 'kayitli_cihaz_id'], 'ts_servis_kayitlari_kayitli_cihaz_idx');
            });
        }

        $cihazlar = [];
        DB::table('teknik_servis_kayitlari')
            ->select(['id', 'firma_id', 'cari_id', 'cihaz_id', 'marka_id', 'model_no', 'seri_no'])
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->chunkById(500, function ($kayitlar) use (&$cihazlar): void {
                foreach ($kayitlar as $kayit) {
                    if (! $kayit->cihaz_id && ! $kayit->marka_id && blank($kayit->model_no) && blank($kayit->seri_no)) {
                        continue;
                    }

                    $anahtar = implode('|', [
                        (int) $kayit->firma_id,
                        (int) $kayit->cari_id,
                        (int) ($kayit->cihaz_id ?? 0),
                        (int) ($kayit->marka_id ?? 0),
                        trim((string) ($kayit->model_no ?? '')),
                        trim((string) ($kayit->seri_no ?? '')),
                    ]);

                    if (! isset($cihazlar[$anahtar])) {
                        $cihazlar[$anahtar] = DB::table('teknik_servis_kayitli_cihazlar')->insertGetId([
                            'firma_id' => $kayit->firma_id,
                            'cari_id' => $kayit->cari_id,
                            'cihaz_id' => $kayit->cihaz_id,
                            'marka_id' => $kayit->marka_id,
                            'model_no' => trim((string) ($kayit->model_no ?? '')) ?: null,
                            'seri_no' => trim((string) ($kayit->seri_no ?? '')) ?: null,
                            'aktif_mi' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('teknik_servis_kayitlari')
                        ->where('id', $kayit->id)
                        ->update(['kayitli_cihaz_id' => $cihazlar[$anahtar]]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('teknik_servis_kayitlari', 'kayitli_cihaz_id')) {
            Schema::table('teknik_servis_kayitlari', function (Blueprint $table): void {
                $table->dropForeign(['kayitli_cihaz_id']);
                $table->dropIndex('ts_servis_kayitlari_kayitli_cihaz_idx');
                $table->dropColumn('kayitli_cihaz_id');
            });
        }

        Schema::dropIfExists('teknik_servis_kayitli_cihazlar');
    }
};
