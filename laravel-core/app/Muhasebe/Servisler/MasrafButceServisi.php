<?php

namespace App\Muhasebe\Servisler;

use App\Models\Muhasebe\MasrafKategorisi;
use App\Models\Masraf\MasrafButcesi;
use App\Muhasebe\Exceptions\IsKuraliIstisnasi;
use App\Muhasebe\Guvenlik\MuhasebeFirmaErisimDenetleyicisi;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class MasrafButceServisi
{
    public function __construct(
        private readonly MuhasebeFirmaErisimDenetleyicisi $firmaDenetleyicisi,
    ) {}

    /** @param array<string, mixed> $alanlar */
    public function kaydet(int $firmaId, array $alanlar, ?int $butceId = null): MasrafButcesi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $kategoriId = (int) ($alanlar['masraf_kategorisi_id'] ?? 0);
        $kategori = MasrafKategorisi::query()
            ->where('firma_id', $firmaId)
            ->whereKey($kategoriId)
            ->where('aktif_mi', true)
            ->where('secilir_mi', true)
            ->first();
        if (! $kategori) {
            throw new IsKuraliIstisnasi('Bütçe yalnızca aktif ve seçilebilir bir alt masraf türüne bağlanabilir.');
        }

        $baslangic = Carbon::parse((string) ($alanlar['donem_baslangic'] ?? ''))->toDateString();
        $bitis = Carbon::parse((string) ($alanlar['donem_bitis'] ?? ''))->toDateString();
        if ($baslangic > $bitis) {
            throw new IsKuraliIstisnasi('Bütçe dönem başlangıcı bitiş tarihinden sonra olamaz.');
        }

        $tutar = str_replace([' ', ','], ['', '.'], trim((string) ($alanlar['butce_tutari'] ?? '')));
        if (! preg_match('/^\d{1,14}(?:\.\d{1,2})?$/', $tutar) || bccomp($tutar, '0', 2) <= 0) {
            throw new IsKuraliIstisnasi('Bütçe tutarı sıfırdan büyük ve en fazla iki ondalık basamaklı olmalıdır.');
        }

        $paraBirimi = strtoupper(trim((string) ($alanlar['para_birimi'] ?? 'TRY')));
        if (! in_array($paraBirimi, ['TRY', 'USD', 'EUR', 'GBP'], true)) {
            throw new IsKuraliIstisnasi('Geçersiz bütçe para birimi.');
        }

        $ayni = MasrafButcesi::query()
            ->where('firma_id', $firmaId)
            ->where('masraf_kategorisi_id', $kategoriId)
            ->whereDate('donem_baslangic', $baslangic)
            ->whereDate('donem_bitis', $bitis)
            ->where('para_birimi', $paraBirimi)
            ->when($butceId !== null, fn ($query) => $query->whereKeyNot($butceId))
            ->exists();
        if ($ayni) {
            throw new IsKuraliIstisnasi('Bu kategori ve dönem için aynı para biriminde bütçe zaten tanımlı.');
        }

        return DB::transaction(function () use ($firmaId, $alanlar, $butceId, $kategoriId, $baslangic, $bitis, $tutar, $paraBirimi): MasrafButcesi {
            $butce = $butceId === null
                ? new MasrafButcesi()
                : MasrafButcesi::query()->where('firma_id', $firmaId)->whereKey($butceId)->firstOrFail();

            $butce->fill([
                'firma_id' => $firmaId,
                'masraf_kategorisi_id' => $kategoriId,
                'donem_baslangic' => $baslangic,
                'donem_bitis' => $bitis,
                'butce_tutari' => bcadd($tutar, '0', 2),
                'para_birimi' => $paraBirimi,
                'durum' => $alanlar['durum'] ?? MasrafButcesi::DURUM_AKTIF,
                'notlar' => $alanlar['notlar'] ?? null,
            ])->save();

            return $butce->fresh();
        });
    }

    public function durumDegistir(int $firmaId, int $butceId): MasrafButcesi
    {
        $this->firmaDenetleyicisi->yazmaIcinFirmaKontrolEt($firmaId);

        $butce = MasrafButcesi::query()->where('firma_id', $firmaId)->whereKey($butceId)->firstOrFail();
        $butce->update(['durum' => $butce->durum === MasrafButcesi::DURUM_AKTIF ? MasrafButcesi::DURUM_KAPALI : MasrafButcesi::DURUM_AKTIF]);

        return $butce->fresh();
    }
}
