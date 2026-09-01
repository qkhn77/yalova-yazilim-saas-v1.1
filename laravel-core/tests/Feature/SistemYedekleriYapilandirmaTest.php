<?php

namespace Tests\Feature;

use App\Services\SistemYedekleriServisi;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SistemYedekleriYapilandirmaTest extends TestCase
{
    private ?string $geciciDizin = null;

    protected function tearDown(): void
    {
        if ($this->geciciDizin !== null && is_dir($this->geciciDizin)) {
            File::deleteDirectory($this->geciciDizin);
        }

        parent::tearDown();
    }

    public function test_servis_sql_yedeklerini_listeler_ve_diger_dosyalari_yoksayar(): void
    {
        $this->geciciDizin = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sistem-yedekleri-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->geciciDizin);

        $eskiYedek = $this->geciciDizin.DIRECTORY_SEPARATOR.'db-2026-08-31.sql';
        $yeniYedek = $this->geciciDizin.DIRECTORY_SEPARATOR.'db-2026-09-01.sql.gz';
        File::put($eskiYedek, 'eski');
        File::put($yeniYedek, 'yeni');
        File::put($this->geciciDizin.DIRECTORY_SEPARATOR.'not.txt', 'yoksay');
        touch($eskiYedek, 1_700_000_000);
        touch($yeniYedek, 1_800_000_000);

        config(['backup.path' => $this->geciciDizin]);

        $yedekler = app(SistemYedekleriServisi::class)->listele();

        $this->assertSame(['db-2026-09-01.sql.gz', 'db-2026-08-31.sql'], array_column($yedekler, 'name'));
    }

    public function test_servis_mysqldump_ciktisini_sikistirilmis_yedek_olarak_kaydeder(): void
    {
        $this->geciciDizin = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sistem-yedekleri-'.bin2hex(random_bytes(8));
        File::ensureDirectoryExists($this->geciciDizin);
        $fakeDump = $this->geciciDizin.DIRECTORY_SEPARATOR.'fake-mysqldump.php';
        File::put($fakeDump, <<<'PHP'
<?php
fwrite(STDOUT, "CREATE TABLE test (id INT);\n");
PHP);

        config([
            'backup.path' => $this->geciciDizin,
            'backup.mysqldump_command' => [PHP_BINARY, $fakeDump],
            'backup.defaults_file' => '',
            'backup.timeout_seconds' => 30,
            'database.default' => 'mysql',
            'database.connections.mysql.driver' => 'mysql',
            'database.connections.mysql.database' => 'test_database',
            'database.connections.mysql.host' => '127.0.0.1',
            'database.connections.mysql.port' => 3306,
            'database.connections.mysql.username' => 'test',
            'database.connections.mysql.password' => 'secret',
        ]);

        $yedek = app(SistemYedekleriServisi::class)->yedekAl();

        $this->assertMatchesRegularExpression('/\Adb-\d{4}-\d{2}-\d{2}-\d{6}-\d{6}\.sql\.gz\z/', $yedek['name']);
        $this->assertSame("CREATE TABLE test (id INT);\n", gzdecode((string) File::get(
            $this->geciciDizin.DIRECTORY_SEPARATOR.$yedek['name']
        )));
        $this->assertFileDoesNotExist($this->geciciDizin.DIRECTORY_SEPARATOR.$yedek['name'].'.part');
    }
}
