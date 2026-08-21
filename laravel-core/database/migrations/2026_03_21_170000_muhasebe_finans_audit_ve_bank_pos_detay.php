<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finans_hareketleri', function (Blueprint $table): void {
            $table->foreignId('islem_yapan_kullanici_id')->nullable()->after('firma_id')->constrained('users')->nullOnDelete();
            $table->string('islem_kaynagi', 32)->default('panel')->after('islem_yapan_kullanici_id');
            $table->string('audit_ip', 45)->nullable()->after('islem_kaynagi');
            $table->decimal('brut_tutar', 18, 2)->nullable()->after('tutar');
            $table->decimal('pos_komisyon_tutari', 18, 2)->nullable()->after('brut_tutar');
            $table->decimal('pos_komisyon_orani_yuzde', 8, 4)->nullable()->after('pos_komisyon_tutari');
            $table->text('ek_aciklama')->nullable()->after('aciklama');
        });

        Schema::table('banka_hareketleri', function (Blueprint $table): void {
            $table->string('dekont_no', 64)->nullable()->after('para_birimi');
            $table->string('islem_referansi', 128)->nullable()->after('dekont_no');
            $table->text('detay_aciklama')->nullable()->after('islem_referansi');
        });

        Schema::table('pos_hareketleri', function (Blueprint $table): void {
            $table->decimal('brut_tutar', 18, 2)->nullable()->after('para_birimi');
            $table->decimal('komisyon_tutari', 18, 2)->nullable()->after('brut_tutar');
            $table->string('slip_no', 64)->nullable()->after('komisyon_tutari');
            $table->string('provizyon_no', 64)->nullable()->after('slip_no');
            $table->text('detay_aciklama')->nullable()->after('provizyon_no');
        });
    }

    public function down(): void
    {
        Schema::table('pos_hareketleri', function (Blueprint $table): void {
            $table->dropColumn(['brut_tutar', 'komisyon_tutari', 'slip_no', 'provizyon_no', 'detay_aciklama']);
        });

        Schema::table('banka_hareketleri', function (Blueprint $table): void {
            $table->dropColumn(['dekont_no', 'islem_referansi', 'detay_aciklama']);
        });

        Schema::table('finans_hareketleri', function (Blueprint $table): void {
            $table->dropForeign(['islem_yapan_kullanici_id']);
            $table->dropColumn([
                'islem_yapan_kullanici_id',
                'islem_kaynagi',
                'audit_ip',
                'brut_tutar',
                'pos_komisyon_tutari',
                'pos_komisyon_orani_yuzde',
                'ek_aciklama',
            ]);
        });
    }
};
