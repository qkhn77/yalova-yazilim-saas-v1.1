<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('masraf_kategorileri', function (Blueprint $table): void {
            $table->foreignId('ust_kategori_id')
                ->nullable()
                ->after('firma_id')
                ->constrained('masraf_kategorileri')
                ->nullOnDelete();
            $table->boolean('sistem_mi')->default(false)->after('sira')->index();
            $table->boolean('secilir_mi')->default(true)->after('sistem_mi')->index();

            $table->index(['firma_id', 'ust_kategori_id', 'aktif_mi', 'sira'], 'masraf_kategori_hiyerarsi_index');
        });

        Schema::table('masraflar', function (Blueprint $table): void {
            $table->string('kaynak_turu', 64)->nullable()->after('masraf_kategorisi_id');
            $table->unsignedBigInteger('kaynak_id')->nullable()->after('kaynak_turu');

            $table->index(['firma_id', 'kaynak_turu', 'kaynak_id'], 'masraf_kaynak_index');
        });

        Schema::create('masraf_fatura_dagitilari', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('firma_id')->constrained('firmalar')->cascadeOnDelete();
            $table->foreignId('masraf_id')->constrained('masraflar')->cascadeOnDelete();
            $table->foreignId('fatura_id')->constrained('faturalar')->restrictOnDelete();
            $table->decimal('tutar', 18, 2);
            $table->char('para_birimi', 3)->default('TRY');
            $table->timestamps();

            $table->unique(['firma_id', 'masraf_id', 'fatura_id'], 'masraf_fatura_dagitim_unique');
            $table->index(['firma_id', 'fatura_id']);
            $table->index(['firma_id', 'masraf_id']);
        });

        $this->mevcutFirmalariHiyerarsiyeTasi();
    }

    public function down(): void
    {
        Schema::dropIfExists('masraf_fatura_dagitilari');

        Schema::table('masraflar', function (Blueprint $table): void {
            $table->dropIndex('masraf_kaynak_index');
            $table->dropColumn(['kaynak_turu', 'kaynak_id']);
        });

        Schema::table('masraf_kategorileri', function (Blueprint $table): void {
            $table->dropForeign(['ust_kategori_id']);
            $table->dropIndex('masraf_kategori_hiyerarsi_index');
            $table->dropColumn(['ust_kategori_id', 'sistem_mi', 'secilir_mi']);
        });
    }

    private function mevcutFirmalariHiyerarsiyeTasi(): void
    {
        $firmalar = DB::table('firmalar')->pluck('id');

        foreach ($firmalar as $firmaId) {
            $firmaId = (int) $firmaId;

            $legacyTelefon = DB::table('masraf_kategorileri')
                ->where('firma_id', $firmaId)
                ->where('kod', 'telefon_internet')
                ->first();
            $telefon = DB::table('masraf_kategorileri')
                ->where('firma_id', $firmaId)
                ->where('kod', 'telefon')
                ->exists();

            if ($legacyTelefon && ! $telefon) {
                DB::table('masraf_kategorileri')
                    ->where('id', $legacyTelefon->id)
                    ->update([
                        'kod' => 'telefon',
                        'ad' => 'Telefon',
                        'updated_at' => now(),
                    ]);
            }

            foreach ($this->sabitKategoriler() as $anaKategori) {
                $ustId = $this->kategoriOlustur(
                    $firmaId,
                    $anaKategori['kod'],
                    $anaKategori['ad'],
                    $anaKategori['sira'],
                    null,
                    false,
                );

                foreach ($anaKategori['alt_turler'] as $altTur) {
                    $this->kategoriOlustur(
                        $firmaId,
                        $altTur['kod'],
                        $altTur['ad'],
                        $altTur['sira'],
                        $ustId,
                        true,
                    );
                }
            }
        }
    }

    private function kategoriOlustur(
        int $firmaId,
        string $kod,
        string $ad,
        int $sira,
        ?int $ustKategoriId,
        bool $secilirMi,
    ): int {
        $kategori = DB::table('masraf_kategorileri')
            ->where('firma_id', $firmaId)
            ->where('kod', $kod)
            ->first();

        if ($kategori) {
            DB::table('masraf_kategorileri')
                ->where('id', $kategori->id)
                ->update([
                    'ust_kategori_id' => $ustKategoriId,
                    'sistem_mi' => true,
                    'secilir_mi' => $secilirMi,
                    'updated_at' => now(),
                ]);

            return (int) $kategori->id;
        }

        return (int) DB::table('masraf_kategorileri')->insertGetId([
            'firma_id' => $firmaId,
            'ust_kategori_id' => $ustKategoriId,
            'kod' => $kod,
            'ad' => $ad,
            'sira' => $sira,
            'sistem_mi' => true,
            'secilir_mi' => $secilirMi,
            'aktif_mi' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** @return array<int, array{kod:string, ad:string, sira:int, alt_turler:array<int, array{kod:string, ad:string, sira:int}>}> */
    private function sabitKategoriler(): array
    {
        return [
            ['kod' => 'duzenli_faturalar', 'ad' => 'Düzenli Faturalar', 'sira' => 10, 'alt_turler' => [
                ['kod' => 'elektrik', 'ad' => 'Elektrik', 'sira' => 10],
                ['kod' => 'su', 'ad' => 'Su', 'sira' => 20],
                ['kod' => 'dogalgaz', 'ad' => 'Doğalgaz', 'sira' => 30],
                ['kod' => 'telefon', 'ad' => 'Telefon', 'sira' => 40],
                ['kod' => 'internet', 'ad' => 'İnternet', 'sira' => 50],
                ['kod' => 'hosting_domain', 'ad' => 'Hosting / Domain', 'sira' => 60],
            ]],
            ['kod' => 'personel_giderleri', 'ad' => 'Personel Giderleri', 'sira' => 20, 'alt_turler' => [
                ['kod' => 'personel', 'ad' => 'Personel', 'sira' => 10],
                ['kod' => 'maas', 'ad' => 'Maaş', 'sira' => 20],
                ['kod' => 'sgk', 'ad' => 'SGK', 'sira' => 30],
                ['kod' => 'personel_yemek', 'ad' => 'Yemek', 'sira' => 40],
                ['kod' => 'personel_yol', 'ad' => 'Yol', 'sira' => 50],
                ['kod' => 'personel_prim', 'ad' => 'Prim', 'sira' => 60],
                ['kod' => 'personel_egitim', 'ad' => 'Eğitim', 'sira' => 70],
            ]],
            ['kod' => 'arac_ve_ulasim', 'ad' => 'Araç ve Ulaşım', 'sira' => 30, 'alt_turler' => [
                ['kod' => 'arac', 'ad' => 'Araç', 'sira' => 10],
                ['kod' => 'yakit', 'ad' => 'Yakıt', 'sira' => 20],
                ['kod' => 'arac_bakim', 'ad' => 'Bakım', 'sira' => 30],
                ['kod' => 'arac_onarim', 'ad' => 'Onarım', 'sira' => 40],
                ['kod' => 'arac_sigorta', 'ad' => 'Sigorta', 'sira' => 50],
                ['kod' => 'mtv', 'ad' => 'MTV', 'sira' => 60],
                ['kod' => 'otopark', 'ad' => 'Otopark', 'sira' => 70],
                ['kod' => 'kopru_otoyol', 'ad' => 'Köprü / Otoyol', 'sira' => 80],
            ]],
            ['kod' => 'kira_ve_isletme', 'ad' => 'Kira ve İşletme', 'sira' => 40, 'alt_turler' => [
                ['kod' => 'kira', 'ad' => 'Kira', 'sira' => 10],
                ['kod' => 'aidat', 'ad' => 'Aidat', 'sira' => 20],
                ['kod' => 'isletme_sigortasi', 'ad' => 'İşletme Sigortası', 'sira' => 30],
                ['kod' => 'ruhsat', 'ad' => 'Ruhsat', 'sira' => 40],
                ['kod' => 'vergi_harc', 'ad' => 'Vergi / Harç', 'sira' => 50],
            ]],
            ['kod' => 'ofis_ve_sarf', 'ad' => 'Ofis ve Sarf', 'sira' => 50, 'alt_turler' => [
                ['kod' => 'ofis', 'ad' => 'Ofis', 'sira' => 10],
                ['kod' => 'kirtasiye', 'ad' => 'Kırtasiye', 'sira' => 20],
                ['kod' => 'temizlik', 'ad' => 'Temizlik', 'sira' => 30],
                ['kod' => 'mutfak', 'ad' => 'Mutfak', 'sira' => 40],
                ['kod' => 'buro_malzemeleri', 'ad' => 'Büro Malzemeleri', 'sira' => 50],
            ]],
            ['kod' => 'teknik_servis_operasyon', 'ad' => 'Teknik Servis ve Operasyon', 'sira' => 60, 'alt_turler' => [
                ['kod' => 'bakim_onarim', 'ad' => 'Bakım / Onarım', 'sira' => 10],
                ['kod' => 'taseron', 'ad' => 'Taşeron', 'sira' => 20],
                ['kod' => 'kurye', 'ad' => 'Kurye', 'sira' => 30],
                ['kod' => 'servis_yol', 'ad' => 'Servis Yol Gideri', 'sira' => 40],
                ['kod' => 'kucuk_malzeme', 'ad' => 'Küçük Malzeme', 'sira' => 50],
            ]],
            ['kod' => 'pazarlama_ve_satis', 'ad' => 'Pazarlama ve Satış', 'sira' => 70, 'alt_turler' => [
                ['kod' => 'pazarlama', 'ad' => 'Pazarlama', 'sira' => 10],
                ['kod' => 'reklam', 'ad' => 'Reklam', 'sira' => 20],
                ['kod' => 'sosyal_medya', 'ad' => 'Sosyal Medya', 'sira' => 30],
                ['kod' => 'baski', 'ad' => 'Baskı', 'sira' => 40],
                ['kod' => 'web_sitesi', 'ad' => 'Web Sitesi', 'sira' => 50],
                ['kod' => 'musteri_ikrami', 'ad' => 'Müşteri İkramı', 'sira' => 60],
            ]],
            ['kod' => 'finans_ve_banka', 'ad' => 'Finans ve Banka', 'sira' => 80, 'alt_turler' => [
                ['kod' => 'banka_masrafi', 'ad' => 'Banka Masrafı', 'sira' => 10],
                ['kod' => 'pos_komisyonu', 'ad' => 'POS Komisyonu', 'sira' => 20],
                ['kod' => 'havale_eft', 'ad' => 'Havale / EFT', 'sira' => 30],
                ['kod' => 'kredi_faizi', 'ad' => 'Kredi Faizi', 'sira' => 40],
            ]],
            ['kod' => 'muhasebe_ve_hukuk', 'ad' => 'Muhasebe ve Hukuk', 'sira' => 90, 'alt_turler' => [
                ['kod' => 'mali_musavir', 'ad' => 'Mali Müşavir', 'sira' => 10],
                ['kod' => 'noter', 'ad' => 'Noter', 'sira' => 20],
                ['kod' => 'danismanlik', 'ad' => 'Danışmanlık', 'sira' => 30],
                ['kod' => 'hukuk', 'ad' => 'Hukuk', 'sira' => 40],
            ]],
            ['kod' => 'diger_grubu', 'ad' => 'Diğer', 'sira' => 999, 'alt_turler' => [
                ['kod' => 'diger', 'ad' => 'Diğer', 'sira' => 10],
            ]],
        ];
    }
};
