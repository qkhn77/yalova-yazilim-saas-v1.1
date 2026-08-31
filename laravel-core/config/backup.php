<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SQL backup directory
    |--------------------------------------------------------------------------
    |
    | Keep backups outside the public document root. Production can override
    | this with BACKUP_PATH (the cPanel cron uses /home/yalovaya/backups/database).
    */
    'path' => env('BACKUP_PATH', storage_path('backups/database')),
];
