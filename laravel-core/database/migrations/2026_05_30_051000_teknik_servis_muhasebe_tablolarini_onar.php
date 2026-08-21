<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->muhasebeBaglantilariTablosunuOnar();
        $this->tahsilatTablosunuOnar();
    }

    public function down(): void
    {
        // Onarım migration'ı: rollback sırasında mevcut üretim muhasebe/tahsilat tablolarını düşürmeyiz.
    }

    private function muhasebeBaglantilariTablosunuOnar(): void
    {
        if (! Schema::hasTable('teknik_servis_muhasebe_baglantilari')) {
            Schema::create('teknik_servis_muhasebe_baglantilari', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('firma_id');
                $table->unsignedBigInteger('teknik_servis_kaydi_id');
                $table->string('islem_tipi', 24);
                $table->string('idempotency_key', 128);
                $table->unsignedBigInteger('satis_faturasi_id')->nullable();
                $table->unsignedBigInteger('gider_faturasi_id')->nullable();
                $table->unsignedBigInteger('finans_hareketi_id')->nullable();
                $table->dateTime('son_senkron_tarihi')->nullable();
                $table->string('senkron_durumu', 24)->default('beklemede');
                $table->text('hata_mesaji')->nullable();
                $table->timestamps();

                $table->foreign('firma_id', 'ts_muhasebe_firma_fk')->references('id')->on('firmalar')->restrictOnDelete();
                $table->foreign('teknik_servis_kaydi_id', 'ts_muhasebe_kayit_fk')->references('id')->on('teknik_servis_kayitlari')->restrictOnDelete();
                $table->foreign('satis_faturasi_id', 'ts_muhasebe_sat_fat_fk')->references('id')->on('faturalar')->nullOnDelete();
                $table->foreign('gider_faturasi_id', 'ts_muhasebe_gid_fat_fk')->references('id')->on('faturalar')->nullOnDelete();
                $table->foreign('finans_hareketi_id', 'ts_muhasebe_finans_fk')->references('id')->on('finans_hareketleri')->nullOnDelete();

                $table->unique(['firma_id', 'idempotency_key'], 'ts_muhasebe_firma_idem_uq');
                $table->index(['firma_id', 'teknik_servis_kaydi_id'], 'ts_muhasebe_firma_kayit_idx');
                $table->index(['firma_id', 'islem_tipi'], 'ts_muhasebe_firma_islem_idx');
                $table->index(['firma_id'], 'ts_muhasebe_firma_only');
                $table->index(['teknik_servis_kaydi_id'], 'ts_muhasebe_kayit_only');
                $table->index(['firma_id', 'teknik_servis_kaydi_id', 'islem_tipi', 'id'], 'ts_muhasebe_kayit_islem_id_idx');
            });

            return;
        }

        $this->indexEkle('teknik_servis_muhasebe_baglantilari', ['firma_id'], 'ts_muhasebe_firma_only');
        $this->indexEkle('teknik_servis_muhasebe_baglantilari', ['teknik_servis_kaydi_id'], 'ts_muhasebe_kayit_only');
        $this->indexEkle('teknik_servis_muhasebe_baglantilari', ['firma_id', 'teknik_servis_kaydi_id', 'islem_tipi', 'id'], 'ts_muhasebe_kayit_islem_id_idx');
    }

    private function tahsilatTablosunuOnar(): void
    {
        if (! Schema::hasTable('teknik_servis_tahsilatlari')) {
            Schema::create('teknik_servis_tahsilatlari', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('firma_id')->constrained('firmalar');
                $table->foreignId('teknik_servis_kaydi_id')->constrained('teknik_servis_kayitlari');
                $table->foreignId('satis_faturasi_id')->nullable()->constrained('faturalar');
                $table->foreignId('finans_hareketi_id')->nullable()->constrained('finans_hareketleri');
                $table->foreignId('iptal_finans_hareketi_id')->nullable()->constrained('finans_hareketleri');
                $table->string('kanal', 20);
                $table->foreignId('kasa_hesap_id')->nullable()->constrained('kasa_hesaplari');
                $table->foreignId('banka_hesap_id')->nullable()->constrained('banka_hesaplari');
                $table->foreignId('pos_hesap_id')->nullable()->constrained('pos_hesaplari');
                $table->string('kaynak_para_birimi', 3);
                $table->string('hedef_para_birimi', 3)->nullable();
                $table->string('doviz_kuru_turu', 20)->nullable();
                $table->decimal('doviz_kuru', 18, 8)->nullable();
                $table->decimal('tutar', 18, 2);
                $table->decimal('hedef_tutar', 18, 2)->nullable();
                $table->dateTime('tarih');
                $table->text('aciklama')->nullable();
                $table->string('durum', 20)->default('aktif');
                $table->foreignId('olusturan_id')->nullable()->constrained('users');
                $table->foreignId('guncelleyen_id')->nullable()->constrained('users');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['firma_id', 'teknik_servis_kaydi_id', 'durum'], 'ts_tahsilat_kayit_durum_idx');
                $table->index(['firma_id', 'satis_faturasi_id'], 'ts_tahsilat_fatura_idx');
                $table->index(['firma_id', 'finans_hareketi_id'], 'ts_tahsilat_finans_idx');
                $table->index(['firma_id', 'teknik_servis_kaydi_id', 'deleted_at', 'durum', 'tutar'], 'ts_tahsilat_kayit_sil_durum_tutar_idx');
            });

            return;
        }

        $this->indexEkle('teknik_servis_tahsilatlari', ['firma_id', 'teknik_servis_kaydi_id', 'deleted_at', 'durum', 'tutar'], 'ts_tahsilat_kayit_sil_durum_tutar_idx');
    }

    /**
     * @param  array<int, string>  $kolonlar
     */
    private function indexEkle(string $tablo, array $kolonlar, string $indexAdi): void
    {
        foreach ($kolonlar as $kolon) {
            if (! Schema::hasColumn($tablo, $kolon)) {
                return;
            }
        }

        if ($this->indexVarMi($tablo, $indexAdi)) {
            return;
        }

        Schema::table($tablo, function (Blueprint $table) use ($kolonlar, $indexAdi): void {
            $table->index($kolonlar, $indexAdi);
        });
    }

    private function indexVarMi(string $tablo, string $indexAdi): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $tablo = str_replace('"', '""', $tablo);
            foreach (DB::select('PRAGMA index_list("'.$tablo.'")') as $satir) {
                if ((string) ($satir->name ?? '') === $indexAdi) {
                    return true;
                }
            }

            return false;
        }

        if ($driver !== 'mysql') {
            return false;
        }

        $satir = DB::selectOne(
            'SELECT COUNT(1) AS c FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?',
            [$tablo, $indexAdi]
        );

        return isset($satir->c) && (int) $satir->c > 0;
    }
};
