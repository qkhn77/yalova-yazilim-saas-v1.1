<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class AdminWarmupCommand extends Command
{
    protected $signature = 'admin:warmup
        {--url= : APP_URL yerine kullanilacak taban URL}
        {--timeout=30 : Her HTTP istegi icin saniye cinsinden timeout}
        {--direct-runs=1 : Dogrudan admin sayfalari icin tekrar sayisi}
        {--parameter-runs=1 : Parametreli admin sayfalari icin tekrar sayisi}
        {--skip-parameters : Parametreli sayfalari isitma}';

    protected $description = 'Deploy/restart sonrasi admin sayfalarini HTTP istekleriyle isitir.';

    public function handle(): int
    {
        $url = trim((string) ($this->option('url') ?: ''));
        $timeout = max(3, (int) $this->option('timeout'));
        $directRuns = max(1, (int) $this->option('direct-runs'));
        $parameterRuns = max(1, (int) $this->option('parameter-runs'));

        $directArgs = [
            '--runs' => $directRuns,
            '--timeout' => $timeout,
            '--cleanup-temp-user' => true,
        ];

        if ($url !== '') {
            $directArgs['--url'] = $url;
        }

        $this->line('Dogrudan admin sayfalari isitiliyor...');
        $directStatus = $this->call('admin:performance-scan', $directArgs);
        if ($directStatus !== self::SUCCESS) {
            return $directStatus;
        }

        if ((bool) $this->option('skip-parameters')) {
            return self::SUCCESS;
        }

        $parameterArgs = [
            '--runs' => $parameterRuns,
            '--timeout' => $timeout,
            '--use-fixtures' => true,
            '--cleanup-temp-user' => true,
        ];

        if ($url !== '') {
            $parameterArgs['--url'] = $url;
        }

        $this->line('Parametreli admin sayfalari isitiliyor...');

        return $this->call('admin:parameter-performance-scan', $parameterArgs);
    }
}
