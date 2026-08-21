<?php

namespace App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Muhasebe\Fatura;
use App\Muhasebe\Guvenlik\MuhasebeFilamentErisimYardimcisi;
use App\Muhasebe\Enumlar\FaturaDurumu;
use App\Muhasebe\Servisler\FaturaIslemServisi;
use App\Muhasebe\Servisler\FaturaOlcuKalemiServisi;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditFatura extends EditRecord
{
    protected static string $resource = FaturaKaynagi::class;

    public bool $goruntule = false;
    private bool $onayIsteniyor = false;

    public function mount(int|string $record): void
    {
        $path = trim((string) request()->path(), '/');
        $this->goruntule = request()->boolean('goruntule') || ! str_ends_with($path, '/edit');

        parent::mount($record);
    }

    protected function getHeaderActions(): array
    {
        $kalemDetayModu = FaturaKaynagi::kalemDetaylariGoster();
        $anaUrl = FaturaKaynagi::getUrl('edit', ['record' => (int) $this->record->getKey()]);

        if (! $kalemDetayModu) {
            return [
                Actions\Action::make('detaylar')
                    ->label('Detaylar')
                    ->url($anaUrl),
            ];
        }

        return [
            Actions\Action::make('duzenle')
                ->label(fn (Fatura $record): string => $record->durum === FaturaDurumu::Onayli
                    ? 'Düzenlenemez'
                    : 'Düzenle')
                ->icon(fn (Fatura $record): string => $record->durum === FaturaDurumu::Onayli
                    ? 'heroicon-o-lock-closed'
                    : 'heroicon-o-pencil-square')
                ->color(fn (Fatura $record): string => $record->durum === FaturaDurumu::Onayli ? 'info' : 'gray')
                ->tooltip(fn (Fatura $record): string => $record->durum === FaturaDurumu::Onayli
                    ? 'Onaylı faturalar düzenlenemez. Değişiklik için önce iptal veya iade akışını kullanın.'
                    : 'Faturayı hızlı düzenle')
                ->disabled(fn (Fatura $record): bool => $record->durum === FaturaDurumu::Onayli)
                ->url($anaUrl),
            ...($kalemDetayModu ? [
            Actions\DeleteAction::make()->visible(fn (Fatura $record) => $record->durum === FaturaDurumu::Taslak),
            Actions\Action::make('onayla')
                ->label(fn (Fatura $record): string => static::faturaNumarasiEksikMi($record) ? 'Fatura no tamamla' : 'Onayla')
                ->visible(fn (Fatura $record): bool => static::faturaYetkisiVarMi('onay') && (in_array($record->durum, [FaturaDurumu::Taslak, FaturaDurumu::Beklemede], true) || static::faturaNumarasiEksikMi($record)))
                ->disabled(fn (Fatura $record): bool => ! in_array($record->durum, [FaturaDurumu::Taslak, FaturaDurumu::Beklemede], true) && ! static::faturaNumarasiEksikMi($record))
                ->requiresConfirmation()
                ->action(function (Fatura $record): void {
                    static::faturaYetkisiniDogrula('onay');
                    try {
                        foreach ($record->load('kalemler')->kalemler as $kalem) {
                            app(FaturaOlcuKalemiServisi::class)->tekOlcuDagiliminiOtomatikTamamla(
                                $kalem,
                                in_array($record->tur->kanonik(), [\App\Muhasebe\Enumlar\FaturaTuru::Giden, \App\Muhasebe\Enumlar\FaturaTuru::AlisIadesi], true),
                            );
                        }
                        app(FaturaIslemServisi::class)->faturayiOnayla($record);
                        $record->refresh();

                        Notification::make()
                            ->success()
                            ->title(static::faturaNumarasiEksikMi($record) ? 'Fatura numarası tamamlandı' : 'Fatura onaylandı')
                            ->body('İşlem tamamlandı. Faturanın mevcut durumu: '.($record->durum?->value ?? 'belirsiz').'.')
                            ->persistent()
                            ->send();
                    } catch (Throwable $e) {
                        report($e);

                        Notification::make()
                            ->danger()
                            ->title('Fatura onaylanamadı')
                            ->body($e->getMessage().' Fatura taslak olarak bırakıldı; eksikleri düzelttikten sonra tekrar deneyebilirsiniz.')
                            ->persistent()
                            ->send();
                    }
                }),
            Actions\Action::make('iptal')
                ->label('İptal Et')
                ->color('danger')
                ->visible(fn (Fatura $record) => static::faturaYetkisiVarMi('guncelle') && $record->durum !== FaturaDurumu::Iptal)
                ->disabled(fn (Fatura $record) => $record->durum === FaturaDurumu::Iptal)
                ->requiresConfirmation()
                ->action(function (Fatura $record): void {
                    static::faturaYetkisiniDogrula('guncelle');
                    try {
                        app(FaturaIslemServisi::class)->faturayiIptalEt($record);
                        $record->refresh();

                        Notification::make()
                            ->warning()
                            ->title('Fatura iptal edildi')
                            ->body('Cari ve stok hareketleri ters kayıtla kapatıldı. Faturanın mevcut durumu: '.($record->durum?->value ?? 'belirsiz').'.')
                            ->persistent()
                            ->send();
                    } catch (Throwable $e) {
                        report($e);

                        Notification::make()
                            ->danger()
                            ->title('Fatura iptal edilemedi')
                            ->body($e->getMessage().' Fatura ve bağlı finans hareketleri değiştirilmedi.')
                            ->persistent()
                            ->send();
                    }
                }),
            Actions\Action::make('iade')
                ->label(fn (Fatura $record): string => static::faturaTamamenIadeEdildiMi($record) ? 'İade tamamlandı' : 'İade Et')
                ->visible(fn (Fatura $record) => static::faturaYetkisiVarMi('guncelle')
                    && $record->durum === FaturaDurumu::Onayli
                    && in_array($record->tur?->kanonik(), [\App\Muhasebe\Enumlar\FaturaTuru::Gelen, \App\Muhasebe\Enumlar\FaturaTuru::Giden], true))
                ->disabled(fn (Fatura $record) => $record->durum !== FaturaDurumu::Onayli || static::faturaTamamenIadeEdildiMi($record))
                ->tooltip(fn (Fatura $record): string => static::faturaTamamenIadeEdildiMi($record)
                    ? 'Bu faturanın tüm kalemleri iade edilmiş.'
                    : 'Yeni bağlı iade faturası oluştur')
                ->requiresConfirmation()
                ->action(function (Fatura $record): void {
                    static::faturaYetkisiniDogrula('guncelle');
                    $iadeSayfasi = $record->tur?->kanonik() === \App\Muhasebe\Enumlar\FaturaTuru::Gelen
                        ? 'createGelenIade'
                        : 'createGidenIade';

                    redirect(FaturaKaynagi::getUrl($iadeSayfasi, [
                        'kaynak_fatura_id' => (int) $record->getKey(),
                    ]));
                }),
            ] : []),
        ];
    }

    private static function faturaNumarasiEksikMi(Fatura $record): bool
    {
        return $record->durum === FaturaDurumu::Onayli
            && $record->tur->kayitUretirMi()
            && trim((string) $record->fatura_no) === '';
    }

    private static function faturaYetkisiVarMi(string $islem): bool
    {
        return MuhasebeFilamentErisimYardimcisi::muhasebeYetkisiVarMi(match ($islem) {
            'onay' => \App\Support\MuhasebeYetkiSablonlari::FATURA_ONAY,
            'guncelle' => \App\Support\MuhasebeYetkiSablonlari::FATURA_GUNCELLE,
            default => \App\Support\MuhasebeYetkiSablonlari::FATURA_GORUNTULE,
        });
    }

    private static function faturaTamamenIadeEdildiMi(Fatura $record): bool
    {
        $kaynakTur = $record->tur?->kanonik();
        $iadeTur = $kaynakTur === \App\Muhasebe\Enumlar\FaturaTuru::Gelen
            ? \App\Muhasebe\Enumlar\FaturaTuru::AlisIadesi
            : \App\Muhasebe\Enumlar\FaturaTuru::SatisIadesi;

        $kaynakKalemleri = $record->onayKalemleri()->get();
        if ($kaynakKalemleri->isEmpty()) {
            return false;
        }

        foreach ($kaynakKalemleri as $kaynakKalem) {
            $iadeAlan = $kaynakKalem->ana_miktar !== null ? 'ana_miktar' : 'miktar';
            $uit = \App\Models\Muhasebe\FaturaKalemi::withoutGlobalScopes()
                ->where('kaynak_fatura_kalemi_id', $kaynakKalem->getKey())
                ->whereHas('fatura', fn ($fatura) => $fatura
                    ->withoutGlobalScopes()
                    ->where('firma_id', $record->firma_id)
                    ->where('bagli_fatura_id', $record->getKey())
                    ->where('tur', $iadeTur->value)
                    ->where('durum', FaturaDurumu::Onayli->value))
                ->sum($iadeAlan);
            $kaynakMiktar = (float) ($kaynakKalem->{$iadeAlan} ?? 0);
            if ((float) $uit + 0.00000001 < $kaynakMiktar) {
                return false;
            }
        }

        return true;
    }

    private static function faturaYetkisiniDogrula(string $islem): void
    {
        if (! static::faturaYetkisiVarMi($islem)) {
            throw new \Illuminate\Auth\Access\AuthorizationException('Bu fatura işlemi için yetkiniz yok.');
        }
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (! FaturaKaynagi::kalemDetaylariGoster()) {
            return $data;
        }

        // EditRecord yalnızca ana modelin attributesToArray() çıktısını
        // mutateFormDataBeforeFill() metoduna verir; ilişki verileri daha
        // sonra Repeater tarafından yüklenir. Bu yüzden özet hesaplaması için
        // kalemleri burada açıkça form verisine ekliyoruz.
        if (! array_key_exists('kalemler', $data)) {
            $kalemler = $this->record->relationLoaded('kalemler')
                ? $this->record->kalemler
                : $this->record->load('kalemler')->kalemler;

            $data['kalemler'] = $kalemler
                ->map(fn ($kalem): array => $kalem->attributesToArray())
                ->values()
                ->all();
        }

        if (is_array($data['kalemler'] ?? null)) {
            $malHizmetToplam = 0.0;
            foreach ($data['kalemler'] as $index => &$kalem) {
                if (! is_array($kalem)) {
                    continue;
                }

                if (! empty($kalem['stok_id'])) {
                    $kalem['stok_kodu_secim'] = (string) $kalem['stok_id'];
                }

                if (! isset($kalem['sira_no'])) {
                    $kalem['sira_no'] = (int) ($kalem['satir_no'] ?? $index + 1);
                }

                if (! array_key_exists('net_toplam_gosterim', $kalem) || $kalem['net_toplam_gosterim'] === null || $kalem['net_toplam_gosterim'] === '') {
                    $kalem['net_toplam_gosterim'] = $kalem['toplam'] ?? $kalem['satir_genel_toplam'] ?? 0;
                }

                $malHizmetToplam += (float) ($kalem['satir_toplami'] ?? 0);
            }
            unset($kalem);

            $data['mal_hizmet_toplam_tutari_gosterim'] = $malHizmetToplam;

            // Eski veya yarım kalmış kayıtlarda kalemler veritabanına yazılmış
            // olsa da fatura başlık toplamları 0 kalmış olabilir. Ekran,
            // özellikle salt-okunur görünüm, başlıkta duran eski değerleri
            // göstermemeli; kalemlerden hesaplanan güncel özeti göstermelidir.
            $hesapliOzet = FaturaKaynagi::hesaplaFormKalemleriVeOzet($data);
            foreach ([
                'toplam_indirim',
                'kdv_toplam',
                'genel_toplam',
                'tevkifat_orani',
                'tevkifat_tutari_gosterim',
                'odenecek_tutar',
                'acik_tutar',
                'genel_indirim_tutari',
                'ara_toplam',
            ] as $alan) {
                if (array_key_exists($alan, $hesapliOzet)) {
                    $data[$alan] = $hesapliOzet[$alan];
                }
            }
        }

        if (! array_key_exists('tevkifat_tutari_gosterim', $data) || $data['tevkifat_tutari_gosterim'] === null || $data['tevkifat_tutari_gosterim'] === '') {
            $data['tevkifat_tutari_gosterim'] = round(((float) ($data['kdv_toplam'] ?? 0)) * ((float) ($data['tevkifat_orani'] ?? 0)) / 100, 8);
        }

        return $data;
    }

    protected function getFormActions(): array
    {
        if ($this->goruntule) {
            return [];
        }

        if ($this->record->durum === FaturaDurumu::Onayli) {
            return [];
        }

        if (FaturaKaynagi::kalemDetaylariGoster()) {
            return parent::getFormActions();
        }

        return [
            Actions\Action::make('save')
                ->label('Kaydet')
                ->action('save')
                ->color('primary'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->durum === FaturaDurumu::Onayli) {
            throw ValidationException::withMessages([
                'data' => 'Onaylı fatura düzenlenemez. Değişiklik için önce iptal veya iade akışını kullanın.',
            ]);
        }

        // Onaylı duruma yalnızca cari/stok/numara işlemleri tamamlandıktan
        // sonra geçilebilir. Kullanıcı formdan Onaylı seçerse, önce mevcut
        // taslak/beklemede durumunu koruyup afterSave içinde onay servisini
        // çalıştıracağız.
        $durumVerisi = $data['durum'] ?? null;
        $istenenDurum = $durumVerisi instanceof FaturaDurumu
            ? $durumVerisi
            : FaturaDurumu::tryFrom((string) $durumVerisi);
        if ($istenenDurum === FaturaDurumu::Onayli) {
            $this->onayIsteniyor = true;
            $data['durum'] = $this->record->durum instanceof FaturaDurumu
                ? $this->record->durum->value
                : (string) $this->record->durum;
        }

        if (! FaturaKaynagi::kalemDetaylariGoster()) {
            return $data;
        }

        return FaturaKaynagi::hesaplaFormKalemleriVeOzet($data);
    }

    protected function afterSave(): void
    {
        if ($this->onayIsteniyor) {
            try {
                foreach ($this->record->load('kalemler')->kalemler as $kalem) {
                    app(FaturaOlcuKalemiServisi::class)->tekOlcuDagiliminiOtomatikTamamla(
                        $kalem,
                        in_array($this->record->tur->kanonik(), [
                            \App\Muhasebe\Enumlar\FaturaTuru::Giden,
                            \App\Muhasebe\Enumlar\FaturaTuru::AlisIadesi,
                        ], true),
                    );
                }
                app(FaturaIslemServisi::class)->faturayiOnayla($this->record->fresh(['kalemler']));
                $this->record->refresh();

                Notification::make()
                    ->success()
                    ->title('Fatura onaylandı')
                    ->body('Cari, stok ve numara işlemleri tamamlandı; fatura onaylı duruma getirildi.')
                    ->persistent()
                    ->send();
            } catch (Throwable $e) {
                report($e);
                Notification::make()
                    ->danger()
                    ->title('Fatura onaylanamadı')
                    ->body($e->getMessage().' Fatura taslak olarak bırakıldı; eksikleri düzelttikten sonra tekrar deneyebilirsiniz.')
                    ->persistent()
                    ->send();
            }

            return;
        }

        $durum = $this->record->durum instanceof FaturaDurumu
            ? $this->record->durum->value
            : (string) $this->record->durum;

        Notification::make()
            ->success()
            ->title('Fatura kaydı tamamlandı')
            ->body('Fatura bilgileri ve hesaplanan tutarlar başarıyla kaydedildi. Durum: '.($durum !== '' ? $durum : 'belirsiz').'.')
            ->send();
    }
}
