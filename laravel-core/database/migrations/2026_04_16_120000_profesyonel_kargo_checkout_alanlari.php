<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ecommerce_kargo_yontemleri')) {
            Schema::table('ecommerce_kargo_yontemleri', function (Blueprint $table): void {
                if (! Schema::hasColumn('ecommerce_kargo_yontemleri', 'kod')) {
                    $table->string('kod', 80)->nullable()->after('ad');
                }

                if (! Schema::hasColumn('ecommerce_kargo_yontemleri', 'hizmet_tipi')) {
                    $table->string('hizmet_tipi', 80)->nullable()->after('tip');
                }

                if (! Schema::hasColumn('ecommerce_kargo_yontemleri', 'para_birimi')) {
                    $table->string('para_birimi', 3)->default('TRY')->after('aktif_mi');
                }

                if (! Schema::hasColumn('ecommerce_kargo_yontemleri', 'yurt_ici_aktif')) {
                    $table->boolean('yurt_ici_aktif')->default(true)->after('aktif_mi');
                }

                if (! Schema::hasColumn('ecommerce_kargo_yontemleri', 'yurt_disi_aktif')) {
                    $table->boolean('yurt_disi_aktif')->default(false)->after('yurt_ici_aktif');
                }

                if (! Schema::hasColumn('ecommerce_kargo_yontemleri', 'sira')) {
                    $table->unsignedSmallInteger('sira')->default(100)->after('tahmini_teslim_gun');
                }
            });
        }

        if (Schema::hasTable('siparisler')) {
            Schema::table('siparisler', function (Blueprint $table): void {
                if (! Schema::hasColumn('siparisler', 'kargo_yontemi_id')) {
                    $table->unsignedBigInteger('kargo_yontemi_id')->nullable()->after('odeme_deneme_sayisi');
                }

                if (! Schema::hasColumn('siparisler', 'kargo_ucreti')) {
                    $table->decimal('kargo_ucreti', 14, 2)->default(0)->after('kargo_yontemi_id');
                }

                if (! Schema::hasColumn('siparisler', 'kargo_para_birimi')) {
                    $table->string('kargo_para_birimi', 3)->default('TRY')->after('kargo_ucreti');
                }

                if (! Schema::hasColumn('siparisler', 'teslimat_ulke')) {
                    $table->string('teslimat_ulke', 2)->default('TR')->after('teslimat_adresi');
                }

                if (! Schema::hasColumn('siparisler', 'teslimat_il')) {
                    $table->string('teslimat_il', 120)->nullable()->after('teslimat_ulke');
                }

                if (! Schema::hasColumn('siparisler', 'teslimat_ilce')) {
                    $table->string('teslimat_ilce', 120)->nullable()->after('teslimat_il');
                }

                if (! Schema::hasColumn('siparisler', 'teslimat_posta_kodu')) {
                    $table->string('teslimat_posta_kodu', 20)->nullable()->after('teslimat_ilce');
                }
            });
        }

        $this->varsayilanKargoYontemleriniEkle();
    }

    public function down(): void
    {
        if (Schema::hasTable('siparisler')) {
            Schema::table('siparisler', function (Blueprint $table): void {
                foreach ([
                    'kargo_yontemi_id',
                    'kargo_ucreti',
                    'kargo_para_birimi',
                    'teslimat_ulke',
                    'teslimat_il',
                    'teslimat_ilce',
                    'teslimat_posta_kodu',
                ] as $column) {
                    if (Schema::hasColumn('siparisler', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('ecommerce_kargo_yontemleri')) {
            Schema::table('ecommerce_kargo_yontemleri', function (Blueprint $table): void {
                foreach (['kod', 'hizmet_tipi', 'para_birimi', 'yurt_ici_aktif', 'yurt_disi_aktif', 'sira'] as $column) {
                    if (Schema::hasColumn('ecommerce_kargo_yontemleri', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    private function varsayilanKargoYontemleriniEkle(): void
    {
        if (! Schema::hasTable('firmalar') || ! Schema::hasTable('ecommerce_kargo_yontemleri')) {
            return;
        }

        $firmaIds = DB::table('firmalar')->pluck('id');

        foreach ($firmaIds as $firmaId) {
            $this->kargoYontemiOlusturVeyaGuncelle((int) $firmaId, [
                'kod' => 'aras-kargo-standart',
                'ad' => 'Aras Kargo',
                'entegrasyon' => 'aras',
                'hizmet_tipi' => 'standart',
                'sabit_ucret' => 149.90,
                'ucretsiz_esik' => 3000,
                'tahmini_teslim_gun' => 2,
                'yurt_ici_aktif' => true,
                'yurt_disi_aktif' => false,
                'sira' => 10,
            ]);

            $this->kargoYontemiOlusturVeyaGuncelle((int) $firmaId, [
                'kod' => 'ups-kargo-ekspres',
                'ad' => 'UPS Kargo',
                'entegrasyon' => 'ups',
                'hizmet_tipi' => 'ekspres',
                'sabit_ucret' => 249.90,
                'ucretsiz_esik' => 5000,
                'tahmini_teslim_gun' => 1,
                'yurt_ici_aktif' => true,
                'yurt_disi_aktif' => true,
                'sira' => 20,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function kargoYontemiOlusturVeyaGuncelle(int $firmaId, array $data): void
    {
        $mevcut = DB::table('ecommerce_kargo_yontemleri')
            ->where('firma_id', $firmaId)
            ->where('kod', $data['kod'])
            ->first();

        $payload = [
            'firma_id' => $firmaId,
            'ad' => $data['ad'],
            'kod' => $data['kod'],
            'tip' => 'sabit',
            'hizmet_tipi' => $data['hizmet_tipi'],
            'aktif_mi' => true,
            'yurt_ici_aktif' => $data['yurt_ici_aktif'],
            'yurt_disi_aktif' => $data['yurt_disi_aktif'],
            'para_birimi' => 'TRY',
            'sabit_ucret' => $data['sabit_ucret'],
            'ucretsiz_esik' => $data['ucretsiz_esik'],
            'tahmini_teslim_gun' => $data['tahmini_teslim_gun'],
            'sira' => $data['sira'],
            'entegrasyon_aktif' => false,
            'entegrasyon' => $data['entegrasyon'],
            'entegrasyon_ayarlar' => json_encode([
                'musteri_no' => null,
                'api_key' => null,
                'api_secret' => null,
                'test_modu' => true,
            ], JSON_UNESCAPED_UNICODE),
            'kural' => json_encode([
                'aciklama' => 'Sabit kargo ücreti ve ücretsiz kargo eşiği ile çalışır.',
            ], JSON_UNESCAPED_UNICODE),
            'bolge_kurali' => json_encode([
                'ulkeler' => $data['yurt_disi_aktif'] ? ['TR', 'international'] : ['TR'],
                'iller' => null,
            ], JSON_UNESCAPED_UNICODE),
            'iade_kargo_aktif' => true,
            'iade_kargo_ayarlar' => json_encode([
                'kod_sablon' => strtoupper((string) $data['entegrasyon']).'-IADE-{siparis_no}',
            ], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ];

        if ($mevcut) {
            DB::table('ecommerce_kargo_yontemleri')
                ->where('id', $mevcut->id)
                ->update($payload);

            return;
        }

        $payload['created_at'] = now();
        DB::table('ecommerce_kargo_yontemleri')->insert($payload);
    }
};
