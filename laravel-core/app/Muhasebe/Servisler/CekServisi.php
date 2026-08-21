<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Cari;
use App\Models\Muhasebe\Cek;
use App\Models\Muhasebe\CekHareketi;
use App\Models\Muhasebe\FinansHareketi;
use App\Muhasebe\Enumlar\CekDurumu;
use App\Muhasebe\Enumlar\CekHareketDurumu;
use App\Muhasebe\Enumlar\CekIslemTuru;
use App\Muhasebe\Enumlar\CekTuru;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CekServisi
{
    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
        private readonly FinansHareketServisi $finansHareketServisi,
    ) {}

    /**
     * Müşteriden alınan çeki portföye alır ve cari tahsilat hareketi üretir.
     *
     * @param array<string, mixed> $veri
     */
    public function girisKaydet(int $firmaId, array $veri): Cek
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $cariId = (int) ($veri['cari_id'] ?? 0);
        $paraBirimi = strtoupper(trim((string) ($veri['para_birimi'] ?? 'TRY')));
        $tutar = $this->tutarHazirla($veri['tutar'] ?? null);
        $cekNo = trim((string) ($veri['cek_no'] ?? ''));
        $islemTarihi = $this->tarihZorunlu($veri['islem_tarihi'] ?? null);
        $onGorselYolu = $this->gorselYolunuDogrula($firmaId, $veri['on_gorsel_yolu'] ?? null);
        $arkaGorselYolu = $this->gorselYolunuDogrula($firmaId, $veri['arka_gorsel_yolu'] ?? null);
        $idempotencyKey = $this->idempotencyKey('giris', $firmaId, [
            'cari_id' => $cariId,
            'cek_no' => $cekNo,
            'tutar' => $tutar,
            'para_birimi' => $paraBirimi,
            'islem_tarihi' => $islemTarihi,
            'vade_tarihi' => $veri['vade_tarihi'] ?? null,
        ]);

        if ($cekNo === '') {
            throw new IsKuraliIstisnasi('Çek numarası zorunludur.');
        }

        $this->cariyiDogrula($firmaId, $cariId, $paraBirimi);

        return DB::transaction(function () use ($firmaId, $veri, $cariId, $paraBirimi, $tutar, $cekNo, $islemTarihi, $idempotencyKey, $onGorselYolu, $arkaGorselYolu): Cek {
            $mevcut = CekHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($mevcut) {
                return $mevcut->cek()->firstOrFail();
            }

            $kullaniciId = Auth::id();
            $cek = Cek::query()->create([
                'firma_id' => $firmaId,
                'turu' => CekTuru::Alinan,
                'durum' => CekDurumu::Portfoyde,
                'cek_no' => $cekNo,
                'banka_adi' => $this->bosVeyaMetin($veri['banka_adi'] ?? null),
                'sube_adi' => $this->bosVeyaMetin($veri['sube_adi'] ?? null),
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'keside_tarihi' => $veri['keside_tarihi'] ?? null,
                'vade_tarihi' => $veri['vade_tarihi'] ?? null,
                'sorumlu_kullanici_id' => $kullaniciId,
                'olusturan_kullanici_id' => $kullaniciId,
                'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
                'on_gorsel_yolu' => $onGorselYolu,
                'arka_gorsel_yolu' => $arkaGorselYolu,
            ]);

            $hareket = CekHareketi::query()->create([
                'firma_id' => $firmaId,
                'cek_id' => $cek->getKey(),
                'islem_turu' => CekIslemTuru::Giris,
                'cari_id' => $cariId,
                'islem_yapan_kullanici_id' => $kullaniciId,
                'islem_tarihi' => $islemTarihi,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'idempotency_key' => $idempotencyKey,
                'durum' => CekHareketDurumu::Aktif,
                'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
            ]);

            $finans = $this->finansHareketServisi->cekCariHareketiKaydet(
                $firmaId,
                $cariId,
                $tutar,
                $paraBirimi,
                $islemTarihi,
                $veri['vade_tarihi'] ?? null,
                FinansHareketTuru::Tahsilat,
                (int) $cek->getKey(),
                $this->bosVeyaMetin($veri['aciklama'] ?? null),
            );

            $hareket->update(['finans_hareket_id' => $finans['finans']->getKey()]);

            return $cek->fresh();
        });
    }

    /**
     * İşletmenin kendi çekini veya portföydeki alınan çeki cariye verir.
     *
     * @param array<string, mixed> $veri
     */
    public function cikisKaydet(int $firmaId, array $veri): Cek
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $kaynak = (string) ($veri['kaynak'] ?? 'kendi');
        if (! in_array($kaynak, ['kendi', 'portfoy'], true)) {
            throw new IsKuraliIstisnasi('Geçersiz çek çıkış kaynağı.');
        }

        $cariId = (int) ($veri['cari_id'] ?? 0);
        $islemTarihi = $this->tarihZorunlu($veri['islem_tarihi'] ?? null);
        $cekId = (int) ($veri['cek_id'] ?? 0);
        $idempotencyKey = $this->idempotencyKey('cikis', $firmaId, [
            'kaynak' => $kaynak,
            'cek_id' => $cekId,
            'cari_id' => $cariId,
            'cek_no' => trim((string) ($veri['cek_no'] ?? '')),
            'tutar' => $kaynak === 'kendi' ? $this->tutarHazirla($veri['tutar'] ?? null) : null,
                'para_birimi' => strtoupper(trim((string) ($veri['para_birimi'] ?? 'TRY'))),
                'islem_tarihi' => $islemTarihi,
                'on_gorsel_yolu' => $kaynak === 'kendi' ? ($veri['on_gorsel_yolu'] ?? null) : null,
                'arka_gorsel_yolu' => $kaynak === 'kendi' ? ($veri['arka_gorsel_yolu'] ?? null) : null,
        ]);

        return DB::transaction(function () use ($firmaId, $veri, $kaynak, $cariId, $islemTarihi, $cekId, $idempotencyKey): Cek {
            $mevcut = CekHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($mevcut) {
                return $mevcut->cek()->firstOrFail();
            }

            if ($kaynak === 'portfoy') {
                $cek = Cek::query()
                    ->where('firma_id', $firmaId)
                    ->whereKey($cekId)
                    ->where('turu', CekTuru::Alinan->value)
                    ->where('durum', CekDurumu::Portfoyde->value)
                    ->lockForUpdate()
                    ->firstOrFail();
                $giris = $cek->girisHareketi()->where('durum', CekHareketDurumu::Aktif->value)->first();
                if (! $giris) {
                    throw new IsKuraliIstisnasi('Portföy çeki aktif giriş hareketine sahip değil.');
                }
                $tutar = (string) $cek->tutar;
                $paraBirimi = strtoupper((string) $cek->para_birimi);
                $vadeTarihi = $cek->vade_tarihi;
            } else {
                $tutar = $this->tutarHazirla($veri['tutar'] ?? null);
                $paraBirimi = strtoupper(trim((string) ($veri['para_birimi'] ?? 'TRY')));
                $cekNo = trim((string) ($veri['cek_no'] ?? ''));
                if ($cekNo === '') {
                    throw new IsKuraliIstisnasi('Çek numarası zorunludur.');
                }
                $cek = Cek::query()->create([
                    'firma_id' => $firmaId,
                    'turu' => CekTuru::Verilen,
                    'durum' => CekDurumu::Verildi,
                    'cek_no' => $cekNo,
                    'banka_adi' => $this->bosVeyaMetin($veri['banka_adi'] ?? null),
                    'sube_adi' => $this->bosVeyaMetin($veri['sube_adi'] ?? null),
                    'tutar' => $tutar,
                    'para_birimi' => $paraBirimi,
                    'keside_tarihi' => $veri['keside_tarihi'] ?? null,
                    'vade_tarihi' => $veri['vade_tarihi'] ?? null,
                    'sorumlu_kullanici_id' => Auth::id(),
                    'olusturan_kullanici_id' => Auth::id(),
                    'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
                    'on_gorsel_yolu' => $this->gorselYolunuDogrula($firmaId, $veri['on_gorsel_yolu'] ?? null),
                    'arka_gorsel_yolu' => $this->gorselYolunuDogrula($firmaId, $veri['arka_gorsel_yolu'] ?? null),
                ]);
                $vadeTarihi = $cek->vade_tarihi;
            }

            $this->cariyiDogrula($firmaId, $cariId, $paraBirimi);

            $hareket = CekHareketi::query()->create([
                'firma_id' => $firmaId,
                'cek_id' => $cek->getKey(),
                'islem_turu' => CekIslemTuru::Cikis,
                'cari_id' => $cariId,
                'islem_yapan_kullanici_id' => Auth::id(),
                'islem_tarihi' => $islemTarihi,
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'idempotency_key' => $idempotencyKey,
                'durum' => CekHareketDurumu::Aktif,
                'aciklama' => $this->bosVeyaMetin($veri['aciklama'] ?? null),
            ]);

            $finans = $this->finansHareketServisi->cekCariHareketiKaydet(
                $firmaId,
                $cariId,
                $tutar,
                $paraBirimi,
                $islemTarihi,
                $vadeTarihi,
                FinansHareketTuru::Odeme,
                (int) $cek->getKey(),
                $this->bosVeyaMetin($veri['aciklama'] ?? null),
            );

            $hareket->update(['finans_hareket_id' => $finans['finans']->getKey()]);
            $cek->update(['durum' => CekDurumu::Verildi]);

            return $cek->fresh();
        });
    }

    public function iptalEt(Cek $cek): Cek
    {
        $firmaId = (int) $cek->firma_id;
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        return DB::transaction(function () use ($cek, $firmaId): Cek {
            $kilitliCek = Cek::query()->where('firma_id', $firmaId)->whereKey($cek->getKey())->lockForUpdate()->firstOrFail();
            $hareket = CekHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('cek_id', $kilitliCek->getKey())
                ->where('durum', CekHareketDurumu::Aktif->value)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $hareket || ! $hareket->finans_hareket_id) {
                throw new IsKuraliIstisnasi('İptal edilecek aktif çek hareketi bulunamadı.');
            }

            $this->finansHareketServisi->tersKayitOlustur(
                $hareket->finansHareketi,
                'Çek hareketi iptali: #'.$hareket->getKey(),
            );
            $hareket->update(['durum' => CekHareketDurumu::Iptal]);

            $yeniDurum = $hareket->islem_turu === CekIslemTuru::Cikis
                && $kilitliCek->turu === CekTuru::Alinan
                ? CekDurumu::Portfoyde
                : CekDurumu::Iptal;
            $kilitliCek->update(['durum' => $yeniDurum]);

            return $kilitliCek->fresh();
        });
    }

    /**
     * Çek hareketini tersleyip aynı operasyonun düzeltilmiş karşılığını üretir.
     * Yeni finans kaydı eski kayda bağlanır; eski hareket hiçbir zaman silinmez.
     *
     * @param array<string, mixed> $veri
     */
    public function hareketIptalEtVeDuzelt(Cek $cek, array $veri): Cek
    {
        $firmaId = (int) $cek->firma_id;
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        return DB::transaction(function () use ($cek, $veri, $firmaId): Cek {
            $kilitli = Cek::query()->where('firma_id', $firmaId)->whereKey($cek->getKey())->lockForUpdate()->firstOrFail();
            $eskiHareket = CekHareketi::query()
                ->where('firma_id', $firmaId)
                ->where('cek_id', $kilitli->getKey())
                ->where('durum', CekHareketDurumu::Aktif->value)
                ->latest('id')->lockForUpdate()->first();

            if (! $eskiHareket || ! $eskiHareket->finans_hareket_id) {
                throw new IsKuraliIstisnasi('Düzeltilecek aktif çek hareketi bulunamadı.');
            }

            $eskiFinansId = (int) $eskiHareket->finans_hareket_id;
            $islemTarihi = $veri['islem_tarihi'] ?? now()->toDateTimeString();
            $aciklama = trim((string) ($veri['aciklama'] ?? '')) ?: 'Çek hareketi düzeltmesi';
            $this->iptalEt($kilitli);

            $ortak = [
                'cari_id' => (int) $eskiHareket->cari_id,
                'tutar' => $veri['tutar'] ?? $eskiHareket->tutar,
                'para_birimi' => (string) $eskiHareket->para_birimi,
                'islem_tarihi' => $islemTarihi,
                'vade_tarihi' => $kilitli->vade_tarihi,
                'cek_no' => (string) $kilitli->cek_no,
                'banka_adi' => $kilitli->banka_adi,
                'sube_adi' => $kilitli->sube_adi,
                'keside_tarihi' => $kilitli->keside_tarihi,
                'aciklama' => $aciklama,
            ];

            $yeniCek = $kilitli->turu === CekTuru::Verilen
                ? $this->cikisKaydet($firmaId, array_merge($ortak, [
                    'kaynak' => 'kendi',
                    'on_gorsel_yolu' => $kilitli->on_gorsel_yolu,
                    'arka_gorsel_yolu' => $kilitli->arka_gorsel_yolu,
                ]))
                : $this->girisKaydet($firmaId, array_merge($ortak, [
                    'on_gorsel_yolu' => $kilitli->on_gorsel_yolu,
                    'arka_gorsel_yolu' => $kilitli->arka_gorsel_yolu,
                ]));

            $yeniHareket = CekHareketi::query()->where('cek_id', $yeniCek->getKey())
                ->where('durum', CekHareketDurumu::Aktif->value)->latest('id')->firstOrFail();
            if ($yeniHareket->finans_hareket_id) {
                FinansHareketi::query()->whereKey($yeniHareket->finans_hareket_id)
                    ->update(['duzeltme_kaynagi_id' => $eskiFinansId]);
            }

            return $yeniCek->fresh();
        });
    }

    /** @param array<string, mixed> $parcalar */
    private function idempotencyKey(string $tur, int $firmaId, array $parcalar): string
    {
        ksort($parcalar);

        return hash('sha256', 'cek:'.$tur.':'.$firmaId.':'.json_encode($parcalar, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function tarihZorunlu(mixed $tarih): string
    {
        $tarih = trim((string) $tarih);
        if ($tarih === '') {
            throw new IsKuraliIstisnasi('İşlem tarihi zorunludur.');
        }

        return $tarih;
    }

    private function tutarHazirla(mixed $tutar): string
    {
        $tutar = trim((string) $tutar);
        if (str_contains($tutar, ',') && str_contains($tutar, '.')) {
            $tutar = str_replace('.', '', $tutar);
        }
        $tutar = str_replace(',', '.', $tutar);

        if ($tutar === '' || ! is_numeric($tutar) || bccomp($tutar, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Çek tutarı sıfırdan büyük olmalıdır.');
        }

        return number_format((float) $tutar, 2, '.', '');
    }

    private function bosVeyaMetin(mixed $deger): ?string
    {
        $deger = trim((string) $deger);

        return $deger === '' ? null : $deger;
    }

    private function gorselYolunuDogrula(int $firmaId, mixed $yol): ?string
    {
        $yol = trim((string) $yol);
        if ($yol === '') {
            return null;
        }

        $beklenenKlasor = 'muhasebe/cekler/'.$firmaId.'/';
        if (! str_starts_with($yol, $beklenenKlasor) || str_contains($yol, '..')) {
            throw new IsKuraliIstisnasi('Çek görseli aktif firmaya ait güvenli klasörde olmalıdır.');
        }

        if (! Storage::disk('public')->exists($yol)) {
            throw new IsKuraliIstisnasi('Çek görseli bulunamadı; lütfen görseli yeniden yükleyin.');
        }

        return $yol;
    }

    private function cariyiDogrula(int $firmaId, int $cariId, string $paraBirimi): Cari
    {
        $cari = Cari::query()->whereKey($cariId)->where('firma_id', $firmaId)->firstOrFail();
        if (strtoupper((string) ($cari->para_birimi ?: 'TRY')) !== strtoupper($paraBirimi)) {
            throw new IsKuraliIstisnasi('Cari para birimi ile çek para birimi uyuşmuyor.');
        }

        return $cari;
    }
}
