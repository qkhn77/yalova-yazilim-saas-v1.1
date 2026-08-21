<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi\Pages;

use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListTeklifSablonlari extends ListRecords
{
    protected static string $resource = TeklifSablonKaynagi::class;

    protected static ?string $title = 'Teklif Şablonları';

    public function mount(): void
    {
        parent::mount();

        $firmaId = TeklifSablonKaynagi::aktifFirmaId();
        $sablonVar = TeklifBaskiSablonu::query()
            ->where('firma_id', $firmaId)
            ->where('aktif', true)
            ->exists();

        if (! $sablonVar) {
            app(TeklifBaskiSablonuServisi::class)->firmaSablonlariniHazirla($firmaId);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('yeni_sablon')
                ->label('Yeni şablon')
                ->icon('heroicon-o-plus')
                ->url(TeklifSablonKaynagi::getUrl('create')),
            Actions\Action::make('demo_sablonlari')
                ->label('Demo Şablonları Geri Yükle')
                ->color('warning')
                ->icon('heroicon-o-arrow-path')
                ->action(function (): void {
                    $firmaId = TeklifSablonKaynagi::aktifFirmaId();
                    $servis = app(TeklifBaskiSablonuServisi::class);
                    $izinVerilenKodlar = [
                        'yalova-bilgisayar-teklif-formu-a4',
                        'eas-teklif-a4',
                    ];
                    $demoKodlari = collect($servis->hazirSablonlar())
                        ->pluck('kod')
                        ->all();
                    $eklenen = 0;
                    $guncellenen = 0;

                    TeklifBaskiSablonu::query()
                        ->where('firma_id', $firmaId)
                        ->whereIn('kod', $demoKodlari)
                        ->whereNotIn('kod', $izinVerilenKodlar)
                        ->delete();

                    foreach (collect($servis->hazirSablonlar())
                        ->whereIn('kod', $izinVerilenKodlar)
                        ->all() as $hazir) {
                        $mevcutSorgu = TeklifBaskiSablonu::query()
                            ->where('firma_id', $firmaId)
                            ->where('kod', (string) $hazir['kod']);

                        if ((string) $hazir['kod'] === 'yalova-bilgisayar-teklif-formu-a4') {
                            $mevcutSorgu->orWhere(function ($query) use ($firmaId): void {
                                $query
                                    ->where('firma_id', $firmaId)
                                    ->where('kod', 'like', 'yalova-bilgisayar-teklif-a4%');
                            });
                        }

                        $mevcut = $mevcutSorgu->first();

                        if ($mevcut) {
                            $mevcut->forceFill([
                                'ad' => (string) $hazir['ad'],
                                'sayfa_tipi' => (string) $hazir['sayfa_tipi'],
                                'sablon_html' => (string) $hazir['sablon_html'],
                                'sablon_css' => (string) $hazir['sablon_css'],
                                'aktif' => true,
                            ])->save();
                            $guncellenen++;
                        } else {
                            TeklifBaskiSablonu::query()->create([
                                'firma_id' => $firmaId,
                                'ad' => (string) $hazir['ad'],
                                'kod' => (string) $hazir['kod'],
                                'sayfa_tipi' => (string) $hazir['sayfa_tipi'],
                                'sablon_logo' => null,
                                'sablon_html' => (string) $hazir['sablon_html'],
                                'sablon_css' => (string) $hazir['sablon_css'],
                                'aktif' => true,
                                'varsayilan_mi' => false,
                            ]);
                            $eklenen++;
                        }
                    }

                    $varsayilan = TeklifBaskiSablonu::query()
                        ->where('firma_id', $firmaId)
                        ->where('varsayilan_mi', true)
                        ->exists();

                    if (! $varsayilan) {
                        $ilk = TeklifBaskiSablonu::query()
                            ->where('firma_id', $firmaId)
                            ->orderBy('id')
                            ->first();

                        if ($ilk) {
                            $servis->varsayilanYap($ilk);
                        }
                    }

                    Notification::make()
                        ->title('Demo şablonlar geri yüklendi')
                        ->body('Eklenen: '.$eklenen.' | Güncellenen: '.$guncellenen)
                        ->success()
                        ->send();
                }),
        ];
    }
}
