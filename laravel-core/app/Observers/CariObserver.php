<?php

namespace App\Observers;

use App\Models\Muhasebe\Cari;
use App\Services\CariDenetimServisi;
use App\TeknikServis\Servisler\TeknikServisOkumaCache;

class CariObserver
{
    public function __construct(
        protected CariDenetimServisi $denetim,
        protected TeknikServisOkumaCache $teknikServisOkumaCache,
    ) {}

    public function created(Cari $cari): void
    {
        $this->denetim->kaydet('cari_karti.olustur', $cari, null, $cari->getAttributes());
        $this->teknikServisOkumaCache->temizle();
    }

    public function updated(Cari $cari): void
    {
        $this->denetim->kaydet(
            'cari_karti.guncelle',
            $cari,
            $cari->getOriginal(),
            $cari->getChanges()
        );
        $this->teknikServisOkumaCache->temizle();
    }

    public function deleted(Cari $cari): void
    {
        $this->denetim->kaydet('cari_karti.sil', $cari, $cari->getOriginal(), null);
        $this->teknikServisOkumaCache->temizle();
    }

    public function restored(Cari $cari): void
    {
        $this->teknikServisOkumaCache->temizle();
    }

    public function forceDeleted(Cari $cari): void
    {
        $this->teknikServisOkumaCache->temizle();
    }
}
