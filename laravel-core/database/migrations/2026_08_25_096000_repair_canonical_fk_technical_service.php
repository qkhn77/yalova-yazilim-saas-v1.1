<?php

declare(strict_types=1);

use Database\Migrations\Support\CanonicalForeignKeyRepairSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/Support/CanonicalForeignKeyRepairSupport.php';

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            throw new RuntimeException('B07 requires an isolated MariaDB/MySQL database.');
        }

        $helper = new CanonicalForeignKeyRepairSupport(Schema::getConnection());

        foreach (self::manifest() as $definition) {
            $helper->ensureCanonicalForeignKey($definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('B07 corrective migration rollback is intentionally unsupported; use verified restore-based recovery.');
    }

    /** @return list<array<string, mixed>> */
    private static function manifest(): array
    {
        return [
            ['fk_id' => 'FK-103', 'child_table' => 'teknik_servis_aksesuar_kayitlari', 'child_columns' => ['aksesuar_id'], 'parent_table' => 'teknik_servis_tanim_aksesuarlar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-104', 'child_table' => 'teknik_servis_aksesuar_kayitlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-105', 'child_table' => 'teknik_servis_aksesuar_kayitlari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-106', 'child_table' => 'teknik_servis_ariza_kayitlari', 'child_columns' => ['ariza_id'], 'parent_table' => 'teknik_servis_tanim_arizalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-107', 'child_table' => 'teknik_servis_ariza_kayitlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-108', 'child_table' => 'teknik_servis_ariza_kayitlari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-110', 'child_table' => 'teknik_servis_dokumanlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-111', 'child_table' => 'teknik_servis_dokumanlari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-112', 'child_table' => 'teknik_servis_dokumanlari', 'child_columns' => ['yukleyen_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-113', 'child_table' => 'teknik_servis_durum_gecmisleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-114', 'child_table' => 'teknik_servis_durum_gecmisleri', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-115', 'child_table' => 'teknik_servis_durum_gecmisleri', 'child_columns' => ['onceki_servis_durumu_id'], 'parent_table' => 'teknik_servis_tanim_servis_durumlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-116', 'child_table' => 'teknik_servis_durum_gecmisleri', 'child_columns' => ['degistiren_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-117', 'child_table' => 'teknik_servis_durum_gecmisleri', 'child_columns' => ['yeni_servis_durumu_id'], 'parent_table' => 'teknik_servis_tanim_servis_durumlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-118', 'child_table' => 'teknik_servis_fis_numaralari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-119', 'child_table' => 'teknik_servis_gorev_atamalari', 'child_columns' => ['atanan_kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-120', 'child_table' => 'teknik_servis_gorev_atamalari', 'child_columns' => ['atayan_kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-121', 'child_table' => 'teknik_servis_gorev_atamalari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-122', 'child_table' => 'teknik_servis_gorev_atamalari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-123', 'child_table' => 'teknik_servis_hatirlatmalari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-124', 'child_table' => 'teknik_servis_hatirlatmalari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-125', 'child_table' => 'teknik_servis_hatirlatmalari', 'child_columns' => ['olusturan_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-126', 'child_table' => 'teknik_servis_islem_loglari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-127', 'child_table' => 'teknik_servis_islem_loglari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-128', 'child_table' => 'teknik_servis_islem_loglari', 'child_columns' => ['kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-129', 'child_table' => 'teknik_servis_kalemleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-130', 'child_table' => 'teknik_servis_kalemleri', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-131', 'child_table' => 'teknik_servis_kalemleri', 'child_columns' => ['stok_id'], 'parent_table' => 'stok_kartlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-132', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['ariza_id'], 'parent_table' => 'teknik_servis_tanim_arizalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-133', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['cari_id'], 'parent_table' => 'cariler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-134', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['cihaz_id'], 'parent_table' => 'teknik_servis_tanim_cihazlar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-135', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-136', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['guncelleyen_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-137', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['marka_id'], 'parent_table' => 'teknik_servis_tanim_markalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-138', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['olusturan_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-139', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['servis_durumu_id'], 'parent_table' => 'teknik_servis_tanim_servis_durumlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-140', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['teslim_eden_kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-141', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['tahsilat_banka_hesap_id'], 'parent_table' => 'banka_hesaplari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-142', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['tahsilat_kasa_hesap_id'], 'parent_table' => 'kasa_hesaplari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-143', 'child_table' => 'teknik_servis_kayitlari', 'child_columns' => ['tahsilat_pos_hesap_id'], 'parent_table' => 'pos_hesaplari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-144', 'child_table' => 'teknik_servis_kayitli_cihaz_degisiklikleri', 'child_columns' => ['kayitli_cihaz_id'], 'parent_table' => 'teknik_servis_kayitli_cihazlar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-145', 'child_table' => 'teknik_servis_kayitli_cihaz_degisiklikleri', 'child_columns' => ['kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-146', 'child_table' => 'teknik_servis_mesaj_loglari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-147', 'child_table' => 'teknik_servis_mesaj_loglari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-148', 'child_table' => 'teknik_servis_mesaj_loglari', 'child_columns' => ['gonderen_kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-155', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['banka_hesap_id'], 'parent_table' => 'banka_hesaplari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-156', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['finans_hareketi_id'], 'parent_table' => 'finans_hareketleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-157', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-158', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['guncelleyen_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-159', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['iptal_finans_hareketi_id'], 'parent_table' => 'finans_hareketleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-160', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['kasa_hesap_id'], 'parent_table' => 'kasa_hesaplari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-161', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['olusturan_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-162', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['pos_hesap_id'], 'parent_table' => 'pos_hesaplari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-163', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['satis_faturasi_id'], 'parent_table' => 'faturalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-164', 'child_table' => 'teknik_servis_tahsilatlari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-165', 'child_table' => 'teknik_servis_tanim_aksesuarlar', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-166', 'child_table' => 'teknik_servis_tanim_arizalar', 'child_columns' => ['cihaz_id'], 'parent_table' => 'teknik_servis_tanim_cihazlar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-167', 'child_table' => 'teknik_servis_tanim_arizalar', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-168', 'child_table' => 'teknik_servis_tanim_cihazlar', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-169', 'child_table' => 'teknik_servis_tanim_markalar', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-170', 'child_table' => 'teknik_servis_tanim_servis_durumlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
        ];
    }
};

