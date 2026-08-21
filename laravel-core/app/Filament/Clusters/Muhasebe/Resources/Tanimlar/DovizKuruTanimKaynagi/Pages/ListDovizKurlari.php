<?php

namespace App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DovizKuruTanimKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\Tanimlar\DovizKuruTanimKaynagi;
use App\Muhasebe\Servisler\DovizKurServisi;
use App\Services\TenantContextService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListDovizKurlari extends ListRecords
{
    protected static string $resource = DovizKuruTanimKaynagi::class;

    protected static ?string $title = 'Doviz kurlari';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('otomatikGuncelle')
                ->label('Bugunun kurlarini cek')
                ->icon('heroicon-o-arrow-path')
                ->color('info')
                ->action(function (): void {
                    $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                    if ($firmaId < 1) {
                        Notification::make()->title('Aktif firma yok')->danger()->send();

                        return;
                    }

                    $sonuc = app(DovizKurServisi::class)->firmaIcinBazParitelereOtomatikKurYukle($firmaId, now()->toDateString());
                    Notification::make()
                        ->title('Kur guncelleme tamamlandi')
                        ->body('Basarili: '.$sonuc['ok'].' | Hatali: '.$sonuc['hata'])
                        ->success()
                        ->send();
                }),
            Actions\Action::make('tarihAraligiCek')
                ->label('Tarih araliginda cek')
                ->icon('heroicon-o-calendar-days')
                ->color('gray')
                ->form([
                    Forms\Components\DatePicker::make('baslangic')
                        ->label('Baslangic')
                        ->default(now()->subDays(7)->toDateString())
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('bitis')
                        ->label('Bitis')
                        ->default(now()->toDateString())
                        ->required()
                        ->native(false),
                    Forms\Components\Toggle::make('manuel_ez')
                        ->label('Manuel kayitlari ez')
                        ->default(false),
                ])
                ->action(function (array $data): void {
                    $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                    if ($firmaId < 1) {
                        Notification::make()->title('Aktif firma yok')->danger()->send();

                        return;
                    }

                    $rapor = app(DovizKurServisi::class)->firmaIcinTarihAraligindaBazParitelereOtomatikKurYukle(
                        $firmaId,
                        (string) $data['baslangic'],
                        (string) $data['bitis'],
                        (bool) ($data['manuel_ez'] ?? false)
                    );
                    Notification::make()
                        ->title('Toplu cekim tamamlandi')
                        ->body('Gun: '.$rapor['gun'].' | Basarili: '.$rapor['ok'].' | Hatali: '.$rapor['hata'])
                        ->success()
                        ->send();
                }),
            Actions\Action::make('eksikRapor')
                ->label('Eksik kur raporu')
                ->icon('heroicon-o-document-magnifying-glass')
                ->color('warning')
                ->form([
                    Forms\Components\DatePicker::make('baslangic')
                        ->label('Baslangic')
                        ->default(now()->subDays(7)->toDateString())
                        ->required()
                        ->native(false),
                    Forms\Components\DatePicker::make('bitis')
                        ->label('Bitis')
                        ->default(now()->toDateString())
                        ->required()
                        ->native(false),
                ])
                ->action(function (array $data): void {
                    $firmaId = (int) (app(TenantContextService::class)->aktifFirmaId() ?? 0);
                    if ($firmaId < 1) {
                        Notification::make()->title('Aktif firma yok')->danger()->send();

                        return;
                    }

                    $rapor = app(DovizKurServisi::class)->eksikKurRaporu(
                        $firmaId,
                        (string) $data['baslangic'],
                        (string) $data['bitis'],
                        20
                    );

                    $satirOzet = '';
                    if ($rapor['satirlar'] !== []) {
                        $satirOzet = "\nIlk eksikler:\n";
                        foreach ($rapor['satirlar'] as $satir) {
                            $satirOzet .= $satir['tarih'].' '.$satir['kaynak'].'->'.$satir['hedef']."\n";
                        }
                        if ($rapor['eksik'] > count($rapor['satirlar'])) {
                            $satirOzet .= '... daha fazla eksik var.';
                        }
                    }

                    Notification::make()
                        ->title('Eksik kur raporu')
                        ->body('Beklenen: '.$rapor['beklenen'].' | Mevcut: '.$rapor['mevcut'].' | Eksik: '.$rapor['eksik'].$satirOzet)
                        ->color($rapor['eksik'] > 0 ? 'warning' : 'success')
                        ->send();
                }),
            Actions\CreateAction::make()->label('Kur ekle'),
        ];
    }
}
