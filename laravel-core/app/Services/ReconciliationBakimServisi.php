<?php

namespace App\Services;

use App\Models\Ecommerce\Siparis;
use App\Models\Ecommerce\SiparisKalemi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaFinansKapama;
use App\Models\Muhasebe\FinansHareketi;
use App\Models\Muhasebe\StokKarti;
use App\Modules\Urun\Servisler\SiparisOdemeServisi;
use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Servisler\FaturaFinansKapamaServisi;
use App\Muhasebe\Servisler\FaturaKapamaDogrulamaServisi;
use App\Muhasebe\Servisler\FinansHareketServisi;
use App\Support\DenetimYardimcisi;
use Illuminate\Support\Facades\DB;
use Throwable;

class ReconciliationBakimServisi
{
    private const PARA_BASAMAK = 8;

    public function __construct(
        private readonly FaturaFinansKapamaServisi $faturaFinansKapamaServisi,
        private readonly FinansHareketServisi $finansHareketServisi,
        private readonly EcommerceFirmaAyarServisi $ecommerceFirmaAyarServisi,
        private readonly SiparisOdemeServisi $siparisOdemeServisi,
        private readonly SistemOlayServisi $sistemOlayServisi,
    ) {}

    /**
     * @return array{kontrol_edilen:int,bulunan:int,duzeltilen:int,sorunlar:array<int,array<string,mixed>>}
     */
    public function muhasebeReconcile(?int $firmaId = null, bool $fix = false): array
    {
        $sorunlar = [];
        $duzeltilen = 0;
        $kontrol = 0;

        $faturalar = Fatura::query()
            ->withoutGlobalScopes()
            ->when($firmaId, fn ($q) => $q->where('firma_id', $firmaId))
            ->orderBy('id')
            ->get(['id', 'firma_id', 'odendi_tutari', 'acik_tutar']);

        foreach ($faturalar as $fatura) {
            $kontrol++;
            $rapor = app(FaturaKapamaDogrulamaServisi::class)->faturaKapamaDurumuRaporla((int) $fatura->id);
            if ($rapor['hata'] !== null) {
                $sorun = [
                    'kod' => 'fatura.kapama_tutarsizligi',
                    'duzeltilebilir' => true,
                    'firma_id' => (int) $fatura->firma_id,
                    'fatura_id' => (int) $fatura->id,
                    'detay' => $rapor['hata'],
                ];
                $sorunlar[] = $sorun;
                DenetimYardimcisi::kaydet('reconcile.tutarsizlik_bulundu', Fatura::class, (int) $fatura->id, (int) $fatura->firma_id, null, $sorun);
                $this->sistemOlayServisi->olayKaydet('fatura.kapama_tutarsizligi', 'warning', 'Fatura kapama tutarsizligi bulundu.', $sorun);
                $this->sistemOlayServisi->olayKaydet('reconcile.tutarsizlik_bulundu', 'warning', 'Muhasebe reconcile tutarsizlik buldu.', $sorun);

                if ($fix) {
                    DenetimYardimcisi::kaydet('reconcile.fix_basladi', Fatura::class, (int) $fatura->id, (int) $fatura->firma_id, null, $sorun);
                    $this->sistemOlayServisi->olayKaydet('reconcile.fix_basladi', 'info', 'Fatura kapama fix basladi.', $sorun);
                    try {
                        $onceki = ['odendi_tutari' => (string) ($fatura->odendi_tutari ?? '0'), 'acik_tutar' => (string) ($fatura->acik_tutar ?? '0')];
                        $this->faturaFinansKapamaServisi->faturaOdemeDurumunuYenile((int) $fatura->id);
                        $guncel = Fatura::query()->withoutGlobalScopes()->find((int) $fatura->id);
                        $duzeltilen++;
                        DenetimYardimcisi::kaydet('reconcile.fix_basarili', Fatura::class, (int) $fatura->id, (int) $fatura->firma_id, $onceki, [
                            'odendi_tutari' => (string) ($guncel?->odendi_tutari ?? '0'),
                            'acik_tutar' => (string) ($guncel?->acik_tutar ?? '0'),
                        ]);
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_basarili', 'info', 'Fatura kapama fix basarili.', $sorun);
                    } catch (Throwable $e) {
                        DenetimYardimcisi::kaydet('reconcile.fix_hata', Fatura::class, (int) $fatura->id, (int) $fatura->firma_id, null, $sorun + ['hata' => $e->getMessage()]);
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_hata', 'error', 'Fatura kapama fix hatasi.', $sorun + ['hata' => $e->getMessage()]);
                    }
                }
            }
        }

        $finanslar = FinansHareketi::query()
            ->withoutGlobalScopes()
            ->when($firmaId, fn ($q) => $q->where('firma_id', $firmaId))
            ->where('durum', FinansHareketDurumu::Aktif)
            ->whereIn('tur', [FinansHareketTuru::Tahsilat, FinansHareketTuru::Odeme])
            ->orderBy('id')
            ->get(['id', 'firma_id', 'tutar', 'kullanilan_tutar', 'avans_tutar']);

        foreach ($finanslar as $finans) {
            $kontrol++;
            $gercekKullanilan = (string) (FaturaFinansKapama::query()->withoutGlobalScopes()->where('finans_hareket_id', $finans->id)->sum('uygulanan_tutar'));
            $gercekAvans = bcsub((string) $finans->tutar, $gercekKullanilan, self::PARA_BASAMAK);
            $kayitKullanilan = (string) ($finans->kullanilan_tutar ?? '0');
            $kayitAvans = (string) ($finans->avans_tutar ?? '0');

            if (bccomp($kayitKullanilan, $gercekKullanilan, self::PARA_BASAMAK) !== 0 || bccomp($kayitAvans, $gercekAvans, self::PARA_BASAMAK) !== 0) {
                $sorun = [
                    'kod' => 'finans.avans_kullanilan_tutarsizligi',
                    'duzeltilebilir' => true,
                    'firma_id' => (int) $finans->firma_id,
                    'finans_hareket_id' => (int) $finans->id,
                    'detay' => 'kullanilan_tutar/avans_tutar alanlari gercek kapama toplamiyla uyusmuyor',
                ];
                $sorunlar[] = $sorun;
                DenetimYardimcisi::kaydet('reconcile.tutarsizlik_bulundu', FinansHareketi::class, (int) $finans->id, (int) $finans->firma_id, null, $sorun);
                $this->sistemOlayServisi->olayKaydet('reconcile.tutarsizlik_bulundu', 'warning', 'Finans avans/kullanilan tutarsizligi bulundu.', $sorun);

                if ($fix) {
                    DenetimYardimcisi::kaydet('reconcile.fix_basladi', FinansHareketi::class, (int) $finans->id, (int) $finans->firma_id, null, $sorun);
                    $this->sistemOlayServisi->olayKaydet('reconcile.fix_basladi', 'info', 'Finans avans fix basladi.', $sorun);
                    try {
                        $onceki = ['kullanilan_tutar' => $kayitKullanilan, 'avans_tutar' => $kayitAvans];
                        FinansHareketi::query()->withoutGlobalScopes()->whereKey($finans->id)->update([
                            'kullanilan_tutar' => $gercekKullanilan,
                            'avans_tutar' => $gercekAvans,
                        ]);
                        $duzeltilen++;
                        DenetimYardimcisi::kaydet('reconcile.fix_basarili', FinansHareketi::class, (int) $finans->id, (int) $finans->firma_id, $onceki, [
                            'kullanilan_tutar' => $gercekKullanilan,
                            'avans_tutar' => $gercekAvans,
                        ]);
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_basarili', 'info', 'Finans avans fix basarili.', $sorun);
                    } catch (Throwable $e) {
                        DenetimYardimcisi::kaydet('reconcile.fix_hata', FinansHareketi::class, (int) $finans->id, (int) $finans->firma_id, null, $sorun + ['hata' => $e->getMessage()]);
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_hata', 'error', 'Finans avans fix hatasi.', $sorun + ['hata' => $e->getMessage()]);
                    }
                }
            }
        }

        return [
            'kontrol_edilen' => $kontrol,
            'bulunan' => count($sorunlar),
            'duzeltilen' => $duzeltilen,
            'sorunlar' => $sorunlar,
        ];
    }

