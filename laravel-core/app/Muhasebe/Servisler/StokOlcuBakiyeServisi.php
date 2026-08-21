<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Depo;
use App\Models\Muhasebe\StokHareketi;
use App\Models\Muhasebe\StokHareketiOlcuDagilimi;
use App\Models\Muhasebe\StokKarti;
use App\Models\Muhasebe\StokOlcuBakiyesi;
use App\Models\Muhasebe\StokOlcusu;
use App\Muhasebe\Enumlar\OlculuStokTakipTuru;
use App\Services\FirmaAyarDeposu;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Yalnız ölçü alt defterini yönetir. Kart/depo toplamlarını değiştirmez.
 * Dış transaction ile kart, depo ve hareketi atomik yöneten ana orkestratör
 * StokHareketServisi olmaya devam eder.
 */
class StokOlcuBakiyeServisi
{
    public function __construct(private readonly StokOlcuHesaplamaServisi $hesap, private readonly FirmaAyarDeposu $ayarlar) {}

    /** @param array<string, mixed> $veri */
    public function olcuOlustur(int $firmaId, StokKarti $stok, array $veri): StokOlcusu
    {
        if ((int) $stok->firma_id !== $firmaId) throw new InvalidArgumentException('Stok kartı farklı firmaya ait.');
        $tur = $stok->olculu_takip_turu instanceof OlculuStokTakipTuru ? $stok->olculu_takip_turu : OlculuStokTakipTuru::from((string) $stok->olculu_takip_turu);
        if (! $tur->olculuMu()) throw new InvalidArgumentException('Standart stok kartına ölçü eklenemez.');
        if (isset($veri['takip_turu']) && (string) $veri['takip_turu'] !== $tur->value) throw new InvalidArgumentException('Ölçünün takip türü stok kartıyla aynı olmalıdır.');
        $birim = (string) ($veri['olcu_birimi'] ?? 'cm');
        $normalize = function (string $alan) use ($veri, $birim): ?string {
            $deger = $veri[$alan] ?? null;
            return $deger === null || $deger === '' ? null : $this->hesap->metreyeCevir((string) $deger, $birim);
        };
        $enM = $normalize('en'); $boyM = $normalize('boy'); $yukseklikM = $normalize('yukseklik');
        $agirlikTuru = (string) ($veri['agirlik_turu'] ?? $stok->agirlik_turu ?? 'sabit');
        $agirlik = $veri['bir_adet_agirlik'] ?? null;
        $agirlikKg = $agirlik === null || $agirlik === ''
            ? null
            : $this->hesap->kilogramaCevir((string) $agirlik, (string) ($veri['agirlik_birimi'] ?? 'kg'));
        $faktor = ($tur === OlculuStokTakipTuru::Agirlik && $agirlikTuru === 'degisken' && empty($veri['bir_adet_agirlik']))
            ? null
            : $this->hesap->birAdetAnaMiktar($tur, ['en' => $enM, 'boy' => $boyM, 'yukseklik' => $yukseklikM, 'bir_adet_agirlik' => $agirlikKg]);

        return StokOlcusu::create(array_merge($veri, [
            'firma_id' => $firmaId, 'stok_id' => $stok->getKey(), 'takip_turu' => $tur->value,
            'en_m' => $enM, 'boy_m' => $boyM, 'yukseklik_m' => $yukseklikM,
            'bir_adet_ana_miktar' => $faktor, 'agirlik_turu' => $tur === OlculuStokTakipTuru::Agirlik ? $agirlikTuru : null,
            'agirlik_birimi' => $tur === OlculuStokTakipTuru::Agirlik ? (string) ($veri['agirlik_birimi'] ?? 'kg') : null,
        ]));
    }

