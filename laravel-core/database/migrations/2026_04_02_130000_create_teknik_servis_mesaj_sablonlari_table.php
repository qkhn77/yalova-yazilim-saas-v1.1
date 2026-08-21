<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('teknik_servis_mesaj_sablonlari')) {
            Schema::create('teknik_servis_mesaj_sablonlari', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('firma_id');
                $table->string('kanal', 24)->default('whatsapp');
                $table->string('kod', 64);
                $table->string('ad', 191);
                $table->text('mesaj');
                $table->boolean('aktif')->default(true);
                $table->unsignedInteger('siralama')->default(0);
                $table->timestamps();

                $table->foreign('firma_id', 'ts_mesaj_sablon_firma_fk')->references('id')->on('firmalar')->cascadeOnDelete();
                $table->unique(['firma_id', 'kanal', 'kod'], 'ts_mesaj_sablon_firma_kanal_kod_uq');
                $table->index(['firma_id', 'kanal', 'aktif'], 'ts_mesaj_sablon_firma_kanal_aktif_idx');
            });
        }

        $sablon = implode("\n\n", [
            'Merhaba Sayin Musterimiz,',
            'Cihazinizin guvenli, hizli ve sorunsuz calismasini surdurebilmesi icin termal macun yenileme ve fan temizligi periyodik bakim zamaniniz gelmistir.',
            'Bu bakim, cihaz sagligini dogrudan koruyan kritik bir teknik islemdir. Zamaninda yapilmadiginda isi yonetimi bozulabilir; bu durum performans dususu, ani kapanma, donma ve ilerleyen surecte maliyetli donanim arizalarina yol acabilir. Duzenli bakim uygulandiginda ise sogutma dengesi korunur, sistem kararliligi artar ve parca omru guvence altina alinir.',
            "Bakim bilgisi:\n- Cihaz: {cihaz}\n- Planlanan bakim tarihi: {bakim_tarihi}",
            "Teknik servis surecimiz standart prosedurlerle, kontrollu ve guvenli sekilde yurutulmektedir:\n- Sogutma sistemi detayli olarak temizlenir.\n- Eski termal macun profesyonel yontemle yenilenir.\n- Isi degerleri kontrol edilerek cihaz test edilir.\n- Cihaziniz performans ve stabilite acisindan guvenli durumda teslim edilir.",
            'Amacimiz, ariza olustuktan sonra mudahale etmek degil; ariza riskini onceden ortadan kaldirmaktir. Bu bakim, cihaz performansini korumak ve beklenmedik sorunlari onlemek icin en dogru adimdir.',
            'Uygun oldugunuz tarih ve saat bilgisini paylasmaniz halinde bakim planlamanizi memnuniyetle olusturalim.',
            "Saygilarimizla,\nYalova Bilgisayar Teknik Servis\n0 (226) 352 07 24",
        ]);

        $firmaIdler = DB::table('firmalar')->pluck('id');
        foreach ($firmaIdler as $firmaId) {
            DB::table('teknik_servis_mesaj_sablonlari')->updateOrInsert(
                [
                    'firma_id' => (int) $firmaId,
                    'kanal' => 'whatsapp',
                    'kod' => 'termal_macun_bakim',
                ],
                [
                    'ad' => 'Termal Macun Bakim',
                    'mesaj' => $sablon,
                    'aktif' => true,
                    'siralama' => 10,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('teknik_servis_mesaj_sablonlari');
    }
};
