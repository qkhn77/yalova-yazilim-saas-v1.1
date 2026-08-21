<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\Masraf;
use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Masraf\Arac;
use App\Models\Masraf\MasrafAracDetayi;
use App\Models\Proje\IsletmeProjesi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MasrafKayitServisi
{
    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
        private readonly MasrafKaynakDogrulamaServisi $kaynakDogrulamaServisi,
    ) {}

    /**
     * Aynı idempotency anahtarı tekrar gelirse mevcut aktif/iptal kayıt döndürülür.
     * Masraf kaydı fatura değildir; bu nedenle cari veya finans hareketi üretmez.
     *
     * @param array{masraf_kategorisi_id:int, kaynak_turu?:string|null, kaynak_id?:int|string|null, tarih:string, tutar:string|int|float, para_birimi?:string, aciklama:string, notlar?:string|null} $alanlar
     */
    public function kaydet(int $firmaId, array $alanlar, ?int $kullaniciId, string $idempotencyKey): Masraf
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $kategori = MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->whereKey((int) $alanlar['masraf_kategorisi_id'])
            ->where('aktif_mi', true)
            ->where('secilir_mi', true)
            ->first();

        if (! $kategori) {
            throw new IsKuraliIstisnasi('Masraf türü bu firmaya ait değil, aktif değil veya alt tür olarak seçilemez.');
        }

        $kaynakTuru = trim((string) ($alanlar['kaynak_turu'] ?? ''));
        $kaynakId = (int) ($alanlar['kaynak_id'] ?? 0);
        if ($kaynakTuru !== '' && ! preg_match('/^[a-z0-9_:-]{1,64}$/', $kaynakTuru)) {
            throw new IsKuraliIstisnasi('Geçersiz masraf kaynak türü.');
        }
        if ($kaynakTuru === '' && $kaynakId > 0) {
            throw new IsKuraliIstisnasi('Masraf kaynağı seçilmeden kaynak kaydı kullanılamaz.');
        }
        if ($kaynakTuru !== '' && $kaynakId < 1) {
            throw new IsKuraliIstisnasi('Masraf kaynağı seçimi geçersiz.');
        }
        $this->kaynakDogrulamaServisi->dogrula($firmaId, $kaynakTuru, $kaynakId);

        $isletmeProjeId = (int) ($alanlar['isletme_proje_id'] ?? 0);
        if ($isletmeProjeId > 0 && ! IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->whereKey($isletmeProjeId)
            ->secilebilir()
            ->exists()) {
            throw new IsKuraliIstisnasi('Seçilen işletme projesi aktif firmaya ait değil veya masraf bağlantısına uygun değil.');
        }

        $tutar = $this->tutarNormalizeEt($alanlar['tutar'] ?? null);
        $paraBirimi = strtoupper(trim((string) ($alanlar['para_birimi'] ?? 'TRY')));
        if (! in_array($paraBirimi, ['TRY', 'USD', 'EUR', 'GBP'], true)) {
            throw new IsKuraliIstisnasi('Geçersiz para birimi.');
        }

        return DB::transaction(function () use ($firmaId, $alanlar, $kullaniciId, $idempotencyKey, $tutar, $paraBirimi, $kaynakTuru, $kaynakId, $isletmeProjeId): Masraf {
            $mevcut = Masraf::query()
                ->where('firma_id', $firmaId)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($mevcut) {
                return $mevcut;
            }

            $masraf = Masraf::query()->create([
                'firma_id' => $firmaId,
                'masraf_kategorisi_id' => (int) $alanlar['masraf_kategorisi_id'],
                'isletme_proje_id' => $isletmeProjeId > 0 ? $isletmeProjeId : null,
                'kaynak_turu' => $kaynakTuru !== '' ? $kaynakTuru : null,
                'kaynak_id' => $kaynakTuru !== '' ? $kaynakId : null,
                'tarih' => $alanlar['tarih'],
                'tutar' => $tutar,
                'para_birimi' => $paraBirimi,
                'aciklama' => trim((string) $alanlar['aciklama']),
                'notlar' => $alanlar['notlar'] ?? null,
                ...$this->belgeAlanlariniHazirla($firmaId, $alanlar, $kullaniciId),
                'durum' => Masraf::DURUM_AKTIF,
                'idempotency_key' => $idempotencyKey,
                'olusturan_kullanici_id' => $kullaniciId,
            ]);

            if ($kaynakTuru === MasrafKaynakDogrulamaServisi::ARAC) {
                $this->aracDetayiniKaydet($firmaId, $masraf, $kaynakId, $alanlar);
            }

            return $masraf;
        });
    }

    public function iptalEt(int $firmaId, int $masrafId, ?int $kullaniciId, ?string $neden = null): Masraf
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        return DB::transaction(function () use ($firmaId, $masrafId, $kullaniciId, $neden): Masraf {
            $masraf = Masraf::query()
                ->where('firma_id', $firmaId)
                ->whereKey($masrafId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($masraf->durum !== Masraf::DURUM_AKTIF) {
                return $masraf;
            }

            $masraf->update([
                'durum' => Masraf::DURUM_IPTAL,
                'iptal_eden_kullanici_id' => $kullaniciId,
                'iptal_nedeni' => $neden,
                'iptal_edildi_at' => now(),
            ]);

            return $masraf->fresh();
        });
    }

    /**
     * Aktif masrafın açıklama ve sınıflandırma alanlarını günceller.
     * Tutar ve para birimi bilinçli olarak değiştirilmez; fatura/rapor bağlantıları bozulmaz.
     *
     * @param array<string, mixed> $alanlar
     */
    public function guncelle(int $firmaId, int $masrafId, array $alanlar): Masraf
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $kategoriId = (int) ($alanlar['masraf_kategorisi_id'] ?? 0);
        $kategori = MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->whereKey($kategoriId)
            ->where('aktif_mi', true)
            ->where('secilir_mi', true)
            ->exists();
        if (! $kategori) {
            throw new IsKuraliIstisnasi('Masraf türü bu firmaya ait değil, aktif değil veya alt tür olarak seçilemez.');
        }

        $projeId = (int) ($alanlar['isletme_proje_id'] ?? 0);
        if ($projeId > 0 && ! IsletmeProjesi::query()
            ->where('firma_id', $firmaId)
            ->whereKey($projeId)
            ->secilebilir()
            ->exists()) {
            throw new IsKuraliIstisnasi('Seçilen işletme projesi aktif firmaya ait değil veya masraf bağlantısına uygun değil.');
        }

        $aciklama = trim((string) ($alanlar['aciklama'] ?? ''));
        if ($aciklama === '') {
            throw new IsKuraliIstisnasi('Masraf açıklaması zorunludur.');
        }

        return DB::transaction(function () use ($firmaId, $masrafId, $alanlar, $kategoriId, $projeId, $aciklama): Masraf {
            $masraf = Masraf::query()
                ->where('firma_id', $firmaId)
                ->whereKey($masrafId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($masraf->durum !== Masraf::DURUM_AKTIF) {
                throw new IsKuraliIstisnasi('İptal edilmiş masraf düzenlenemez.');
            }

            $masraf->update([
                'tarih' => $alanlar['tarih'],
                'masraf_kategorisi_id' => $kategoriId,
                'isletme_proje_id' => $projeId > 0 ? $projeId : null,
                'aciklama' => $aciklama,
                'notlar' => $alanlar['notlar'] ?? null,
                ...$this->belgeAlanlariniHazirla($firmaId, $alanlar, auth()->id() ? (int) auth()->id() : null, $masraf),
            ]);

            return $masraf->fresh();
        });
    }

    private function tutarNormalizeEt(mixed $tutar): string
    {
        $deger = str_replace([' ', ','], ['', '.'], trim((string) $tutar));
        if (! preg_match('/^\d{1,14}(?:\.\d{1,2})?$/', $deger)) {
            throw new IsKuraliIstisnasi('Tutar en fazla iki ondalık basamak içeren pozitif bir sayı olmalıdır.');
        }

        if (bccomp($deger, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Tutar sıfırdan büyük olmalıdır.');
        }

        return bcadd($deger, '0', 2);
    }

    /** @param array<string, mixed> $alanlar @return array<string, mixed> */
    private function belgeAlanlariniHazirla(int $firmaId, array $alanlar, ?int $kullaniciId, ?Masraf $mevcut = null): array
    {
        if (! array_key_exists('belge_yolu', $alanlar)) {
            return [];
        }

        $yol = trim((string) ($alanlar['belge_yolu'] ?? ''));
        if ($yol === '') {
            return [
                'belge_yolu' => null,
                'belge_adi' => null,
                'belge_mime' => null,
                'belge_boyutu' => null,
                'belge_yukleyen_kullanici_id' => null,
            ];
        }

        $izinliKlasor = 'masraflar/'.$firmaId.'/';
        if (! str_starts_with($yol, $izinliKlasor) || str_contains($yol, '..')) {
            throw new IsKuraliIstisnasi('Masraf belgesi aktif firmaya ait güvenli klasörde olmalıdır.');
        }

        $disk = Storage::disk('public');
        if (! $disk->exists($yol)) {
            throw new IsKuraliIstisnasi('Yüklenen masraf belgesi bulunamadı. Lütfen dosyayı yeniden seçin.');
        }

        return [
            'belge_yolu' => $yol,
            'belge_adi' => ($alanlar['belge_adi'] ?? null) ?: ($mevcut?->belge_adi ?: basename($yol)),
            'belge_mime' => $disk->mimeType($yol),
            'belge_boyutu' => $disk->size($yol),
            'belge_yukleyen_kullanici_id' => $kullaniciId,
        ];
    }

    /** @param array<string, mixed> $alanlar */
    private function aracDetayiniKaydet(int $firmaId, Masraf $masraf, int $aracId, array $alanlar): void
    {
        $litre = $this->opsiyonelOndalik($alanlar['yakit_litre'] ?? null, 3, 'Yakıt litre bilgisi');
        $litreFiyati = $this->opsiyonelOndalik($alanlar['litre_fiyati'] ?? null, 4, 'Litre fiyatı');
        $kilometre = $alanlar['kaynak_kilometre'] ?? null;

        if ($litre === null && $litreFiyati === null && ($kilometre === null || $kilometre === '')) {
            return;
        }

        if ($kilometre !== null && $kilometre !== '' && (! is_numeric($kilometre) || (int) $kilometre < 0)) {
            throw new IsKuraliIstisnasi('Araç kilometresi sıfır veya daha büyük bir sayı olmalıdır.');
        }

        MasrafAracDetayi::query()->create([
            'firma_id' => $firmaId,
            'masraf_id' => $masraf->getKey(),
            'arac_id' => $aracId,
            'yakit_litre' => $litre,
            'litre_fiyati' => $litreFiyati,
            'kilometre' => $kilometre !== null && $kilometre !== '' ? (int) $kilometre : null,
        ]);

        if ($kilometre !== null && $kilometre !== '') {
            Arac::query()
                ->where('firma_id', $firmaId)
                ->whereKey($aracId)
                ->where('kilometre', '<', (int) $kilometre)
                ->update(['kilometre' => (int) $kilometre]);
        }
    }

    private function opsiyonelOndalik(mixed $deger, int $basamak, string $alan): ?string
    {
        if ($deger === null || trim((string) $deger) === '') {
            return null;
        }

        $normalize = str_replace([' ', ','], ['', '.'], trim((string) $deger));
        if (! preg_match('/^\d{1,12}(?:\.\d{1,'.$basamak.'})?$/', $normalize)) {
            throw new IsKuraliIstisnasi($alan.' geçerli bir sayı olmalıdır.');
        }

        return bcadd($normalize, '0', $basamak);
    }
}
