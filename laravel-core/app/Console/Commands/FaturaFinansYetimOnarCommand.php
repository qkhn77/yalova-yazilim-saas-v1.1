<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FaturaFinansYetimOnarCommand extends Command
{
    private const CHILD_TABLE = 'fatura_finans_kapatmalari';

    /**
     * @var array<int, array{column: string, parent: string, constraint: string}>
     */
    private const FOREIGN_KEY_CHECKS = [
        [
            'column' => 'finans_hareket_id',
            'parent' => 'finans_hareketleri',
            'constraint' => 'fatura_finans_kapatmalari_finans_hareket_id_foreign',
        ],
        [
            'column' => 'fatura_id',
            'parent' => 'faturalar',
            'constraint' => 'fatura_finans_kapatmalari_fatura_id_foreign',
        ],
        [
            'column' => 'firma_id',
            'parent' => 'firmalar',
            'constraint' => 'fatura_finans_kapatmalari_firma_id_foreign',
        ],
    ];

    protected $signature = 'muhasebe:fatura-finans-yetim-onar
        {--apply : Yetim kayitlari arsiv tabloya tasir ve ana tablodan kaldirir}
        {--archive-table=fatura_finans_kapatmalari_yetim_arsiv : Arsiv tablo adi}
        {--connection= : Veritabani baglantisi}
        {--firma_id= : Sadece belirtilen firma id icin tarama yapar}
        {--sample=20 : Raporda gosterilecek ornek kayit sayisi}';

    protected $description = 'fatura_finans_kapatmalari tablosundaki yetim kayitlari veri kaybi olmadan arsivler ve FK eklenebilir hale getirir.';

    public function handle(): int
    {
        mb_internal_encoding('UTF-8');

        $connectionName = $this->option('connection') ?: config('database.default');
        $connection = DB::connection((string) $connectionName);
        $archiveTable = (string) $this->option('archive-table');
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;
        $sampleSize = max(1, (int) $this->option('sample'));

        $rows = $this->findOrphanRows($connection, $firmaId);
        $reasonCounts = $this->calculateReasonCounts($rows);

        $this->line('Baglanti: '.$connection->getName());
        $this->line('Tablo: '.self::CHILD_TABLE);
        $this->line('Toplam yetim satir: '.$rows->count());

        foreach ($reasonCounts as $column => $count) {
            $this->line(sprintf('- %s eksik parent: %d', $column, $count));
        }

        if ($rows->isEmpty()) {
            $this->info('Yetim kayit bulunmadi. Foreign key ekleme asamasina gecebilirsiniz.');
            $this->printConstraintSql();

            return self::SUCCESS;
        }

        $sampleRows = $rows
            ->take($sampleSize)
            ->map(fn (array $row): array => [
                'id' => $row['id'],
                'firma_id' => $row['firma_id'],
                'fatura_id' => $row['fatura_id'],
                'finans_hareket_id' => $row['finans_hareket_id'],
                'nedenler' => implode(', ', $row['orphan_reasons']),
            ])
            ->all();

        $this->newLine();
        $this->table(['id', 'firma_id', 'fatura_id', 'finans_hareket_id', 'nedenler'], $sampleRows);

        if (! $this->option('apply')) {
            $this->warn('Dry-run tamamlandi. Arsivleyip temizlemek icin ayni komutu --apply ile tekrar calistirin.');
            $this->printConstraintSql();

            return self::FAILURE;
        }

        $this->ensureArchiveTable($connectionName, $archiveTable);

        $archivedCount = 0;
        $deletedCount = 0;
        $rowsToArchive = $rows->map(function (array $row): array {
            $record = $row['record'];
            $record['kaynak_tablo'] = self::CHILD_TABLE;
            $record['yetim_nedenleri'] = json_encode($row['orphan_reasons'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $record['arsivlendi_at'] = now();

            return $record;
        })->values()->all();

        $rowIds = $rows->pluck('id')->map(fn (int $id): int => $id)->all();

        $connection->transaction(function () use ($connection, $archiveTable, $rowsToArchive, $rowIds, &$archivedCount, &$deletedCount): void {
            $archivedCount = $connection->table($archiveTable)->insertOrIgnore($rowsToArchive);
            $deletedCount = $connection->table(self::CHILD_TABLE)->whereIn('id', $rowIds)->delete();
        });

        $remainingRows = $this->findOrphanRows($connection, $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null);

        $this->info(sprintf('Arsive eklenen yeni kayit: %d', $archivedCount));
        $this->info(sprintf('Ana tablodan kaldirilan kayit: %d', $deletedCount));
        $this->info(sprintf('Kalan yetim kayit: %d', $remainingRows->count()));
        $this->printConstraintSql();

        return $remainingRows->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     firma_id: int|null,
     *     fatura_id: int|null,
     *     finans_hareket_id: int|null,
     *     orphan_reasons: array<int, string>,
     *     record: array<string, mixed>
     * }>
     */
    private function findOrphanRows(ConnectionInterface $connection, ?int $firmaId): Collection
    {
        $query = $connection->table(self::CHILD_TABLE.' as ffk')
            ->leftJoin('finans_hareketleri as fh', 'fh.id', '=', 'ffk.finans_hareket_id')
            ->leftJoin('faturalar as f', 'f.id', '=', 'ffk.fatura_id')
            ->leftJoin('firmalar as fi', 'fi.id', '=', 'ffk.firma_id')
            ->select([
                'ffk.*',
                DB::raw('fh.id as _exists_finans_hareket_id'),
                DB::raw('f.id as _exists_fatura_id'),
                DB::raw('fi.id as _exists_firma_id'),
            ])
            ->where(function ($where): void {
                $where
                    ->where(function ($q): void {
                        $q->whereNotNull('ffk.finans_hareket_id')->whereNull('fh.id');
                    })
                    ->orWhere(function ($q): void {
                        $q->whereNotNull('ffk.fatura_id')->whereNull('f.id');
                    })
                    ->orWhere(function ($q): void {
                        $q->whereNotNull('ffk.firma_id')->whereNull('fi.id');
                    });
            })
            ->orderBy('ffk.id');

        if ($firmaId !== null) {
            $query->where('ffk.firma_id', $firmaId);
        }

        return $query->get()->map(function (object $row): array {
            $record = (array) $row;
            $reasons = [];

            if ($row->finans_hareket_id !== null && $row->_exists_finans_hareket_id === null) {
                $reasons[] = 'finans_hareket_id -> finans_hareketleri.id bulunamadi';
            }

            if ($row->fatura_id !== null && $row->_exists_fatura_id === null) {
                $reasons[] = 'fatura_id -> faturalar.id bulunamadi';
            }

            if ($row->firma_id !== null && $row->_exists_firma_id === null) {
                $reasons[] = 'firma_id -> firmalar.id bulunamadi';
            }

            unset(
                $record['_exists_finans_hareket_id'],
                $record['_exists_fatura_id'],
                $record['_exists_firma_id']
            );

            return [
                'id' => (int) $row->id,
                'firma_id' => $row->firma_id !== null ? (int) $row->firma_id : null,
                'fatura_id' => $row->fatura_id !== null ? (int) $row->fatura_id : null,
                'finans_hareket_id' => $row->finans_hareket_id !== null ? (int) $row->finans_hareket_id : null,
                'orphan_reasons' => $reasons,
                'record' => $record,
            ];
        })->values();
    }

    /**
     * @param  Collection<int, array{id: int, orphan_reasons: array<int, string>}>  $rows
     * @return array<string, int>
     */
    private function calculateReasonCounts(Collection $rows): array
    {
        $counts = [
            'finans_hareket_id' => 0,
            'fatura_id' => 0,
            'firma_id' => 0,
        ];

        foreach ($rows as $row) {
            foreach ($row['orphan_reasons'] as $reason) {
                if (str_starts_with($reason, 'finans_hareket_id')) {
                    $counts['finans_hareket_id']++;
                } elseif (str_starts_with($reason, 'fatura_id')) {
                    $counts['fatura_id']++;
                } elseif (str_starts_with($reason, 'firma_id')) {
                    $counts['firma_id']++;
                }
            }
        }

        return $counts;
    }

    private function ensureArchiveTable(string $connectionName, string $archiveTable): void
    {
        $schema = Schema::connection($connectionName);
        $connection = DB::connection($connectionName);

        if (! $schema->hasTable($archiveTable)) {
            $connection->statement(sprintf(
                'CREATE TABLE `%s` LIKE `%s`',
                $archiveTable,
                self::CHILD_TABLE
            ));
        }

        if (! $schema->hasColumn($archiveTable, 'kaynak_tablo')) {
            $connection->statement(sprintf(
                "ALTER TABLE `%s` ADD COLUMN `kaynak_tablo` VARCHAR(191) NOT NULL DEFAULT '%s'",
                $archiveTable,
                self::CHILD_TABLE
            ));
        }

        if (! $schema->hasColumn($archiveTable, 'yetim_nedenleri')) {
            $connection->statement(sprintf(
                'ALTER TABLE `%s` ADD COLUMN `yetim_nedenleri` LONGTEXT NULL',
                $archiveTable
            ));
        }

        if (! $schema->hasColumn($archiveTable, 'arsivlendi_at')) {
            $connection->statement(sprintf(
                'ALTER TABLE `%s` ADD COLUMN `arsivlendi_at` DATETIME NULL',
                $archiveTable
            ));
        }
    }

    private function printConstraintSql(): void
    {
        $statements = [
            "ALTER TABLE `fatura_finans_kapatmalari`\n".
            "  ADD CONSTRAINT `fatura_finans_kapatmalari_fatura_id_foreign`\n".
            "    FOREIGN KEY (`fatura_id`) REFERENCES `faturalar` (`id`) ON DELETE CASCADE,\n".
            "  ADD CONSTRAINT `fatura_finans_kapatmalari_finans_hareket_id_foreign`\n".
            "    FOREIGN KEY (`finans_hareket_id`) REFERENCES `finans_hareketleri` (`id`) ON DELETE CASCADE,\n".
            "  ADD CONSTRAINT `fatura_finans_kapatmalari_firma_id_foreign`\n".
            "    FOREIGN KEY (`firma_id`) REFERENCES `firmalar` (`id`) ON DELETE CASCADE;",
        ];

        $this->newLine();
        $this->line('Temizlik sonrasi tekrar calistirilacak SQL:');
        foreach ($statements as $statement) {
            $this->line($statement);
        }
    }
}
