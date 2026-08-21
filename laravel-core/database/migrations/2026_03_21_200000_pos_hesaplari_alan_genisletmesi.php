<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pos_hesaplari', function (Blueprint $table): void {
            $table->string('pos_tipi', 32)->default('fiziki_pos')->after('ad');
            $table->string('saglayici_tipi', 32)->default('banka_posu')->after('pos_tipi');
            $table->foreignId('banka_hesabi_id')->nullable()->after('saglayici_tipi')->constrained('banka_hesaplari')->nullOnDelete();
            $table->string('banka_adi', 191)->nullable()->after('banka_hesabi_id');
            $table->string('saglayici_adi', 191)->nullable()->after('banka_adi');
            $table->string('uye_isyeri_no', 64)->nullable()->after('terminal_no');
            $table->string('magaza_kodu', 64)->nullable()->after('uye_isyeri_no');
            $table->string('sanal_pos_no', 64)->nullable()->after('magaza_kodu');
            $table->decimal('komisyon_orani', 8, 4)->nullable()->after('para_birimi');
            $table->decimal('sabit_komisyon_tutari', 18, 2)->nullable()->after('komisyon_orani');
            $table->unsignedSmallInteger('bloke_gun_sayisi')->nullable()->after('sabit_komisyon_tutari');
            $table->unsignedSmallInteger('valor_gun_sayisi')->nullable()->after('bloke_gun_sayisi');
            $table->boolean('erken_odeme_destegi_var_mi')->default(false)->after('valor_gun_sayisi');
            $table->boolean('taksit_destegi_var_mi')->default(false)->after('erken_odeme_destegi_var_mi');
            $table->unsignedTinyInteger('maksimum_taksit_sayisi')->nullable()->after('taksit_destegi_var_mi');
            $table->boolean('tek_cekim_destegi_var_mi')->default(true)->after('maksimum_taksit_sayisi');
            $table->boolean('varsayilan_mi')->default(false)->after('tek_cekim_destegi_var_mi');

            $table->index(['firma_id', 'varsayilan_mi']);
            $table->index(['firma_id', 'pos_tipi']);
            $table->index(['firma_id', 'saglayici_tipi']);
        });
    }

    public function down(): void
    {
        Schema::table('pos_hesaplari', function (Blueprint $table): void {
            $table->dropIndex(['firma_id', 'varsayilan_mi']);
            $table->dropIndex(['firma_id', 'pos_tipi']);
            $table->dropIndex(['firma_id', 'saglayici_tipi']);

            $table->dropConstrainedForeignId('banka_hesabi_id');

            $table->dropColumn([
                'pos_tipi',
                'saglayici_tipi',
                'banka_adi',
                'saglayici_adi',
                'uye_isyeri_no',
                'magaza_kodu',
                'sanal_pos_no',
                'komisyon_orani',
                'sabit_komisyon_tutari',
                'bloke_gun_sayisi',
                'valor_gun_sayisi',
                'erken_odeme_destegi_var_mi',
                'taksit_destegi_var_mi',
                'maksimum_taksit_sayisi',
                'tek_cekim_destegi_var_mi',
                'varsayilan_mi',
            ]);
        });
    }
};