    public function bakiyeBulVeyaOlustur(int $firmaId, StokKarti $stok, StokOlcusu $olcu, Depo $depo, ?string $donusum = null): StokOlcuBakiyesi
    {
        $this->baglantilariDogrula($firmaId, $stok, $olcu, $depo);

        return DB::transaction(function () use ($firmaId, $stok, $olcu, $depo, $donusum): StokOlcuBakiyesi {
            $sorgu = StokOlcuBakiyesi::withoutGlobalScopes()->where([
                'firma_id' => $firmaId, 'stok_id' => $stok->getKey(), 'stok_olcusu_id' => $olcu->getKey(),
                'depo_id' => $depo->getKey(),
            ])->lockForUpdate();
            $bakiye = $sorgu->first();
            if ($bakiye) {
                return $bakiye;
            }

            return StokOlcuBakiyesi::withoutGlobalScopes()->create([
                'firma_id' => $firmaId, 'stok_id' => $stok->getKey(), 'stok_olcusu_id' => $olcu->getKey(),
                'depo_id' => $depo->getKey(),
                'donusum_ana_miktari' => $donusum, 'durum' => 'aktif',
            ]);
        });
    }

    public function giris(StokOlcuBakiyesi $bakiye, ?string $anaMiktar = null, ?string $adet = null): StokOlcuBakiyesi
    {
        return $this->degistir($bakiye, $anaMiktar, $adet, true);
    }

    public function cikis(StokOlcuBakiyesi $bakiye, ?string $anaMiktar = null, ?string $adet = null): StokOlcuBakiyesi
    {
        return $this->degistir($bakiye, $anaMiktar, $adet, false);
    }

    public function rezervEt(StokOlcuBakiyesi $bakiye, string $anaMiktar): StokOlcuBakiyesi
    {
        return DB::transaction(function () use ($bakiye, $anaMiktar): StokOlcuBakiyesi {
            $kilitli = $this->kilitle($bakiye);
            $faktor = $this->faktor($kilitli);
            $yeni = bcadd((string) $kilitli->rezerve_ana_miktar, $this->hesap->kaydet($anaMiktar), 8);
            if (bccomp($yeni, (string) $kilitli->ana_miktar, 8) > 0) {
                throw new InvalidArgumentException('Rezerve ana miktar mevcut ölçü bakiyesini aşamaz.');
            }
            $kilitli->update(['rezerve_ana_miktar' => $yeni, 'rezerve_adet_esdegeri' => $this->hesap->anaMiktardanAdede($yeni, $faktor)]);
            return $kilitli->refresh();
        });
    }

    public function rezervCoz(StokOlcuBakiyesi $bakiye, string $anaMiktar): StokOlcuBakiyesi
    {
        return DB::transaction(function () use ($bakiye, $anaMiktar): StokOlcuBakiyesi {
            $kilitli = $this->kilitle($bakiye);
            $yeni = bcsub((string) $kilitli->rezerve_ana_miktar, $this->hesap->kaydet($anaMiktar), 8);
            if (bccomp($yeni, '0', 8) < 0) {
                throw new InvalidArgumentException('Çözülecek rezerv miktarı mevcut rezervi aşamaz.');
            }
            $kilitli->update(['rezerve_ana_miktar' => $yeni, 'rezerve_adet_esdegeri' => $this->hesap->anaMiktardanAdede($yeni, $this->faktor($kilitli))]);
            return $kilitli->refresh();
        });
    }

    /** @param array<int, array{bakiye: StokOlcuBakiyesi, ana_miktar: string, adet_esdegeri?: string, islem_birimi_id: int, girilen_miktar: string}> $dagilimlar */
    public function dagilimlariKaydet(StokHareketi $hareket, array $dagilimlar): void
    {
        DB::transaction(function () use ($hareket, $dagilimlar): void {
            $toplam = '0';
            foreach ($dagilimlar as $satir) {
                $toplam = bcadd($toplam, $satir['ana_miktar'], 8);
            }
            if (bccomp($toplam, (string) $hareket->miktar, 8) !== 0) {
                throw new InvalidArgumentException('Ölçü dağılımı toplamı stok hareketi miktarına eşit olmalıdır.');
            }
            foreach ($dagilimlar as $satir) {
                $bakiye = $this->kilitle($satir['bakiye']);
                $olcu = $bakiye->olcu()->withoutGlobalScopes()->firstOrFail();
                foreach (['firma_id', 'stok_id', 'depo_id'] as $alan) {
                    if ((int) $bakiye->{$alan} !== (int) $hareket->{$alan}) {
                        throw new InvalidArgumentException('Ölçü dağılımı hareketin firma, stok ve depo bağlantılarıyla uyumlu değil.');
                    }
                }
                $adet = $satir['adet_esdegeri'] ?? $this->hesap->anaMiktardanAdede($satir['ana_miktar'], $this->faktor($bakiye));
                StokHareketiOlcuDagilimi::create([
                    'firma_id' => $hareket->firma_id, 'stok_hareketi_id' => $hareket->getKey(), 'stok_id' => $hareket->stok_id,
                    'stok_olcusu_id' => $olcu->getKey(), 'stok_olcu_bakiyesi_id' => $bakiye->getKey(),
                    'depo_id' => $bakiye->depo_id, 'ana_miktar' => $satir['ana_miktar'], 'adet_esdegeri' => $adet,
                    'islem_birimi_id' => $satir['islem_birimi_id'], 'girilen_miktar' => $satir['girilen_miktar'],
                    'takip_turu' => $olcu->takip_turu->value, 'olcu_birimi' => $olcu->olcu_birimi, 'en' => $olcu->en, 'boy' => $olcu->boy,
                    'yukseklik' => $olcu->yukseklik, 'en_m' => $olcu->en_m, 'boy_m' => $olcu->boy_m, 'yukseklik_m' => $olcu->yukseklik_m,
                    'bir_adet_ana_miktar' => $this->faktor($bakiye),
                ]);
            }
        });
    }

