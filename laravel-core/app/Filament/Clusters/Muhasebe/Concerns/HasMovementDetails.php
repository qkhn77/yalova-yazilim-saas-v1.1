<?php

namespace App\Filament\Clusters\Muhasebe\Concerns;

use App\Muhasebe\Enumlar\FinansHareketDurumu;
use App\Muhasebe\Enumlar\HareketDurumu;
use App\Muhasebe\Servisler\FinansHareketServisi;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Tables;

trait HasMovementDetails
{
    protected function movementDetailColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('hareket_yonu')
                ->label('Yön')
                ->badge()
                ->state(fn ($record): string => $this->movementDirection($record))
                ->color(fn ($state): string => $state === 'Giriş' ? 'success' : ($state === 'Çıkış' ? 'warning' : 'gray')),
            Tables\Columns\TextColumn::make('kaynak_hedef')
                ->label('Kaynak / hedef')
                ->state(fn ($record): string => $this->movementSourceTarget($record))
                ->limit(35)
                ->toggleable(),
        ];
    }

    protected function movementDetailActions(): array
    {
        return [
            Tables\Actions\ViewAction::make('hareket_detayi')
                ->label('Detay')
                ->icon('heroicon-o-eye')
                ->modalHeading('Finans hareketi detayı')
                ->infolist([
                    TextEntry::make('finansHareketi.tarih')->label('Tarih')->dateTime('d.m.Y H:i'),
                    TextEntry::make('finansHareketi.tur')->label('Tür')->formatStateUsing(fn ($state): string => $this->movementTypeLabel($state)),
                    TextEntry::make('hareket_yonu')->label('Yön')->state(fn ($record): string => $this->movementDirection($record)),
                    TextEntry::make('kaynak_hedef')->label('Kaynak / hedef')->state(fn ($record): string => $this->movementSourceTarget($record)),
                    TextEntry::make('tutar')->label('Tutar')->formatStateUsing(fn ($state, $record): string => number_format((float) ($state ?? 0), 2, ',', '.').' '.strtoupper((string) ($record->para_birimi ?: 'TRY'))),
                    TextEntry::make('finansHareketi.cari.ad')->label('Cari')->placeholder('—'),
                    TextEntry::make('finansHareketi.referans_turu')->label('Kaynak modül')->placeholder('—'),
                    TextEntry::make('finansHareketi.referans_id')->label('Kaynak kayıt no')->placeholder('—'),
                    TextEntry::make('finansHareketi.aciklama')->label('Açıklama')->placeholder('—')->columnSpanFull(),
                ]),
            Tables\Actions\Action::make('iptal')
                ->label('İptal et')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Finans hareketi iptal edilsin mi?')
                ->modalDescription('Fiziksel silme yapılmaz; ters kayıt oluşturularak hareket iptal edilir.')
                ->modalSubmitActionLabel('İptal et')
                ->visible(function ($record): bool {
                    $hareketDurumu = $record->durum instanceof HareketDurumu
                        ? $record->durum
                        : HareketDurumu::tryFrom((string) $record->durum);
                    $finansDurumu = $record->finansHareketi?->durum instanceof FinansHareketDurumu
                        ? $record->finansHareketi->durum
                        : FinansHareketDurumu::tryFrom((string) ($record->finansHareketi?->durum ?? ''));
                    $referans = strtolower(trim((string) ($record->finansHareketi?->referans_turu ?? '')));

                    return $hareketDurumu === HareketDurumu::Aktif
                        && $finansDurumu === FinansHareketDurumu::Aktif
                        && ! in_array($referans, ['teknik_servis', 'restoran_adisyon'], true);
                })
                ->action(function ($record): void {
                    try {
                        app(FinansHareketServisi::class)->tersKayitOlustur(
                            $record->finansHareketi,
                            'Hesap hareketlerinden iptal',
                        );

                        Notification::make()
                            ->title('Finans hareketi iptal edildi')
                            ->body('Ters kayıt oluşturuldu; finansal geçmiş korundu.')
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Hareket iptal edilemedi')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function movementDirection($record): string
    {
        $amount = (float) ($record->tutar ?? 0);

        return $amount > 0 ? 'Giriş' : ($amount < 0 ? 'Çıkış' : 'Dengeli');
    }

    protected function movementSourceTarget($record): string
    {
        $finance = $record->finansHareketi;
        if (! $finance) {
            return '—';
        }

        $cari = $finance->cari?->ad;
        if ($cari) {
            return $this->movementDirection($record) === 'Giriş' ? $cari.' → Hesap' : 'Hesap → '.$cari;
        }

        return match ($finance->tur?->value ?? (string) $finance->tur) {
            'virman' => 'Hesaplar arası virman',
            'mahsub' => 'Mahsup kaydı',
            'mahsup' => 'Mahsup kaydı',
            default => 'Finans hareketi #'.$finance->getKey(),
        };
    }

    protected function movementTypeLabel($state): string
    {
        $value = $state?->value ?? (string) $state;

        return match ($value) {
            'tahsilat' => 'Tahsilat',
            'odeme' => 'Ödeme',
            'virman' => 'Virman',
            'mahsup' => 'Mahsup',
            default => ucfirst($value ?: '—'),
        };
    }
}
