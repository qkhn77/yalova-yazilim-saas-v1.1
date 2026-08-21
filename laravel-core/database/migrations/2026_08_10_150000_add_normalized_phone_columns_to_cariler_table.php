<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cariler', function (Blueprint $table): void {
            $table->string('telefon_normalize', 32)->nullable()->after('telefon');
            $table->string('gsm_normalize', 32)->nullable()->after('gsm');
            $table->index(['firma_id', 'telefon_normalize'], 'cariler_firma_telefon_normalize_idx');
            $table->index(['firma_id', 'gsm_normalize'], 'cariler_firma_gsm_normalize_idx');
        });

        DB::table('cariler')->select(['id', 'telefon', 'gsm'])->orderBy('id')->chunkById(500, function ($cariler): void {
            foreach ($cariler as $cari) {
                DB::table('cariler')
                    ->where('id', $cari->id)
                    ->update([
                        'telefon_normalize' => self::normalize($cari->telefon),
                        'gsm_normalize' => self::normalize($cari->gsm),
                    ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('cariler', function (Blueprint $table): void {
            $table->dropIndex('cariler_firma_telefon_normalize_idx');
            $table->dropIndex('cariler_firma_gsm_normalize_idx');
            $table->dropColumn(['telefon_normalize', 'gsm_normalize']);
        });
    }

    private static function normalize(mixed $telefon): ?string
    {
        $normalize = preg_replace('/\D+/', '', (string) ($telefon ?? '')) ?? '';

        return $normalize !== '' ? $normalize : null;
    }
};