    private function degistir(StokOlcuBakiyesi $bakiye, ?string $anaMiktar, ?string $adet, bool $giris): StokOlcuBakiyesi
    {
        return DB::transaction(function () use ($bakiye, $anaMiktar, $adet, $giris): StokOlcuBakiyesi {
            $kilitli = $this->kilitle($bakiye);
            $faktor = $this->faktor($kilitli);
            if ($anaMiktar === null && $adet === null) throw new InvalidArgumentException('Ana miktar veya adet eşdeğeri girilmelidir.');
            $ana = $anaMiktar === null ? $this->hesap->adettenAnaMiktara((string) $adet, $faktor) : $this->hesap->kaydet($anaMiktar);
            $adetSonuc = $adet === null ? $this->hesap->anaMiktardanAdede($ana, $faktor) : $this->hesap->kaydet($adet);
            $this->hesap->tutarliligiDogrula($ana, $adetSonuc, $faktor);
            $yeniAna = $giris ? bcadd((string) $kilitli->ana_miktar, $ana, 8) : bcsub((string) $kilitli->ana_miktar, $ana, 8);
            if (! $giris && bccomp($yeniAna, '0', 8) < 0 && ! (bool) $this->ayarlar->oku((int) $kilitli->firma_id, 'negatif_stok_izinli', false)) {
                throw new InvalidArgumentException('Yetersiz ölçü bakiyesi: negatif stoka izin verilmiyor.');
            }
            if (bccomp((string) $kilitli->rezerve_ana_miktar, $yeniAna, 8) > 0) throw new InvalidArgumentException('Çıkış sonrası rezerv miktarı bakiyeyi aşamaz.');
            $kilitli->update(['ana_miktar' => $yeniAna, 'adet_esdegeri' => $this->hesap->anaMiktardanAdede($yeniAna, $faktor)]);
            return $kilitli->refresh();
        });
    }

    private function kilitle(StokOlcuBakiyesi $bakiye): StokOlcuBakiyesi { return StokOlcuBakiyesi::withoutGlobalScopes()->whereKey($bakiye->getKey())->lockForUpdate()->firstOrFail(); }
    private function faktor(StokOlcuBakiyesi $bakiye): string { $f = $bakiye->donusum_ana_miktari ?: $bakiye->olcu()->withoutGlobalScopes()->value('bir_adet_ana_miktar'); if (! $f) throw new InvalidArgumentException('Değişken ağırlık için bakiye dönüşüm miktarı gereklidir.'); return (string) $f; }

    private function baglantilariDogrula(int $firmaId, StokKarti $stok, StokOlcusu $olcu, Depo $depo): void
    {
        if ((int) $stok->firma_id !== $firmaId || (int) $olcu->firma_id !== $firmaId || (int) $depo->firma_id !== $firmaId || (int) $olcu->stok_id !== (int) $stok->getKey()) throw new InvalidArgumentException('Ölçü bakiyesi bağlantıları aynı firmaya ve stok kartına ait olmalıdır.');
    }
}
