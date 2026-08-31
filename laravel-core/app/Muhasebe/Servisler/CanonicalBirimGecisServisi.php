<?php

namespace App\Muhasebe\Servisler;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class CanonicalBirimGecisServisi
{
    public const DUPLICATE_STATE = 'BLOCKED — DUPLICATE CANONICAL UNIT STATE';

    public const PRECONDITION_FAILED = 'BLOCKED — AD TRANSITION PRECONDITION FAILED';

    public static function adetKodunuAdYap(ConnectionInterface|null $connection = null): bool
    {
        $connection ??= DB::connection();

        return $connection->transaction(function () use ($connection): bool {
            $adRows = $connection->table('muhasebe_birimler')->where('kod', 'AD')->lockForUpdate()->get();
            $adetRows = $connection->table('muhasebe_birimler')->where('kod', 'ADET')->lockForUpdate()->get();

            if ($adRows->isNotEmpty() && $adetRows->isNotEmpty()) {
                throw new RuntimeException(self::DUPLICATE_STATE);
            }

            if ($adRows->count() === 1 && $adetRows->isEmpty()) {
                return false;
            }

            if (
                $adRows->isNotEmpty()
                || $adetRows->count() !== 1
                || (int) $adetRows->first()->id !== 1
                || (string) $adetRows->first()->ad !== 'Adet'
                || self::aktifForeignKeyReferansSayisi($connection, 1) !== 0
            ) {
                throw new RuntimeException(self::PRECONDITION_FAILED);
            }

            $affected = $connection->table('muhasebe_birimler')
                ->where('id', 1)
                ->where('kod', 'ADET')
                ->where('ad', 'Adet')
                ->update([
                    'kod' => 'AD',
                    'updated_at' => now(),
                ]);

            if ($affected !== 1) {
                throw new RuntimeException(self::PRECONDITION_FAILED);
            }

            return true;
        }, 3);
    }

    private static function aktifForeignKeyReferansSayisi(ConnectionInterface $connection, int $birimId): int
    {
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return 0;
        }

        $database = $connection->getDatabaseName();
        $foreignKeys = $connection->table('information_schema.KEY_COLUMN_USAGE')
            ->where('REFERENCED_TABLE_SCHEMA', $database)
            ->where('REFERENCED_TABLE_NAME', 'muhasebe_birimler')
            ->where('REFERENCED_COLUMN_NAME', 'id')
            ->get(['TABLE_NAME', 'COLUMN_NAME']);

        $total = 0;
        foreach ($foreignKeys as $foreignKey) {
            $total += (int) $connection->table($foreignKey->TABLE_NAME)
                ->where($foreignKey->COLUMN_NAME, $birimId)
                ->count();
        }

        return $total;
    }
}
