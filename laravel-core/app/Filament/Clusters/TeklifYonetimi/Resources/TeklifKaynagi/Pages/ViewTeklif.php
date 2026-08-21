<?php

namespace App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi\Pages;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi;
use App\Models\Muhasebe\Teklif;
use App\Models\Muhasebe\Fatura;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use App\TeklifYonetimi\Servisler\TeklifIsAkisiServisi;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;

class ViewTeklif extends ViewRecord
{
    protected static string $resource = TeklifKaynagi::class;

    protected static string $view = 'filament.clusters.teklif-yonetimi.resources.teklif-kaynagi.pages.view-teklif';

    protected static ?string $title = 'Teklif Ön İzleme';

    private ?TeklifBaskiSablonu $aktifSablonCache = null;

    private ?string $onizlemeHtmlCache = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if (! $this->detayModu()) {
            return;
        }

        $this->record->loadMissing([
            'firma',
            'cari',
            'baskiSablonu',
            'kalemler',
            'kalemler.stokKarti',
            'faturayaDonusenFatura',
        ]);

        if (! $this->record->baskiSablonu) {
            app(TeklifBaskiSablonuServisi::class)->hazirVarsayilanSablonId((int) $this->record->firma_id);
        }
    }

    protected function fillForm(): void
    {
        if ($this->detayModu()) {
            parent::fillForm();
        }
    }

    public function getTitle(): string|Htmlable
    {
        /** @var Teklif $record */
        $record = $this->record;

        return (string) ($record->teklif_no ?: 'Teklif #'.$record->getKey());
    }

    protected function getHeaderActions(): array
    {
        $detayModu = $this->detayModu();

        return [
            Actions\Action::make($detayModu ? 'hizli_gorunum' : 'detaylar')
                ->label($detayModu ? 'Hızlı Görünüm' : 'Ön İzleme')
                ->icon($detayModu ? 'heroicon-o-bolt' : 'heroicon-o-document-magnifying-glass')
                ->color('gray')
                ->extraAttributes(['onclick' => 'window.location.href = this.href; return false;'])
                ->url(fn (): string => $this->gorunumUrlOlustur($detayModu)),
            ...($detayModu ? [
            Actions\Action::make('yazdir')
                ->label('Yazdır')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->extraAttributes(['onclick' => 'window.print()']),
            Actions\Action::make('pdf_indir')
                ->label('PDF indir')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->url(fn (): string => $this->pdfUrlOlustur()),
            Actions\ActionGroup::make([
                Actions\Action::make('durum_gonderildi')
                    ->label('Müşteriye gönderildi')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (): bool => (string) $this->record->durum !== 'gonderildi')
                    ->action(fn (): null => $this->durumDegistir('gonderildi')),
                Actions\Action::make('durum_onaylandi')
                    ->label('Onaylandı')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (): bool => (string) $this->record->durum !== 'onaylandi')
                    ->action(fn (): null => $this->durumDegistir('onaylandi')),
                Actions\Action::make('durum_revizyon')
                    ->label('Revizyon bekliyor')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('warning')
                    ->visible(fn (): bool => (string) $this->record->durum !== 'revizyon_bekliyor')
                    ->action(fn (): null => $this->durumDegistir('revizyon_bekliyor')),
                Actions\Action::make('durum_reddedildi')
                    ->label('Reddedildi')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => (string) $this->record->durum !== 'reddedildi')
                    ->action(fn (): null => $this->durumDegistir('reddedildi')),
                Actions\Action::make('durum_suresi_doldu')
                    ->label('Süresi doldu')
                    ->icon('heroicon-o-clock')
                    ->color('gray')
                    ->visible(fn (): bool => (string) $this->record->durum !== 'suresi_doldu')
                    ->action(fn (): null => $this->durumDegistir('suresi_doldu')),
            ])
                ->label('Durum')
                ->icon('heroicon-o-flag'),
            Actions\Action::make('faturaya_donustur')
                ->label('Faturaya dönüştür')
                ->icon('heroicon-o-document-plus')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => (string) $this->record->durum === 'onaylandi' && Gate::allows('create', Fatura::class))
                ->action(fn (): null => $this->faturayaDonustur()),
            ] : []),
            Actions\EditAction::make()->label('Düzenle'),
            Actions\Action::make('liste')
                ->label('Listeye dön')
                ->color('gray')
                ->url(TeklifKaynagi::getUrl('index')),
        ];
    }

    public function onizlemeHtml(): Htmlable
    {
        /** @var Teklif $record */
        $record = $this->record;

        $aktifSablon = $this->aktifOnizlemeSablonu();

        if ($aktifSablon) {
            $record->setRelation('baskiSablonu', $aktifSablon);
        }

        $this->onizlemeHtmlCache ??= app(TeklifBaskiSablonuServisi::class)->teklifHtmlOlustur($record);

        return new HtmlString($this->onizlemeHtmlCache);
    }

    public function detayModu(): bool
    {
        return ! request()->boolean('hizli');
    }

    /**
     * @return array<string, string>
     */
    public function hizliOzetSatirlari(): array
    {
        /** @var Teklif $record */
        $record = $this->record;

        return [
            'Teklif no' => (string) ($record->teklif_no ?: 'Teklif #'.$record->getKey()),
            'Başlık' => (string) ($record->baslik ?: '-'),
            'Tarih' => $record->tarih ? $record->tarih->format('d.m.Y') : '-',
            'Durum' => (string) (Teklif::DURUMLAR[(string) $record->durum] ?? $record->durum ?? '-'),
            'Genel toplam' => TeklifKaynagi::formatMoney($record->genel_toplam, (string) ($record->para_birimi ?: 'TRY')),
        ];
    }

    public function onizlemeCss(): string
    {
        $sablon = $this->aktifOnizlemeSablonu();

        return (string) ($sablon->sablon_css ?? '');
    }

    public function onizlemeKapsayiciStili(): string
    {
        $sablon = $this->aktifOnizlemeSablonu();

        return app(TeklifBaskiSablonuServisi::class)->kapsayiciStili((string) ($sablon->sayfa_tipi ?? 'a4'));
    }

    public function onizlemeSagBosluk(): string
    {
        /** @var Teklif $record */
        $record = $this->record;
        $sablon = $this->aktifOnizlemeSablonu();

        return app(TeklifBaskiSablonuServisi::class)->sayfaSagBosluk((string) ($sablon->sayfa_tipi ?? 'a4'));
    }

    protected function aktifOnizlemeSablonu()
    {
        /** @var Teklif $record */
        $record = $this->record;

        $istekSablonId = (int) request()->integer('preview_template', 0);

        if ($this->aktifSablonCache instanceof TeklifBaskiSablonu) {
            return $this->aktifSablonCache;
        }

        if ($istekSablonId > 0) {
            $istekSablonu = TeklifBaskiSablonu::query()
                ->where('firma_id', (int) $record->firma_id)
                ->where('aktif', true)
                ->find($istekSablonId);

            if ($istekSablonu) {
                return $this->aktifSablonCache = $istekSablonu;
            }
        }

        return $this->aktifSablonCache = $record->baskiSablonu
            ?: app(TeklifBaskiSablonuServisi::class)->varsayilanSablon((int) $record->firma_id);
    }

    private function gorunumUrlOlustur(bool $hizli): string
    {
        /** @var Teklif $record */
        $record = $this->record;

        $url = TeklifKaynagi::getUrl('view', [
            'record' => $record,
        ]);
        $query = [];

        $istekSablonId = (int) request()->integer('preview_template', 0);
        if ($istekSablonId > 0 && ! $hizli) {
            $query['preview_template'] = $istekSablonId;
        }

        if ($hizli) {
            $query['hizli'] = 1;
        }

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    private function pdfUrlOlustur(): string
    {
        /** @var Teklif $record */
        $record = $this->record;

        $parametreler = [
            'teklif' => $record->getKey(),
            'v' => now()->timestamp,
        ];

        $istekSablonId = (int) request()->integer('preview_template', 0);
        if ($istekSablonId > 0) {
            $parametreler['preview_template'] = $istekSablonId;
        }

        return route('admin.teklif-yonetimi.teklifler.pdf', $parametreler);
    }

    private function durumDegistir(string $durum): null
    {
        $this->record = app(TeklifIsAkisiServisi::class)->durumDegistir($this->record, $durum);
        $this->onizlemeHtmlCache = null;

        Notification::make()
            ->title('Teklif durumu güncellendi')
            ->body('Yeni durum: '.(Teklif::DURUMLAR[$durum] ?? $durum))
            ->success()
            ->send();

        return null;
    }

    private function faturayaDonustur(): null
    {
        $fatura = app(TeklifIsAkisiServisi::class)->bekleyenFaturaOlustur($this->record);
        $this->record->refresh();

        Notification::make()
            ->title('Bekleyen fatura oluşturuldu')
            ->success()
            ->send();

        $this->redirect(FaturaKaynagi::getUrl('view', ['record' => $fatura]));

        return null;
    }
}
