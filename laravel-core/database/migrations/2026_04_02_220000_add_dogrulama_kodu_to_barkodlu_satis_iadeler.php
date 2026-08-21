<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('muhasebe_barkodlu_satis_iadeler')) {
            return;
        }

        Schema::table('muhasebe_barkodlu_satis_iadeler', function (Blueprint $table): void {
            if (! Schema::hasColumn('muhasebe_barkodlu_satis_iadeler', 'dogrulama_kodu')) {
                $table->string('dogrulama_kodu', 64)->nullable()->after('iade_no');
                $table->unique(['firma_id', 'dogrulama_kodu'], 'muhasebe_bs_iadeler_firma_dogrulama_uq');
            }
        });

        $kayitlar = DB::table('muhasebe_barkodlu_satis_iadeler')
            ->whereNull('dogrulama_kodu')
            ->select(['id', 'firma_id'])
            ->get();

        foreach ($kayitlar as $kayit) {
            $kod = 'IAD-'.now()->format('Ymd').'-'.$kayit->id;
            DB::table('muhasebe_barkodlu_satis_iadeler')
                ->where('id', $kayit->id)
                ->update(['dogrulama_kodu' => $kod]);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('muhasebe_barkodlu_satis_iadeler')) {
            return;
        }

        Schema::table('muhasebe_barkodlu_satis_iadeler', function (Blueprint $table): void {
            if (Schema::hasColumn('muhasebe_barkodlu_satis_iadeler', 'dogrulama_kodu')) {
                $table->dropUnique('muhasebe_bs_iadeler_firma_dogrulama_uq');
                $table->dropColumn('dogrulama_kodu');
            }
        });
    }
};
