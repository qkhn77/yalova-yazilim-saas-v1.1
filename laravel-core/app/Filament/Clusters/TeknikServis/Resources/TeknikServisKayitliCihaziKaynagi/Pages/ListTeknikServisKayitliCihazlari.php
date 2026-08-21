<?php

namespace App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi\Pages;

use App\Filament\Clusters\TeknikServis\Resources\TeknikServisKayitliCihaziKaynagi;
use App\Models\TeknikServis\TeknikServisKayitliCihazi;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListTeknikServisKayitliCihazlari extends ListRecords
{
    protected static string $resource = TeknikServisKayitliCihaziKaynagi::class;
    // Sidebar kaydı, teknik servis altında tekil navigasyon sayfasından yapılır.
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Kayıtlı cihazlar';
    protected static ?string $navigationLabel = 'Kayıtlı cihazlar';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('csvIndir')
                ->label('CSV indir')->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->csvIndir()),
        ];
    }

    public function csvIndir(): StreamedResponse
    {
        $cihazlar = TeknikServisKayitliCihazi::query()
            ->with(['cari:id,ad', 'cihaz:id,ad', 'marka:id,ad'])
            ->withCount('servisKayitlari')
            ->orderBy('id')
            ->limit(5000)
            ->get();

        return response()->streamDownload(function () use ($cihazlar): void {
            $out = fopen('php://output', 'wb');
            if (! is_resource($out)) return;
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Cihaz no', 'Cari', 'Cihaz', 'Marka', 'Model', 'Seri no', 'Servis sayısı', 'Garanti başlangıcı', 'Garanti bitişi', 'Aktif'], ';');
            foreach ($cihazlar as $cihaz) {
                fputcsv($out, [
                    $cihaz->cihaz_no, $cihaz->cari?->ad, $cihaz->cihaz?->ad, $cihaz->marka?->ad,
                    $cihaz->model_no, $cihaz->seri_no, $cihaz->servis_kayitlari_count, $cihaz->garanti_baslangic_tarihi?->format('d.m.Y'),
                    $cihaz->garanti_bitis_tarihi?->format('d.m.Y'), $cihaz->aktif_mi ? 'Aktif' : 'Pasif',
                ], ';');
            }
            fclose($out);
        }, 'kayitli-cihazlar-'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
