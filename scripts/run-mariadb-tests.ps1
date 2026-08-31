[CmdletBinding()]
param(
    [string] $HostName = '127.0.0.1',
    [int] $Port = 3307,
    [string] $Database = 'yalovayazilimsaas_test_phase32_20260822',
    [string] $Php = 'C:\xampp\php\php.exe',
    [string] $Mysql = 'C:\xampp\mysql\bin\mysql.exe',
    [string] $PhpUnitConfig = 'phpunit.mariadb.xml',
    [string[]] $TestArguments = @()
)

$ErrorActionPreference = 'Stop'

if ($HostName -notin @('127.0.0.1', 'localhost', '::1')) {
    throw "Refusing to run against non-local host: $HostName"
}

if ($Database -notmatch '^yalovayazilimsaas_test_phase32_[a-z0-9_]+$') {
    throw "Refusing database name outside the phase-3.2 test namespace: $Database"
}

foreach ($path in @($Php, $Mysql)) {
    if (-not (Test-Path -LiteralPath $path)) {
        throw "Required executable not found: $path"
    }
}

$root = Split-Path -Parent $PSScriptRoot
$app = Join-Path $root 'laravel-core'

function Invoke-MySql([string] $Sql) {
    & $Mysql --protocol=TCP -h $HostName -P $Port -u root --batch --skip-column-names -e $Sql
    if ($LASTEXITCODE -ne 0) {
        throw "MariaDB command failed with exit code $LASTEXITCODE"
    }
}

Write-Host "Verified local MariaDB target: $HostName`:$Port / $Database"
Invoke-MySql "SELECT @@hostname, @@port, VERSION();"

# This is the only database name this harness can remove.
Invoke-MySql "DROP DATABASE IF EXISTS $Database; CREATE DATABASE $Database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

$env:APP_ENV = 'testing'
$env:DB_CONNECTION = 'mariadb'
$env:DB_HOST = $HostName
$env:DB_PORT = [string] $Port
$env:DB_DATABASE = $Database
$env:DB_USERNAME = 'root'
$env:DB_PASSWORD = ''

Push-Location $app
try {
    & $Php artisan migrate --force
    if ($LASTEXITCODE -ne 0) { throw "Migration failed with exit code $LASTEXITCODE" }

    & $Php artisan db:seed --class=SaasDatabaseSeeder --force
    if ($LASTEXITCODE -ne 0) { throw "First seed failed with exit code $LASTEXITCODE" }

    & $Php artisan db:seed --class=SaasDatabaseSeeder --force
    if ($LASTEXITCODE -ne 0) { throw "Second seed failed with exit code $LASTEXITCODE" }

    & $Php artisan about --only=environment,cache,database
    if ($LASTEXITCODE -ne 0) { throw "Application boot check failed with exit code $LASTEXITCODE" }

    & $Php vendor/phpunit/phpunit/phpunit --configuration=$PhpUnitConfig @TestArguments
    $testExitCode = $LASTEXITCODE
}
finally {
    Pop-Location
}

# Leave the isolated result database available for post-test inspection. The
# caller owns the separate MariaDB process and can shut it down explicitly.
Invoke-MySql "SELECT COUNT(*) AS open_transactions FROM information_schema.innodb_trx;"
if ($testExitCode -ne 0) {
    exit $testExitCode
}
