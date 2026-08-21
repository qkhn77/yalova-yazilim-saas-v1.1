<?php

namespace App\Muhasebe\Servisler;

use App\Filament\Clusters\Muhasebe\Pages\StokDepoSayimGecmisiSayfasi;
use App\Filament\Clusters\Muhasebe\Pages\StokDepoTransferGecmisiSayfasi;
use App\Models\FirmaKullanici;
use App\Models\Iletisim\KullaniciBildirimi;
use App\Models\Muhasebe\StokTransferi;
use App\Models\User;
use App\Services\MesajMerkeziServisi;
use App\Services\YetkiService;
use App\Services\FirmaAyarDeposu;
use App\Support\MuhasebeYetkiSablonlari;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Depo işlemlerini, yalnızca ilgili depoyu görüntüleme yetkisi olan firma
 * kullanıcılarına panel bildirimi olarak iletir.
 */
class DepoBildirimServisi
{
    public function __construct(
        private readonly YetkiService $yetkiServisi,
        private readonly MesajMerkeziServisi $mesajMerkezi,
        private readonly FirmaAyarDeposu $firmaAyarDeposu,
    ) {}

    public function transferKaydedildi(StokTransferi $transfer): void
    {
        $transfer->loadMissing(['cikisHareketi.stokKarti:id,ad,kod', 'kaynakDepo:id,ad', 'hedefDepo:id,ad']);

        $stokAdi = trim((string) ($transfer->cikisHareketi?->stokKarti?->ad ?? 'Stok'));
        $miktar = number_format((float) ($transfer->cikisHareketi?->miktar ?? 0), 4, ',', '.');
        $mesaj = sprintf(
            '%s ürünü %s deposundan %s deposuna %s miktar transfer edildi.',
            $stokAdi,
            $transfer->kaynakDepo?->ad ?? 'Kaynak depo',
            $transfer->hedefDepo?->ad ?? 'Hedef depo',
            $miktar,
        );

        $this->bildir(
            (int) $transfer->firma_id,
            'Depo transferi tamamlandı',
            $mesaj,
            StokDepoTransferGecmisiSayfasi::getUrl(['transfer' => $transfer->id]),
            (int) $transfer->id,
            ['olay' => 'transfer', 'transfer_id' => (int) $transfer->id],
        );
    }

    public function sayimKaydedildi(int $firmaId, int $stokId, int $depoId, string|int|float $fark, string $stokAdi, string $depoAdi): void
    {
        $farkMetni = number_format((float) $fark, 4, ',', '.');
        $mesaj = sprintf('%s / %s için sayım tamamlandı. Stok farkı: %s.', $stokAdi, $depoAdi, $farkMetni);

        $this->bildir(
            $firmaId,
            'Depo sayımı kaydedildi',
            $mesaj,
            StokDepoSayimGecmisiSayfasi::getUrl(['stok_id' => $stokId, 'depo_id' => $depoId]),
            $stokId,
            ['olay' => 'sayim', 'stok_id' => $stokId, 'depo_id' => $depoId, 'fark' => (string) $fark],
        );
    }

    private function bildir(int $firmaId, string $baslik, string $mesaj, string $aksiyonUrl, int $kaynakId, array $data): void
    {
        if ($firmaId < 1) {
            return;
        }

        if (! (bool) $this->firmaAyarDeposu->oku($firmaId, 'stok_depo_bildirimleri_aktif_mi', true)) {
            return;
        }

        try {
            $gonderenId = (int) Auth::id();
            $aliciIds = FirmaKullanici::query()
                ->where('firma_id', $firmaId)
                ->where('durum', 'aktif')
                ->whereNull('deleted_at')
                ->pluck('kullanici_id')
                ->map(fn ($id): int => (int) $id)
                ->unique()
                ->filter(fn (int $id): bool => $id !== $gonderenId)
                ->filter(function (int $id) use ($firmaId): bool {
                    $kullanici = User::query()->find($id);

                    return $kullanici instanceof User
                        && $this->yetkiServisi->yetkiVarMi($kullanici, $firmaId, MuhasebeYetkiSablonlari::DEPO_GORUNTULE);
                })
                ->values();

            if ($aliciIds->isEmpty()) {
                return;
            }

            $simdi = now();
            $bildirimler = $aliciIds->map(fn (int $kullaniciId): array => [
                'firma_id' => $firmaId,
                'kullanici_id' => $kullaniciId,
                'kaynak_turu' => self::class,
                'kaynak_id' => $kaynakId,
                'baslik' => $baslik,
                'mesaj' => $mesaj,
                'seviye' => 'bilgi',
                'okundu_at' => null,
                'aksiyon_url' => $aksiyonUrl,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'created_at' => $simdi,
                'updated_at' => $simdi,
            ]);

            foreach ($bildirimler->chunk(200) as $parca) {
                KullaniciBildirimi::query()->insert($parca->all());
            }

            foreach ($aliciIds as $kullaniciId) {
                $this->mesajMerkezi->sayacCacheTemizle($kullaniciId, $firmaId);
                $this->mesajMerkezi->akisCacheTemizle($kullaniciId, $firmaId);
            }
        } catch (Throwable $exception) {
            // Bildirim arızası depo hareketinin başarılı kaydını engellememeli.
            Log::warning('Depo işlemi bildirimi oluşturulamadı.', [
                'firma_id' => $firmaId,
                'baslik' => $baslik,
                'exception' => $exception,
            ]);
        }
    }
}