    /**
     * @return array{kontrol_edilen:int,bulunan:int,duzeltilen:int,sorunlar:array<int,array<string,mixed>>}
     */
    public function ecommerceReconcile(?int $firmaId = null, bool $fix = false): array
    {
        $sorunlar = [];
        $duzeltilen = 0;
        $kontrol = 0;

        $siparisler = Siparis::query()
            ->withoutGlobalScopes()
            ->when($firmaId, fn ($q) => $q->where('firma_id', $firmaId))
            ->orderBy('id')
            ->get(['id', 'firma_id', 'durum', 'stok_dusuldu_mi', 'para_birimi', 'genel_toplam', 'siparis_no', 'odeme_suresi_bitis_at']);

        foreach ($siparisler as $siparis) {
            $kontrol++;
            $finansVar = FinansHareketi::query()->withoutGlobalScopes()
                ->where('firma_id', $siparis->firma_id)
                ->where('referans_turu', Siparis::REFERANS_TURU_FINANS)
                ->where('referans_id', $siparis->id)
                ->where('durum', FinansHareketDurumu::Aktif)
                ->exists();

            if (Siparis::odemeAlindiDurumMu((string) $siparis->durum) && ! $finansVar) {
                $sorun = [
                    'kod' => 'siparis.finans_tutarsizligi',
                    'duzeltilebilir' => true,
                    'firma_id' => (int) $siparis->firma_id,
                    'siparis_id' => (int) $siparis->id,
                    'detay' => 'Ödeme alınmış siparişte aktif finans kaydı yok',
                ];
                $sorunlar[] = $sorun;
                $this->sistemOlayServisi->olayKaydet('siparis.finans_tutarsizligi', 'error', 'Siparis-finans tutarsizligi bulundu.', $sorun);
                $this->sistemOlayServisi->olayKaydet('reconcile.tutarsizlik_bulundu', 'warning', 'Ecommerce reconcile tutarsizlik buldu.', $sorun);

                if ($fix) {
                    $this->sistemOlayServisi->olayKaydet('reconcile.fix_basladi', 'info', 'Siparis finans fix basladi.', $sorun);
                    try {
                        $ids = $this->ecommerceFirmaAyarServisi->tahsilatIds((int) $siparis->firma_id);
                        if (($ids['cari_id'] ?? null) && ($ids['kasa_id'] ?? null)) {
                            $this->finansHareketServisi->tahsilatKasadanEcommerceKaydet(
                                (int) $siparis->firma_id,
                                (int) $ids['cari_id'],
                                (int) $ids['kasa_id'],
                                (string) $siparis->genel_toplam,
                                (string) ($siparis->para_birimi ?? 'TRY'),
                                now(),
                                'Reconcile auto-fix: '.$siparis->siparis_no,
                                Siparis::REFERANS_TURU_FINANS,
                                (int) $siparis->id,
                            );
                            $duzeltilen++;
                            $this->sistemOlayServisi->olayKaydet('reconcile.fix_basarili', 'info', 'Siparis finans fix basarili.', $sorun);
                        } else {
                            $this->sistemOlayServisi->olayKaydet('reconcile.fix_hata', 'warning', 'Siparis finans fix ayar eksigi nedeniyle atlandi.', $sorun);
                        }
                    } catch (Throwable $e) {
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_hata', 'error', 'Siparis finans fix hatasi.', $sorun + ['hata' => $e->getMessage()]);
                    }
                }
            }

            if (in_array((string) $siparis->durum, [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR], true)
                && $siparis->odeme_suresi_bitis_at !== null
                && now()->gt($siparis->odeme_suresi_bitis_at)) {
                $sorun = [
                    'kod' => 'siparis.timeout_artigi',
                    'duzeltilebilir' => true,
                    'firma_id' => (int) $siparis->firma_id,
                    'siparis_id' => (int) $siparis->id,
                    'detay' => 'Onay bekleyen fakat timeout olmuş sipariş',
                ];
                $sorunlar[] = $sorun;
                $this->sistemOlayServisi->olayKaydet('reconcile.tutarsizlik_bulundu', 'warning', 'Timeout siparis artigi bulundu.', $sorun);

                if ($fix) {
                    try {
                        $this->siparisOdemeServisi->siparisZamanAsimindaIptal($siparis);
                        $duzeltilen++;
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_basarili', 'info', 'Timeout siparis iptal fix basarili.', $sorun);
                    } catch (Throwable $e) {
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_hata', 'error', 'Timeout siparis fix hatasi.', $sorun + ['hata' => $e->getMessage()]);
                    }
                }
            }
        }

        return [
            'kontrol_edilen' => $kontrol,
            'bulunan' => count($sorunlar),
            'duzeltilen' => $duzeltilen,
            'sorunlar' => $sorunlar,
        ];
    }

