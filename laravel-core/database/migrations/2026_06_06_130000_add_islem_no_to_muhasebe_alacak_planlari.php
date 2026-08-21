<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_alacak_planlari')) {
            return;
        }

        if (! Schema::hasColumn('muhasebe_alacak_planlari', 'islem_no')) {
            Schema::table('muhasebe_alacak_planlari', function (Blueprint $table): void {
                $table->string('islem_no', 64)->nullable()->after('id');
            });
        }

        DB::table('muhasebe_alacak_planlari')
            ->where(function ($query): void {
                $query->whereNull('islem_no')->orWhere('islem_no', '');
            })
            ->orderBy('id')
            ->chunkById(500, function ($planlar): void {
                foreach ($planlar as $plan) {
                    $tarih = $plan->created_at
                        ? date('Ymd', strtotime((string) $plan->created_at))
                        : now()->format('Ymd');

                    DB::table('muhasebe_alacak_planlari')
                        ->where('id', (int) $plan->id)
                        ->update([
                            'islem_no' => sprintf('VP-%s-%06d', $tarih, (int) $plan->id),
                        ]);
                }
            });

        Schema::table('muhasebe_alacak_planlari', function (Blueprint $table): void {
            $table->unique(['firma_id', 'islem_no'], 'muh_alacak_plan_firma_islem_no_uq');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('muhasebe_alacak_planlari') || ! Schema::hasColumn('muhasebe_alacak_planlari', 'islem_no')) {
            return;
        }

        Schema::table('muhasebe_alacak_planlari', function (Blueprint $table): void {
            $table->dropUnique('muh_alacak_plan_firma_islem_no_uq');
            $table->dropColumn('islem_no');
        });
    }
};
