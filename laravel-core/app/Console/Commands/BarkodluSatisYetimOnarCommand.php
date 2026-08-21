<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BarkodluSatisYetimOnarCommand extends Command
{
    private const TABLE = 'muhasebe_barkodlu_satislar';

    protected $signature = 'muhasebe:barkodlu-satis-yetim-onar
        {--apply : Yetim foreign key alanlarini guvenli sekilde duzeltir}
        {--archive-table=muhasebe_barkodlu_satislar_yetim_arsiv : Arsiv tablo adi}
        {--connection= : Veritabani baglantisi}
        {--firma_id= : Sadece belirtilen firma id icin tarama yapar}
        {--sample=20 : Raporda gosterilecek ornek kayit sayisi}';

    protected $description = 'muhasebe_barkodlu_satislar tablosundaki yetim foreign key alanlarini veri kaybi olmadan onarir.';

    public function handle(): int
    {
        mb_internal_encoding('UTF-8');

        $connectionName = $this->option('connection') ?: config('database.default');
        $connection = DB::connection((string) $connectionName);
        $archiveTable = (string) $this->option('archive-table');
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;
        $sampleSize = max(1, (int) $this->option('sample'));

        $rows = $this->findOrphanRows($connectionName, $firmaId);

        $this->line('Baglanti: '.$connection->getName());
        $this->line('Tablo: '.self::TABLE);
        $this->line('Toplam sorunlu satir: '.$rows->count());

        if ($rows->isEmpty()) {
            $this->info('Yetim foreign key bulunmadi. Constraint ekleme asamasina gecebilirsiniz.');
            $this->printConstraintSql();

            return self::SUCCESS;
        }

        $sampleRows = $rows
            ->take($sampleSize)
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'firma_id' => $row['firma_id'],
                'satis_no' => $row['satis_no'],
                'cari_id' => $row['cari_id'],
                'neden' => 'cari_id -> cariler.id bulunamadi',
            ])
            ->all();

        $this->newLine();
        $this->table(['id', 'firma_id', 'satis_no', 'cari_id', 'neden'], $sampleRows);

        if (! $this->option('apply')) {
            $this->warn('Dry-run tamamlandi. Duzeltmek icin ayni komutu --apply ile tekrar calistirin.');
            $this->printConstraintSql();

            return self::FAILURE;
        }

        $this->ensureArchiveTable($connectionName, $archiveTable);

        $archiveRows = $rows->map(function (array $row): array {
            return [
                'barkodlu_satis_id' => $row['id'],
                'firma_id' => $row['firma_id'],
                'satis_no' => $row['satis_no'],
                'eski_cari_id' => $row['cari_id'],
                'yetim_nedeni' => 'cari_id -> cariler.id bulunamadi',
                'arsivlendi_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        })->values()->all();

        $ids = $rows->pluck('id')->map(fn (int $id): int => $id)->all();

        $archivedCount = 0;
        $updatedCount = 0;

        $connection->transaction(function () use ($connection, $archiveTable, $archiveRows, $ids, &$archivedCount, &$updatedCount): void {
            $archivedCount = $connection->table($archiveTable)->insertOrIgnore($archiveRows);
            $updatedCount = $connection->table(self::TABLE)
                ->whereIn('id', $ids)
                ->update([
                    'cari_id' => null,
                    'updated_at' => now(),
                ]);
        });

        $remainingRows = $this->findOrphanRows($connectionName, $firmaId);

        $this->info(sprintf('Arsive eklenen yeni kayit: %d', $archivedCount));
        $this->info(sprintf('NULL yapilan cari_id sayisi: %d', $updatedCount));
        $this->info(sprintf('Kalan sorunlu satir: %d', $remainingRows->count()));
        $this->printConstraintSql();

        return $remainingRows->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, array{id: int, firma_id: int, satis_no: string, cari_id: int}>
     */
    private function findOrphanRows(string $connectionName, ?int $firmaId): Collection
    {
        $query = DB::connection($connectionName)
            ->table(self::TABLE.' as s')
            ->leftJoin('cariler as c', 'c.id', '=', 's.cari_id')
            ->whereNotNull('s.cari_id')
            ->whereNull('c.id')
            ->select(['s.id', 's.firma_id', 's.satis_no', 's.cari_id'])
            ->orderBy('s.id');

        if ($firmaId !== null) {
            $query->where('s.firma_id', $firmaId);
        }

        return $query->get()->map(fn (object $row): array => [
            'id' => (int) $row->id,
            'firma_id' => (int) $row->firma_id,
            'satis_no' => (string) $row->satis_no,
            'cari_id' => (int) $row->cari_id,
        ])->values();
    }

    private function ensureArchiveTable(string $connectionName, string $archiveTable): void
    {
        $schema = Schema::connection($connectionName);
        $connection = DB::connection($connectionName);

        if (! $schema->hasTable($archiveTable)) {
            $connection->statement(sprintf(
                'CREATE TABLE `%s` (
                    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                    `barkodlu_satis_id` BIGINT UNSIGNED NOT NULL,
                    `firma_id` BIGINT UNSIGNED NOT NULL,
                    `satis_no` VARCHAR(64) NOT NULL,
                    `eski_cari_id` BIGINT UNSIGNED NOT NULL,
                    `yetim_nedeni` VARCHAR(255) NOT NULL,
                    `arsivlendi_at` DATETIME NULL,
                    `created_at` TIMESTAMP NULL,
                    `updated_at` TIMESTAMP NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `%s_uq` (`barkodlu_satis_id`, `eski_cari_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
                $archiveTable,
                $archiveTable
            ));
        }
    }

    private function printConstraintSql(): void
    {
        $this->newLine();
        $this->line('Temizlik sonrasi tekrar calistirilacak SQL:');
        $this->line(
            "ALTER TABLE `muhasebe_barkodlu_satislar`\n".
            "  ADD CONSTRAINT `muhasebe_barkodlu_satislar_cari_id_foreign` FOREIGN KEY (`cari_id`) REFERENCES `cariler` (`id`) ON DELETE SET NULL,\n".
            "  ADD CONSTRAINT `muhasebe_barkodlu_satislar_firma_id_foreign` FOREIGN KEY (`firma_id`) REFERENCES `firmalar` (`id`) ON DELETE CASCADE,\n".
            "  ADD CONSTRAINT `muhasebe_barkodlu_satislar_iptal_eden_id_foreign` FOREIGN KEY (`iptal_eden_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,\n".
            "  ADD CONSTRAINT `muhasebe_barkodlu_satislar_olusturan_id_foreign` FOREIGN KEY (`olusturan_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;"
        );
    }
}
