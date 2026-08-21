<?php

namespace App\Muhasebe\Enumlar;

/**
 * Kasa / banka / POS satır hareketlerinde silme yerine ters kayıt için durum.
 */
enum HareketDurumu: string
{
    case Aktif = 'aktif';

    case Iptal = 'iptal';
}
