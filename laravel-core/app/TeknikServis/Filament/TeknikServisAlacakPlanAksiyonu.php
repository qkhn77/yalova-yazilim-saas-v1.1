<?php

namespace App\TeknikServis\Filament;

use App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi;
use App\Models\Muhasebe\AlacakPlani;
use App\Models\TeknikServis\TeknikServisKaydi;
use App\Muhasebe\Servisler\AlacakPlanServisi;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Throwable;

final class TeknikServisAlacakPlanAksiyonu
{
    /**
     * @param callable(): ?TeknikServisKaydi $servisGetir
     */
    public static function olustur(callable $servisGetir): Action
    {
        return Action::make('odemePlaniOlustur')
            ->label('Odeme Plani')
            ->icon('heroicon-o-calendar-days')
            ->color('warning')
            ->visible(fn (): bool => self::gorunurMu($servisGetir()))
            ->form(fn (): array => self::form($servisGetir()))
            ->fillForm(fn (): array => self::varsayilanlar($servisGetir()))
            ->action(function (array $data) use ($servisGetir): void {
                $servis = $servisGetir();
                if (! $servis) {
                    return;
                }

                try {
                    $plan = app(AlacakPlanServisi::class)->teknikServisIcinOlustur($servis->fresh(['cari', 'tahsilatlar']) ?? $servis, $data);

                    Notification::make()
                        ->title('Odeme plani olusturuldu')
                        ->body('Plan #'.(int) $plan->getKey().' Finans > Vade Takibi ekranina eklendi.')
                        ->actions([
                            NotificationAction::make('vade_takibi')
                                ->label('Vade Takibine Git')
                                ->url(VadeTakipSayfasi::getUrl()),
                        ])
                        ->success()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Odeme plani olusturulamadi')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }

    /**
     * @return array<int, Forms\Components\Component>
     */
    private static function form(?TeknikServisKaydi $servis): array
    {
        $paraBirimi = strtoupper((string) ($servis?->cari?->para_birimi ?: 'TRY'));

        return [
            Forms\Components\Select::make('plan_turu')
                ->label('Plan turu')
                ->options([
                    'veresiye' => 'Veresiye',
                    'taksit' => 'Taksitli',
                ])
                ->default('veresiye')
                ->live()
                ->required(),
            Forms\Components\TextInput::make('toplam_tutar')
                ->label('Toplam tutar')
                ->numeric()
                ->disabled()
                ->dehydrated()
                ->required(),
            Forms\Components\TextInput::make('pesinat_tutari')
                ->label('Pesinat / mevcut tahsilat')
                ->numeric()
                ->minValue(0)
                ->step('0.01')
                ->required(),
            Forms\Components\Hidden::make('para_birimi')
                ->default($paraBirimi)
                ->dehydrated(),
            Forms\Components\Placeholder::make('para_birimi_gosterim')
                ->label('Para birimi')
                ->content($paraBirimi),
            Forms\Components\DatePicker::make('ilk_vade_tarihi')
                ->label('Ilk vade tarihi')
                ->native(false)
                ->required(),
            Forms\Components\TextInput::make('taksit_sayisi')
                ->label('Taksit sayisi')
                ->numeric()
                ->minValue(2)
                ->default(2)
                ->visible(fn (Forms\Get $get): bool => (string) $get('plan_turu') === 'taksit')
                ->required(fn (Forms\Get $get): bool => (string) $get('plan_turu') === 'taksit'),
            Forms\Components\TextInput::make('taksit_araligi_gun')
                ->label('Taksit araligi (gun)')
                ->numeric()
                ->minValue(1)
                ->default(30)
                ->visible(fn (Forms\Get $get): bool => (string) $get('plan_turu') === 'taksit')
                ->required(fn (Forms\Get $get): bool => (string) $get('plan_turu') === 'taksit'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function varsayilanlar(?TeknikServisKaydi $servis): array
    {
        $toplam = number_format((float) ($servis?->toplam_tutar ?? 0), 2, '.', '');
        $tahsilat = (float) ($servis?->odenen_tutar ?? 0);
        $pesinat = min((float) $toplam, $tahsilat);

        return [
            'plan_turu' => 'veresiye',
            'toplam_tutar' => $toplam,
            'pesinat_tutari' => number_format($pesinat, 2, '.', ''),
            'para_birimi' => strtoupper((string) ($servis?->cari?->para_birimi ?: 'TRY')),
            'ilk_vade_tarihi' => now()->addDays(30)->toDateString(),
            'taksit_sayisi' => 2,
            'taksit_araligi_gun' => 30,
        ];
    }

    private static function gorunurMu(?TeknikServisKaydi $servis): bool
    {
        if (! $servis || (int) ($servis->cari_id ?? 0) < 1 || (float) ($servis->toplam_tutar ?? 0) <= 0) {
            return false;
        }

        if (self::aktifPlanVarMi($servis)) {
            return false;
        }

        $toplam = (float) ($servis->toplam_tutar ?? 0);
        $odenen = (float) ($servis->odenen_tutar ?? 0);

        return $toplam - $odenen > 0.009;
    }

    private static function aktifPlanVarMi(TeknikServisKaydi $servis): bool
    {
        if (array_key_exists('aktif_alacak_plani_var_mi', $servis->getAttributes())) {
            return (bool) $servis->getAttribute('aktif_alacak_plani_var_mi');
        }

        return AlacakPlani::query()
            ->where('firma_id', (int) $servis->firma_id)
            ->where('kaynak_turu', 'teknik_servis')
            ->where('kaynak_id', (int) $servis->getKey())
            ->whereIn('durum', ['aktif', 'kismi_odendi', 'gecikti'])
            ->exists();
    }

}
