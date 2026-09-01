<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\Process\Process;
use Throwable;

class SistemYedekleriServisi
{
    public function dizin(): string
    {
        return rtrim((string) config('backup.path'), DIRECTORY_SEPARATOR);
    }

    /**
     * @return list<array{name: string, size: int, modified_at: int}>
     */
    public function listele(): array
    {
        $directory = $this->dizin();
        if (! is_dir($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(fn (\SplFileInfo $file): bool => preg_match('/\\.sql(?:\\.gz)?\\z/i', $file->getFilename()) === 1)
            ->map(fn (\SplFileInfo $file): array => [
                'name' => $file->getFilename(),
                'size' => $file->getSize(),
                'modified_at' => $file->getMTime(),
            ])
            ->sortByDesc('modified_at')
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, size: int, modified_at: int}
     */
    public function yedekAl(): array
    {
        $lock = Cache::lock('sistem-veritabani-yedegi', 600);
        if (! $lock->get()) {
            throw new RuntimeException('Başka bir veritabanı yedeği halen hazırlanıyor.');
        }

        try {
            return $this->yedekOlustur();
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{name: string, size: int, modified_at: int}
     */
    private function yedekOlustur(): array
    {
        if (! function_exists('gzopen')) {
            throw new RuntimeException('Sunucuda PHP zlib desteği etkin değil.');
        }

        $directory = $this->dizin();
        File::ensureDirectoryExists($directory, 0750, true);

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('Yedek dizini oluşturulamıyor veya yazılabilir değil.');
        }

        $connectionName = (string) config('database.default');
        $connection = config('database.connections.'.$connectionName);
        if (! is_array($connection) || ! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException('Yalnızca MySQL veya MariaDB veritabanlarının yedeği alınabilir.');
        }

        $database = (string) ($connection['database'] ?? '');
        if ($database === '') {
            throw new RuntimeException('MySQL veritabanı adı yapılandırılmamış.');
        }

        $name = 'db-'.now()->format('Y-m-d-His-u').'.sql.gz';
        $path = $directory.DIRECTORY_SEPARATOR.$name;
        $temporaryPath = $path.'.part';
        $gzip = gzopen($temporaryPath, 'wb9');

        if ($gzip === false) {
            throw new RuntimeException('Geçici yedek dosyası oluşturulamadı.');
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(max(30, (int) config('backup.timeout_seconds', 300)));
        }

        try {
            $driver = strtolower(trim((string) config('backup.driver', 'auto')));
            if ($driver === 'pdo' || ($driver === 'auto' && ! function_exists('proc_open'))) {
                $this->pdoIleYedekYaz($gzip, $connectionName, $database);
            } elseif (in_array($driver, ['auto', 'mysqldump'], true)) {
                $this->mysqldumpIleYedekYaz($gzip, $database, $connection);
            } else {
                throw new RuntimeException('Geçersiz veritabanı yedekleme sürücüsü yapılandırılmış.');
            }
        } catch (Throwable $exception) {
            gzclose($gzip);
            File::delete($temporaryPath);

            throw $exception;
        } finally {
            if (is_resource($gzip)) {
                gzclose($gzip);
            }
        }

        if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            File::delete($temporaryPath);
            throw new RuntimeException('Veritabanı yedeği boş bir çıktı üretti.');
        }

        if (! File::move($temporaryPath, $path)) {
            File::delete($temporaryPath);
            throw new RuntimeException('Yedek dosyası son konumuna taşınamadı.');
        }

        return [
            'name' => $name,
            'size' => (int) filesize($path),
            'modified_at' => (int) filemtime($path),
        ];
    }

    /**
     * @param  resource  $gzip
     * @param  array<string, mixed>  $connection
     */
    private function mysqldumpIleYedekYaz($gzip, string $database, array $connection): void
    {
        $process = new Process(
            $this->mysqldumpKomutu($database, $connection),
            base_path(),
            $this->mysqldumpOrtami($connection),
        );
        $process->setTimeout(max(30, (int) config('backup.timeout_seconds', 300)));
        $errorOutput = '';

        try {
            $process->start();

            foreach ($process as $type => $buffer) {
                if ($type === Process::OUT) {
                    $this->gzipYaz($gzip, $buffer);
                    $process->clearOutput();

                    continue;
                }

                if (strlen($errorOutput) < 8_000) {
                    $errorOutput .= $buffer;
                }
                $process->clearErrorOutput();
            }
        } catch (Throwable $exception) {
            if ($process->isRunning()) {
                $process->stop(1);
            }

            throw $exception;
        }

        if (! $process->isSuccessful()) {
            $detail = trim($errorOutput);

            throw new RuntimeException('mysqldump çalıştırılamadı'.($detail !== '' ? ': '.$detail : '.'));
        }
    }

    /**
     * proc_open kapalı shared-hosting ortamları için veriyi PDO üzerinden
     * akış halinde SQL dosyasına yazar.
     *
     * @param  resource  $gzip
     */
    private function pdoIleYedekYaz($gzip, string $connectionName, string $database): void
    {
        $pdo = DB::connection($connectionName)->getPdo();
        $this->gzipYaz($gzip, implode("\n", [
            '-- Yalova Yazılım veritabanı yedeği',
            '-- Oluşturulma: '.now()->toIso8601String(),
            '-- Veritabanı: '.$database,
            'SET NAMES utf8mb4;',
            'SET FOREIGN_KEY_CHECKS=0;',
            'SET UNIQUE_CHECKS=0;',
            'START TRANSACTION;',
            '',
        ]));

        $bufferedAttribute = defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')
            ? constant('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')
            : null;
        $previousBufferedValue = null;

        if (is_int($bufferedAttribute)) {
            try {
                $previousBufferedValue = $pdo->getAttribute($bufferedAttribute);
                $pdo->setAttribute($bufferedAttribute, false);
            } catch (Throwable) {
                $previousBufferedValue = null;
            }
        }

        $transactionStarted = false;

        try {
            if (! $pdo->inTransaction()) {
                $pdo->beginTransaction();
                $transactionStarted = true;
            }

            [$tables, $views] = $this->mysqlNesneleriniGetir($pdo);

            foreach ($tables as $table) {
                $this->mysqlTablosunuYaz($pdo, $gzip, $table);
            }

            foreach ($views as $view) {
                $this->mysqlGorunumunuYaz($pdo, $gzip, $view);
            }

            $this->mysqlTetikleyicileriniYaz($pdo, $gzip);
            $this->gzipYaz($gzip, "COMMIT;\nSET UNIQUE_CHECKS=1;\nSET FOREIGN_KEY_CHECKS=1;\n");
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        } finally {
            if (is_int($bufferedAttribute) && $previousBufferedValue !== null) {
                try {
                    $pdo->setAttribute($bufferedAttribute, $previousBufferedValue);
                } catch (Throwable) {
                    // Bağlantı kapanırken eski değerin geri yüklenememesi yedeği bozmaz.
                }
            }
        }
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function mysqlNesneleriniGetir(PDO $pdo): array
    {
        $statement = $pdo->query('SHOW FULL TABLES');
        $tables = [];
        $views = [];

        while (($row = $statement->fetch(PDO::FETCH_NUM)) !== false) {
            if (strtoupper((string) ($row[1] ?? '')) === 'VIEW') {
                $views[] = (string) $row[0];
            } else {
                $tables[] = (string) $row[0];
            }
        }
        $statement->closeCursor();

        return [$tables, $views];
    }

    /** @param resource $gzip */
    private function mysqlTablosunuYaz(PDO $pdo, $gzip, string $table): void
    {
        $identifier = $this->mysqlIdentifier($table);
        $createStatement = $pdo->query('SHOW CREATE TABLE '.$identifier);
        $createRow = $createStatement->fetch(PDO::FETCH_NUM);
        $createStatement->closeCursor();

        if (! is_array($createRow) || ! isset($createRow[1])) {
            throw new RuntimeException($table.' tablosunun şeması okunamadı.');
        }

        $this->gzipYaz($gzip, "\nDROP TABLE IF EXISTS {$identifier};\n{$createRow[1]};\n");

        $rows = $pdo->query('SELECT * FROM '.$identifier);
        $batch = [];
        while (($row = $rows->fetch(PDO::FETCH_NUM)) !== false) {
            $batch[] = '('.implode(', ', array_map(
                fn ($value): string => $this->mysqlDegeri($pdo, $value),
                $row,
            )).')';

            if (count($batch) >= 100) {
                $this->mysqlInsertYaz($gzip, $identifier, $batch);
                $batch = [];
            }
        }
        $rows->closeCursor();

        if ($batch !== []) {
            $this->mysqlInsertYaz($gzip, $identifier, $batch);
        }
    }

    /** @param resource $gzip */
    private function mysqlGorunumunuYaz(PDO $pdo, $gzip, string $view): void
    {
        $identifier = $this->mysqlIdentifier($view);
        $statement = $pdo->query('SHOW CREATE VIEW '.$identifier);
        $row = $statement->fetch(PDO::FETCH_NUM);
        $statement->closeCursor();

        if (! is_array($row) || ! isset($row[1])) {
            throw new RuntimeException($view.' görünümünün şeması okunamadı.');
        }

        $this->gzipYaz($gzip, "\nDROP VIEW IF EXISTS {$identifier};\n{$row[1]};\n");
    }

    /** @param resource $gzip */
    private function mysqlTetikleyicileriniYaz(PDO $pdo, $gzip): void
    {
        $statement = $pdo->query('SHOW TRIGGERS');
        $triggers = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            if (isset($row['Trigger'])) {
                $triggers[] = (string) $row['Trigger'];
            }
        }
        $statement->closeCursor();

        foreach ($triggers as $trigger) {
            $identifier = $this->mysqlIdentifier($trigger);
            $createStatement = $pdo->query('SHOW CREATE TRIGGER '.$identifier);
            $row = $createStatement->fetch(PDO::FETCH_ASSOC);
            $createStatement->closeCursor();
            $sql = is_array($row) ? ($row['SQL Original Statement'] ?? $row['Create Trigger'] ?? null) : null;

            if (is_string($sql) && $sql !== '') {
                $this->gzipYaz($gzip, "\nDROP TRIGGER IF EXISTS {$identifier};\nDELIMITER $$\n{$sql}$$\nDELIMITER ;\n");
            }
        }
    }

    /**
     * @param  resource  $gzip
     * @param  list<string>  $rows
     */
    private function mysqlInsertYaz($gzip, string $identifier, array $rows): void
    {
        $this->gzipYaz($gzip, 'INSERT INTO '.$identifier." VALUES\n".implode(",\n", $rows).";\n");
    }

    private function mysqlIdentifier(string $value): string
    {
        return '`'.str_replace('`', '``', $value).'`';
    }

    private function mysqlDegeri(PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        $quoted = $pdo->quote((string) $value);

        return $quoted !== false ? $quoted : '0x'.bin2hex((string) $value);
    }

    /** @param resource $gzip */
    private function gzipYaz($gzip, string $contents): void
    {
        $offset = 0;
        $length = strlen($contents);

        while ($offset < $length) {
            $written = gzwrite($gzip, substr($contents, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Yedek dosyasına yazılamadı.');
            }

            $offset += $written;
        }
    }

    /**
     * @return list<string>
     */
    private function mysqldumpKomutu(string $database, array $connection): array
    {
        $configuredCommand = config('backup.mysqldump_command', ['mysqldump']);
        $command = is_array($configuredCommand) ? array_values($configuredCommand) : [(string) $configuredCommand];
        $defaultsFile = trim((string) config('backup.defaults_file'));

        if ($defaultsFile !== '' && is_file($defaultsFile)) {
            $command[] = '--defaults-extra-file='.$defaultsFile;
        } else {
            $command[] = '--host='.(string) ($connection['host'] ?? '127.0.0.1');
            $command[] = '--port='.(string) ($connection['port'] ?? 3306);
            $command[] = '--user='.(string) ($connection['username'] ?? '');
        }

        $command[] = '--single-transaction';
        $command[] = '--quick';
        $command[] = '--default-character-set=utf8mb4';
        $command[] = $database;

        return $command;
    }

    /**
     * @return array<string, string>|null
     */
    private function mysqldumpOrtami(array $connection): ?array
    {
        $defaultsFile = trim((string) config('backup.defaults_file'));
        if ($defaultsFile !== '' && is_file($defaultsFile)) {
            return null;
        }

        return ['MYSQL_PWD' => (string) ($connection['password'] ?? '')];
    }

    public function guvenliYol(string $name): string
    {
        abort_unless($name === basename($name), 404);
        abort_unless(preg_match('/\\A[a-zA-Z0-9._-]+\\.sql(?:\\.gz)?\\z/', $name) === 1, 404);

        $path = $this->dizin().DIRECTORY_SEPARATOR.$name;
        abort_unless(is_file($path), 404);

        return $path;
    }

    public function indir(string $name): BinaryFileResponse
    {
        return response()->download($this->guvenliYol($name), $name, [
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function sil(string $name): void
    {
        File::delete($this->guvenliYol($name));
    }
}
