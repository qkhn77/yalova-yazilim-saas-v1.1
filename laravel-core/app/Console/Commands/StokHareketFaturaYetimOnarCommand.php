<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StokHareketFaturaYetimOnarCommand extends Command
{
    private const CHILD_TABLE = 'stok_hareketleri';

    protected $signature = 'muhasebe:stok-hareket-fatura-yetim-onar
        {--apply : Yetim kayitlari arsiv tabloya tasir ve ana tablodan kaldirir}
        {--archive-table=stok_hareketleri_fatura_yetim_arsiv : Arsiv tablo adi}
        {--connection= : Veritabani baglantisi}
        {--firma_id= : Sadece belirtilen firma id icin tarama yapar}
        {--sample=20 : Raporda gosterilecek ornek kayit sayisi}';

    protected $description = 'stok_hareketleri tablosunda belge_turu=fatura olup faturasi bulunmayan yetim kayitlari arsivler.';

    public function handle(): int
    {
        mb_internal_encoding('UTF-8');

        $connectionName = $this->option('connection') ?: config('database.default');
        $connection = DB::connection((string) $connectionName);
        $archiveTable = (string) $this->option('archive-table');
        $firmaId = $this->option('firma_id') !== null ? (int) $this->option('firma_id') : null;
        $sampleSize = max(1, (int) $this->option('sample'));

        $rows = $this->findOrphanRows($connection, $firmaId);

        $this->line('Baglanti: '.$connection->getName());
        $this->line('Tablo: '.self::CHILD_TABLE);
        $this->line('Toplam yetim satir: '.$rows->count());

        if ($rows->isEmpty()) {
            $this->info('Yetim stok hareketi bulunmadi.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(
            ['id', 'firma_id', 'belge_turu', 'belge_id', 'islem_turu', 'nedenler'],
            $rows->take($sampleSize)->map(static fn (array $row): array => [
                $row['id'],
                $row['firma_id'],
                $row['belge_turu'],
                $row['belge_id'],
                $row['islem_turu'],
                implode(', ', $row['orphan_reasons']),
            ])->all()
        );

        if (! $this->option('apply')) {
            $this->warn('Dry-run tamamlandi. Arsivleyip temizlemek icin ayni komutu --apply ile tekrar calistirin.');

            return self::FAILURE;
        }

        $this->ensureArchiveTable($connectionName, $archiveTable);

        $rowsToArchive = $rows->map(function (array $row): array {
            $record = $row['record'];
            $record['kaynak_tablo'] = self::CHILD_TABLE;
            $record['yetim_nedenleri'] = json_encode($row['orphan_reasons'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $record['arsivlendi_at'] = now();

            return $record;
        })->values()->all();
        $rowIds = $rows->pluck('id')->map(fn (int $id): int => $id)->all();

        $archivedCount = 0;
        $deletedCount = 0;
        $connection->transaction(function () use ($connection, $archiveTable, $rowsToArchive, $rowIds, &$archivedCount, &$deletedCount): void {
            $archivedCount = $connection->table($archiveTable)->insertOrIgnore($rowsToArchive);
            $deletedCount = $connection->table(self::CHILD_TABLE)->whereIn('id', $rowIds)->delete();
        });

        $remainingRows = $this->findOrphanRows($connection, $firmaId);

        $this->info(sprintf('Arsive eklenen yeni kayit: %d', $archivedCount));
        $this->info(sprintf('Ana tablodan kaldirilan kayit: %d', $deletedCount));
        $this->info(sprintf('Kalan yetim kayit: %d', $remainingRows->count()));

        return $remainingRows->isEmpty() ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return Collection<int, array{
     *     id: int,
     *     firma_id: int|null,
     *     belge_turu: string|null,
     *     belge_id: int|null,
     *     islem_turu: string|null,
     *     orphan_reasons: array<int, string>,
     *     record: array<string, mixed>
     * }>
     */
    private function findOrphanRows(ConnectionInterface $connection, ?int $firmaId): Collection
    {
        $query = $connection->table(self::CHILD_TABLE.' as sh')
            ->leftJoin('faturalar as f', 'f.id', '=', 'sh.belge_id')
            ->leftJoin('firmalar as fi', 'fi.id', '=', 'sh.firma_id')
            ->select([
                'sh.*',
                DB::raw('f.id as _exists_fatura_id'),
                DB::raw('fi.id as _exists_firma_id'),
            ])
            ->where('sh.belge_turu', 'fatura')
            ->where(function ($where): void {
                $where
                    ->where(function ($q): void {
                        $q->whereNotNull('sh.belge_id')->whereNull('f.id');
                    })
                    ->orWhere(function ($q): void {
                        $q->whereNotNull('sh.firma_id')->whereNull('fi.id');
                    });
            })
            ->orderBy('sh.id');

        if ($firmaId !== null) {
            $query->where('sh.firma_id', $firmaId);
        }

        return $query->get()->map(function (object $row): array {
            $record = (array) $row;
            $reasons = [];

            if ($row->belge_id !== null && $row->_exists_fatura_id === null) {
                $reasons[] = 'belge_id -> faturalar.id bulunamadi';
            }

            if ($row->firma_id !== null && $row->_exists_firma_id === null) {
                $reasons[] = 'firma_id -> firmalar.id bulunamadi';
            }

            unset($record['_exists_fatura_id'], $record['_exists_firma_id']);

            return [
                'id' => (int) $row->id,
                'firma_id' => $row->firma_id !== null ? (int) $row->firma_id : null,
                'belge_turu' => $row->belge_turu !== null ? (string) $row->belge_turu : null,
                'belge_id' => $row->belge_id !== null ? (int) $row->belge_id : null,
                'islem_turu' => $row->islem_turu !== null ? (string) $row->islem_turu : null,
                'orphan_reasons' => $reasons,
                'record' => $record,
            ];
        })->values();
    }

    private function ensureArchiveTable(string $connectionName, string $archiveTable): void
    {
        $schema = Schema::connection($connectionName);
        $connection = DB::connection($connectionName);

        if (! $schema->hasTable($archiveTable)) {
            $connection->statement(sprintf('CREATE TABLE `%s` LIKE `%s`', $archiveTable, self::CHILD_TABLE));
        }

        if (! $schema->hasColumn($archiveTable, 'kaynak_tablo')) {
            $connection->statement(sprintf(
                "ALTER TABLE `%s` ADD COLUMN `kaynak_tablo` VARCHAR(191) NOT NULL DEFAULT '%s'",
                $archiveTable,
                self::CHILD_TABLE
            ));
        }

        if (! $schema->hasColumn($archiveTable, 'yetim_nedenleri')) {
            $connection->statement(sprintf('ALTER TABLE `%s` ADD COLUMN `yetim_nedenleri` LONGTEXT NULL', $archiveTable));
        }

        if (! $schema->hasColumn($archiveTable, 'arsivlendi_at')) {
            $connection->statement(sprintf('ALTER TABLE `%s` ADD COLUMN `arsivlendi_at` DATETIME NULL', $archiveTable));
        }
    }
}
