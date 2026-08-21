<?php

namespace App\Observers;

use App\Models\Muhasebe\PosHesabi;
use App\Services\PosHesabiDenetimServisi;

class PosHesabiObserver
{
    public function __construct(
        protected PosHesabiDenetimServisi $denetim
    ) {}

    public function created(PosHesabi $posHesabi): void
    {
        $this->denetim->kaydet('pos_hesabi.olustur', $posHesabi, null, $posHesabi->getAttributes());
    }

    public function updated(PosHesabi $posHesabi): void
    {
        $this->denetim->kaydet(
            'pos_hesabi.guncelle',
            $posHesabi,
            $posHesabi->getOriginal(),
            $posHesabi->getChanges()
        );
    }

    public function deleted(PosHesabi $posHesabi): void
    {
        $this->denetim->kaydet('pos_hesabi.sil', $posHesabi, $posHesabi->getOriginal(), null);
    }
}
