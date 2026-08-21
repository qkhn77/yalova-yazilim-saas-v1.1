<?php

namespace App\Muhasebe\Enumlar;

enum FaturaTuru: string
{
    case Gelen = 'gelen';

    case GelenFatura = 'gelen_fatura';

    case Giden = 'giden';

    case GidenFatura = 'giden_fatura';

    case Proforma = 'proforma';

    case ProformaFatura = 'proforma_fatura';

    case Gider = 'gider';

    case GiderFaturasi = 'gider_faturasi';

    case BekleyenFatura = 'bekleyen_fatura';

    case IptalFatura = 'iptal_fatura';

    case IadeFatura = 'iade_fatura';

    /** Müşteriye kesilen satışın iadesi (stok girişi / cari borç yönü). */
    case SatisIadesi = 'satis_iadesi';

    /** Tedarikçi alışının iadesi (stok çıkışı / cari alacak yönü). */
    case AlisIadesi = 'alis_iadesi';

    /**
     * Kullanıcıya gösterilecek nihai tür listesi (STEP 15.2).
     *
     * @return array<self>
     */
    public static function uiNihaiTurler(): array
    {
        return [
            self::Gelen,
            self::Giden,
            self::BekleyenFatura,
            self::IptalFatura,
            self::SatisIadesi,
            self::AlisIadesi,
            self::Proforma,
            self::Gider,
        ];
    }

    public function etiket(): string
    {
        return match ($this->kanonik()) {
            self::Gelen => 'Gelen Fatura',
            self::Giden => 'Giden Fatura',
            self::BekleyenFatura => 'Bekleyen Fatura',
            self::IptalFatura => 'İptal Fatura',
            self::SatisIadesi => 'Giden İade Faturası',
            self::AlisIadesi => 'Gelen İade Faturası',
            self::Proforma => 'Proforma Fatura',
            self::Gider => 'Gider Faturası',
        };
    }

    public function kanonik(): self
    {
        return match ($this) {
            self::GelenFatura => self::Gelen,
            self::GidenFatura => self::Giden,
            self::ProformaFatura => self::Proforma,
            self::GiderFaturasi => self::Gider,
            self::IadeFatura => self::SatisIadesi,
            default => $this,
        };
    }

    public function kayitUretirMi(): bool
    {
        return ! in_array($this->kanonik(), [self::Proforma, self::BekleyenFatura, self::IptalFatura], true);
    }

    public function cariYonu(): string
    {
        return match ($this->kanonik()) {
            self::Giden => 'alacak',
            self::Gelen, self::Gider, self::SatisIadesi => 'borc',
            self::AlisIadesi => 'alacak',
            self::Proforma, self::BekleyenFatura, self::IptalFatura => 'yok',
        };
    }

    public function stokYonu(): string
    {
        return match ($this->kanonik()) {
            self::Giden, self::AlisIadesi => 'cikis',
            self::Gelen, self::Gider, self::SatisIadesi => 'giris',
            self::Proforma, self::BekleyenFatura, self::IptalFatura => 'yok',
        };
    }
}
