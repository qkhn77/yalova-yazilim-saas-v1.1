<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('teknik_servis_ariza_kayitlari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->nullable()->constrained('firmalar')->nullOnDelete();
            $table->foreignId('teknik_servis_kaydi_id')->constrained('teknik_servis_kayitlari')->cascadeOnDelete();
            $table->foreignId('ariza_id')->constrained('teknik_servis_tanim_arizalar')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['teknik_servis_kaydi_id', 'ariza_id'], 'ts_ariza_unique');
        });

        DB::table('teknik_servis_kayitlari')
            ->select(['id', 'firma_id', 'ariza_id'])
            ->whereNotNull('ariza_id')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                $insert = [];
                foreach ($rows as $row) {
                    $insert[] = [
                        'firma_id' => $row->firma_id,
                        'teknik_servis_kaydi_id' => $row->id,
                        'ariza_id' => $row->ariza_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }

                if ($insert !== []) {
                    DB::table('teknik_servis_ariza_kayitlari')->upsert(
                        $insert,
                        ['teknik_servis_kaydi_id', 'ariza_id'],
                        ['firma_id', 'updated_at']
                    );
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('teknik_servis_ariza_kayitlari');
    }
};
