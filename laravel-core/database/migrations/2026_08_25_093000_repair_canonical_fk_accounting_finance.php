<?php

declare(strict_types=1);

use Database\Migrations\Support\CanonicalForeignKeyRepairSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

require_once __DIR__.'/Support/CanonicalForeignKeyRepairSupport.php';

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            throw new RuntimeException('B04 requires an isolated MariaDB/MySQL database.');
        }

        // The historical migration guarded this nullable conversion with a
        // mysql-only driver check. MariaDB therefore reaches this canonical
        // SET NULL FK with a NOT NULL column on a fresh install. Correct that
        // known compatibility gap before the approved helper enforces its
        // fail-fast SET NULL precondition. Existing production clones already
        // have the nullable column, so this is a no-op there.
        self::prepareMariaDbSetNullCompatibility();

        $helper = new CanonicalForeignKeyRepairSupport(Schema::getConnection());

        foreach (self::manifest() as $definition) {
            $helper->ensureCanonicalForeignKey($definition);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('B04 corrective migration rollback is intentionally unsupported; use verified restore-based recovery.');
    }

    private static function prepareMariaDbSetNullCompatibility(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'mariadb'
            || ! Schema::hasTable('muhasebe_doviz_kurlari')
            || ! Schema::hasColumn('muhasebe_doviz_kurlari', 'firma_id')) {
            return;
        }

        $column = collect(Schema::getColumns('muhasebe_doviz_kurlari'))
            ->first(static fn (array $item): bool => ($item['name'] ?? null) === 'firma_id');

        if (($column['nullable'] ?? true) === true) {
            return;
        }

        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            $table->unsignedBigInteger('firma_id')->nullable()->change();
        });

        // Preserve the historical constraint name while normalizing this
        // known MariaDB-only historical action. The approved helper then
        // correctly accounts for it as a semantic NO-OP rather than treating
        // the compatibility normalization as one of the 30 planned repairs.
        $legacy = collect(Schema::getForeignKeys('muhasebe_doviz_kurlari'))
            ->first(static fn (array $item): bool => ($item['name'] ?? null) === 'muhasebe_doviz_kurlari_firma_id_foreign');

        if (strtoupper((string) ($legacy['on_delete'] ?? '')) !== 'CASCADE') {
            return;
        }

        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            $table->dropForeign('muhasebe_doviz_kurlari_firma_id_foreign');
        });
        Schema::table('muhasebe_doviz_kurlari', function (Blueprint $table): void {
            $table->foreign('firma_id', 'muhasebe_doviz_kurlari_firma_id_foreign')
                ->references('id')
                ->on('firmalar')
                ->onDelete('SET NULL')
                ->onUpdate('RESTRICT');
        });
    }

    /** @return list<array<string, mixed>> */
    private static function manifest(): array
    {
        return [
            ['fk_id' => 'FK-001', 'child_table' => 'fatura_numara_sayaclari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-002', 'child_table' => 'finans_hareketleri', 'child_columns' => ['cari_id'], 'parent_table' => 'cariler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-003', 'child_table' => 'finans_hareketleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-004', 'child_table' => 'finans_hareketleri', 'child_columns' => ['iptal_edilen_hareket_id'], 'parent_table' => 'finans_hareketleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-005', 'child_table' => 'finans_hareketleri', 'child_columns' => ['islem_yapan_kullanici_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-015', 'child_table' => 'kasa_hareketleri', 'child_columns' => ['finans_hareket_id'], 'parent_table' => 'finans_hareketleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-024', 'child_table' => 'muhasebe_barkodlu_satislar', 'child_columns' => ['cari_id'], 'parent_table' => 'cariler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-025', 'child_table' => 'muhasebe_barkodlu_satislar', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-026', 'child_table' => 'muhasebe_barkodlu_satislar', 'child_columns' => ['iptal_eden_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-027', 'child_table' => 'muhasebe_barkodlu_satislar', 'child_columns' => ['olusturan_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-028', 'child_table' => 'muhasebe_barkodlu_satis_iadeler', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-029', 'child_table' => 'muhasebe_barkodlu_satis_iadeler', 'child_columns' => ['olusturan_id'], 'parent_table' => 'users', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-030', 'child_table' => 'muhasebe_barkodlu_satis_iadeler', 'child_columns' => ['satis_id'], 'parent_table' => 'muhasebe_barkodlu_satislar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-031', 'child_table' => 'muhasebe_barkodlu_satis_iade_kalemleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-032', 'child_table' => 'muhasebe_barkodlu_satis_iade_kalemleri', 'child_columns' => ['iade_id'], 'parent_table' => 'muhasebe_barkodlu_satis_iadeler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-033', 'child_table' => 'muhasebe_barkodlu_satis_iade_kalemleri', 'child_columns' => ['satis_kalem_id'], 'parent_table' => 'muhasebe_barkodlu_satis_kalemleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-034', 'child_table' => 'muhasebe_barkodlu_satis_iade_kalemleri', 'child_columns' => ['stok_id'], 'parent_table' => 'stok_kartlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-035', 'child_table' => 'muhasebe_barkodlu_satis_kalemleri', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-036', 'child_table' => 'muhasebe_barkodlu_satis_kalemleri', 'child_columns' => ['satis_id'], 'parent_table' => 'muhasebe_barkodlu_satislar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-037', 'child_table' => 'muhasebe_barkodlu_satis_kalemleri', 'child_columns' => ['stok_id'], 'parent_table' => 'stok_kartlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-039', 'child_table' => 'muhasebe_cari_gruplari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-040', 'child_table' => 'muhasebe_doviz_kurlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-053', 'child_table' => 'muhasebe_vergi_oranlari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-054', 'child_table' => 'odemeler', 'child_columns' => ['siparis_id'], 'parent_table' => 'siparisler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-058', 'child_table' => 'pos_hareketleri', 'child_columns' => ['finans_hareket_id'], 'parent_table' => 'finans_hareketleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-082', 'child_table' => 'siparis_gecmisleri', 'child_columns' => ['siparis_id'], 'parent_table' => 'siparisler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-083', 'child_table' => 'siparis_kalemleri', 'child_columns' => ['siparis_id'], 'parent_table' => 'siparisler', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-150', 'child_table' => 'teknik_servis_muhasebe_baglantilari', 'child_columns' => ['finans_hareketi_id'], 'parent_table' => 'finans_hareketleri', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-151', 'child_table' => 'teknik_servis_muhasebe_baglantilari', 'child_columns' => ['firma_id'], 'parent_table' => 'firmalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-152', 'child_table' => 'teknik_servis_muhasebe_baglantilari', 'child_columns' => ['gider_faturasi_id'], 'parent_table' => 'faturalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-153', 'child_table' => 'teknik_servis_muhasebe_baglantilari', 'child_columns' => ['teknik_servis_kaydi_id'], 'parent_table' => 'teknik_servis_kayitlari', 'parent_columns' => ['id'], 'canonical_on_delete' => 'RESTRICT', 'canonical_on_update' => 'RESTRICT'],
            ['fk_id' => 'FK-154', 'child_table' => 'teknik_servis_muhasebe_baglantilari', 'child_columns' => ['satis_faturasi_id'], 'parent_table' => 'faturalar', 'parent_columns' => ['id'], 'canonical_on_delete' => 'SET NULL', 'canonical_on_update' => 'RESTRICT'],
        ];
    }
};
