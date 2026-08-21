<?php

namespace App\Observers;

use App\TeknikServis\Servisler\TeknikServisFormSecenekCache;
use Illuminate\Database\Eloquent\Model;

class TeknikServisFormSecenekCacheObserver
{
    public function __construct(
        private readonly TeknikServisFormSecenekCache $cache,
    ) {}

    public function saved(Model $model): void
    {
        $this->cache->modelIcinTemizle($model);
    }

    public function deleted(Model $model): void
    {
        $this->cache->modelIcinTemizle($model);
    }

    public function restored(Model $model): void
    {
        $this->cache->modelIcinTemizle($model);
    }

    public function forceDeleted(Model $model): void
    {
        $this->cache->modelIcinTemizle($model);
    }
}
