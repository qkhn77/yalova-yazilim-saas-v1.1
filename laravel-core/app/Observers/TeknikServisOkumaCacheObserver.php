<?php

namespace App\Observers;

use App\TeknikServis\Servisler\TeknikServisOkumaCache;
use Illuminate\Database\Eloquent\Model;

class TeknikServisOkumaCacheObserver
{
    public function __construct(
        private readonly TeknikServisOkumaCache $cache,
    ) {}

    public function saved(Model $model): void
    {
        $this->cache->temizle();
    }

    public function deleted(Model $model): void
    {
        $this->cache->temizle();
    }

    public function restored(Model $model): void
    {
        $this->cache->temizle();
    }

    public function forceDeleted(Model $model): void
    {
        $this->cache->temizle();
    }
}
