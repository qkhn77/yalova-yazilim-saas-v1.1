<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
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
        $command = $this->mysqldumpKomutu($database, $connection);
        $environment = $this->mysqldumpOrtami($connection);
        $gzip = gzopen($temporaryPath, 'wb9');

        if ($gzip === false) {
            throw new RuntimeException('Geçici yedek dosyası oluşturulamadı.');
        }

        $process = new Process($command, base_path(), $environment);
        $process->setTimeout(max(30, (int) config('backup.timeout_seconds', 300)));
        $errorOutput = '';

        try {
            $process->start();

            foreach ($process as $type => $buffer) {
                if ($type === Process::OUT) {
                    if (gzwrite($gzip, $buffer) === false) {
                        throw new RuntimeException('Yedek dosyasına yazılamadı.');
                    }

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

            gzclose($gzip);
            File::delete($temporaryPath);

            throw $exception;
        } finally {
            if (is_resource($gzip)) {
                gzclose($gzip);
            }
        }

        if (! $process->isSuccessful()) {
            File::delete($temporaryPath);
            $detail = trim($errorOutput);

            throw new RuntimeException('mysqldump çalıştırılamadı'.($detail !== '' ? ': '.$detail : '.'));
        }

        if (! is_file($temporaryPath) || filesize($temporaryPath) === 0) {
            File::delete($temporaryPath);
            throw new RuntimeException('mysqldump boş bir çıktı üretti.');
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
