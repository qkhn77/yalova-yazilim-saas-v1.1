<?php

$configuredPath = trim((string) env('BACKUP_PATH', ''));
$defaultPath = dirname(base_path()).DIRECTORY_SEPARATOR.'backups'.DIRECTORY_SEPARATOR.'database';
$configuredMysqldump = trim((string) env('MYSQLDUMP_BINARY', ''));
$configuredDefaultsFile = trim((string) env('MYSQLDUMP_DEFAULTS_FILE', ''));
$xamppMysqldump = dirname(dirname(PHP_BINARY)).DIRECTORY_SEPARATOR.'mysql'.DIRECTORY_SEPARATOR.'bin'.DIRECTORY_SEPARATOR.'mysqldump.exe';
$defaultMysqldump = PHP_OS_FAMILY === 'Windows' && is_file($xamppMysqldump)
    ? $xamppMysqldump
    : (PHP_OS_FAMILY === 'Windows' ? 'mysqldump' : '/usr/bin/mysqldump');

return [
    /*
    |--------------------------------------------------------------------------
    | SQL backup directory
    |--------------------------------------------------------------------------
    |
    | Keep backups outside the public document root. An explicit BACKUP_PATH
    | takes precedence. Empty values use the account-level backups/database
    | directory expected by the cPanel cron.
    */
    'path' => $configuredPath !== '' ? $configuredPath : $defaultPath,

    /*
    | mysqldump is executed without a shell. Production normally reads the
    | database credentials from the same account-level .my.cnf used by cron.
    */
    'mysqldump_command' => [$configuredMysqldump !== '' ? $configuredMysqldump : $defaultMysqldump],
    'defaults_file' => $configuredDefaultsFile !== ''
        ? $configuredDefaultsFile
        : dirname(base_path()).DIRECTORY_SEPARATOR.'.my.cnf',
    'timeout_seconds' => (int) env('BACKUP_TIMEOUT_SECONDS', 300),
];