    /**
     * @return array{kontrol_edilen:int,bulunan:int,duzeltilen:int,sorunlar:array<int,array<string,mixed>>}
     */
    public function stokRezervReconcile(?int $firmaId = null, bool $fix = false): array
    {
        $sorunlar = [];
        $duzeltilen = 0;
        $kontrol = 0;

        $stoklar = StokKarti::query()
            ->withoutGlobalScopes()
            ->when($firmaId, fn ($q) => $q->where('firma_id', $firmaId))
            ->where('stok_takip', true)
            ->orderBy('id')
            ->get(['id', 'firma_id', 'rezerve_miktar', 'stok_miktari']);

        foreach ($stoklar as $stok) {
            $kontrol++;
            $beklenenRezerv = (string) (SiparisKalemi::query()
                ->withoutGlobalScopes()
                ->join('siparisler', 'siparisler.id', '=', 'siparis_kalemleri.siparis_id')
                ->where('siparisler.firma_id', $stok->firma_id)
                ->whereIn('siparisler.durum', [Siparis::DURUM_ONAY_BEKLIYOR, Siparis::DURUM_ODEME_BEKLENIYOR])
                ->where('siparis_kalemleri.stok_karti_id', $stok->id)
                ->sum('siparis_kalemleri.stok_rezerv_miktari'));

            $kayitRezerv = (string) ($stok->rezerve_miktar ?? '0');
            $uyumsuz = bccomp($kayitRezerv, $beklenenRezerv, 4) !== 0;
            $rezervStoktanBuyuk = bccomp((string) $kayitRezerv, (string) ($stok->stok_miktari ?? '0'), 4) === 1;

            if ($uyumsuz || $rezervStoktanBuyuk) {
                $sorun = [
                    'kod' => 'stok.rezerv_tutarsizligi',
                    'duzeltilebilir' => true,
                    'firma_id' => (int) $stok->firma_id,
                    'stok_id' => (int) $stok->id,
                    'detay' => 'rezerve_miktar beklenenle uyusmuyor veya rezerv stoktan buyuk',
                ];
                $sorunlar[] = $sorun;
                $this->sistemOlayServisi->olayKaydet('stok.rezerv_tutarsizligi', 'warning', 'Stok rezerv tutarsizligi bulundu.', $sorun);
                $this->sistemOlayServisi->olayKaydet('reconcile.tutarsizlik_bulundu', 'warning', 'Stok reconcile tutarsizlik buldu.', $sorun);

                if ($fix) {
                    $this->sistemOlayServisi->olayKaydet('reconcile.fix_basladi', 'info', 'Stok rezerv fix basladi.', $sorun);
                    try {
                        DB::transaction(function () use ($stok, $beklenenRezerv): void {
                            StokKarti::query()->withoutGlobalScopes()->whereKey($stok->id)->lockForUpdate()->update([
                                'rezerve_miktar' => $beklenenRezerv,
                            ]);
                        });
                        $duzeltilen++;
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_basarili', 'info', 'Stok rezerv fix basarili.', $sorun);
                    } catch (Throwable $e) {
                        $this->sistemOlayServisi->olayKaydet('reconcile.fix_hata', 'error', 'Stok rezerv fix hatasi.', $sorun + ['hata' => $e->getMessage()]);
                    }
                }
            }
        }

        return [
            'kontrol_edilen' => $kontrol,
            'bulunan' => count($sorunlar),
            'duzeltilen' => $duzeltilen,
            'sorunlar' => $sorunlar,
        ];
    }
}
