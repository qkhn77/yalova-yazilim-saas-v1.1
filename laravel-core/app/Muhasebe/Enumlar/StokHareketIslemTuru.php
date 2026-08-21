<?php

namespace App\Muhasebe\Enumlar;

enum StokHareketIslemTuru: string
{
    case Acilis = 'acilis';

    case AcilisIptali = 'acilis_iptali';

    case Alis = 'alis';

    case Satis = 'satis';

    /** @deprecated Eski kayıtlar; yeni kod {@see self::SatisIadesi} / {@see self::AlisIadesi} kullanmalı */
    case Iade = 'iade';

    /** Satış iadesi: stok girişi (müşteri iadesi). */
    case SatisIadesi = 'satis_iadesi';

    /** Alış iadesi: stok çıkışı (tedarikçiye iade). */
    case AlisIadesi = 'alis_iadesi';

    case TransferGiris = 'transfer_giris';

    case TransferCikis = 'transfer_cikis';
}
