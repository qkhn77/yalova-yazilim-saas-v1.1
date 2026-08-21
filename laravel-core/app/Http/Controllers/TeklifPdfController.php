<?php

namespace App\Http\Controllers;

use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifKaynagi;
use App\Filament\Clusters\TeklifYonetimi\Resources\TeklifSablonKaynagi;
use App\Models\Muhasebe\Teklif;
use App\Models\TeklifYonetimi\TeklifBaskiSablonu;
use App\TeklifYonetimi\Servisler\TeklifBaskiSablonuServisi;
use App\TeklifYonetimi\Servisler\TeklifPdfServisi;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TeklifPdfController extends Controller
{
    public function teklifIndir(
        Request $request,
        int $teklif,
        TeklifBaskiSablonuServisi $sablonServisi,
        TeklifPdfServisi $pdfServisi,
    ): BinaryFileResponse {
        /** @var Teklif $kayit */
        $kayit = Teklif::query()
            ->with([
                'firma',
                'cari',
                'baskiSablonu',
                'kalemler',
                'kalemler.stokKarti',
                'faturayaDonusenFatura',
            ])
            ->findOrFail($teklif);

        abort_unless(TeklifKaynagi::canView($kayit), 403);

        $kayit->toplamlariniKalemlerdenGuncelle();

        $sablon = $this->teklifSablonu($request, $kayit, $sablonServisi);
        if ($sablon) {
            $kayit->setRelation('baskiSablonu', $sablon);
        }

        $html = $sablonServisi->teklifHtmlOlustur($kayit);
        $css = (string) ($sablon?->sablon_css ?? $kayit->baskiSablonu?->sablon_css ?? '');
        $pdfYolu = $pdfServisi->a4PdfOlustur($html, $css);

        return $this->teklifPdfYaniti($pdfYolu, $this->teklifDosyaAdi($kayit));
    }

    private function teklifPdfYaniti(string $pdfYolu, string $dosyaAdi): BinaryFileResponse
    {
        $response = response()
            ->download($pdfYolu, $dosyaAdi, array_merge(['Content-Type' => 'application/pdf'], $this->onbellekKapatmaBasliklari()))
            ->deleteFileAfterSend(true);

        return $response;
    }

    public function sablonIndir(
        int $sablon,
        TeklifBaskiSablonuServisi $sablonServisi,
        TeklifPdfServisi $pdfServisi,
    ): BinaryFileResponse {
        /** @var TeklifBaskiSablonu $kayit */
        $kayit = TeklifBaskiSablonu::query()
            ->findOrFail($sablon);

        abort_unless(TeklifSablonKaynagi::canView($kayit), 403);

        $html = $sablonServisi->onizlemeHtmlOlustur($kayit->toArray(), (int) $kayit->firma_id);
        $pdfYolu = $pdfServisi->a4PdfOlustur($html, (string) ($kayit->sablon_css ?? ''));

        return $this->teklifPdfYaniti($pdfYolu, $this->sablonDosyaAdi($kayit));
    }

    public function sablonOnizlemeFrame(
        int $sablon,
        TeklifBaskiSablonuServisi $sablonServisi,
    ) {
        /** @var TeklifBaskiSablonu $kayit */
        $kayit = TeklifBaskiSablonu::query()
            ->select([
                'id',
                'firma_id',
                'ad',
                'kod',
                'sayfa_tipi',
                'sablon_logo',
                'sablon_html',
                'sablon_css',
                'varsayilan_mi',
                'aktif',
                'updated_at',
            ])
            ->findOrFail($sablon);

        abort_unless(TeklifSablonKaynagi::canView($kayit), 403);

        $html = Cache::remember(
            'teklif_sablon_onizleme_html|'.((int) $kayit->getKey()).'|'.((string) $kayit->updated_at),
            now()->addMinutes(10),
            fn (): string => $sablonServisi->onizlemeHtmlOlustur($kayit->toArray(), (int) $kayit->firma_id)
        );

        $css = (string) ($kayit->sablon_css ?? '');
        $kapsayiciStili = $sablonServisi->kapsayiciStili((string) ($kayit->sayfa_tipi ?? 'a4'));

        $dokuman = '<!doctype html><html lang="tr"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<style>html,body{margin:0;padding:0;background:#f8fafc;color:#000;font-family:DejaVu Sans,Segoe UI,Arial,sans-serif;}body{padding:16px;}.teklif-sablon-onizleme{width:100%;overflow-x:hidden!important}.teklif-sablon-onizleme .teklif-wrap,.teklif-sablon-onizleme .teklif-mini,.teklif-sablon-onizleme .eas-sheet,.teklif-sablon-onizleme .pdfpage{box-sizing:border-box!important;max-width:100%!important;margin:0 auto!important;min-width:0!important;overflow:hidden!important;word-break:break-word;overflow-wrap:anywhere}.teklif-sablon-onizleme img,.teklif-sablon-onizleme table,.teklif-sablon-onizleme svg{max-width:100%!important}.teklif-sablon-onizleme .pdfpage{background:#fff!important;width:100%!important;height:auto!important;aspect-ratio:793/1123;min-height:auto!important;position:relative!important}.teklif-sablon-onizleme .pdfpage>svg{position:absolute!important;inset:0!important;width:100%!important;height:100%!important}@media print{@page{size:A4 portrait;margin:0}html,body{background:#fff!important;padding:0!important}.teklif-sablon-onizleme .yb-offer-page{width:210mm!important;max-width:210mm!important;min-height:297mm!important;margin:0 auto!important;padding:0!important;overflow:visible!important;-webkit-print-color-adjust:exact!important;print-color-adjust:exact!important}}'
            .$css
            .'</style></head><body><div class="teklif-sablon-onizleme"><div style="'.e($kapsayiciStili).'">'.$html.'</div></div></body></html>';

        return response($dokuman, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function teklifSablonu(Request $request, Teklif $teklif, TeklifBaskiSablonuServisi $sablonServisi): ?TeklifBaskiSablonu
    {
        $istekSablonId = (int) $request->integer('preview_template', 0);

        if ($istekSablonId > 0) {
            $istekSablonu = TeklifBaskiSablonu::query()
                ->where('firma_id', (int) $teklif->firma_id)
                ->where('aktif', true)
                ->find($istekSablonId);

            if ($istekSablonu) {
                return $istekSablonu;
            }
        }

        return $teklif->baskiSablonu
            ?: $sablonServisi->varsayilanSablon((int) $teklif->firma_id);
    }

    private function teklifDosyaAdi(Teklif $teklif): string
    {
        $ad = (string) ($teklif->teklif_no ?: 'teklif-'.$teklif->getKey());

        return (Str::slug($ad) ?: 'teklif').'.pdf';
    }

    private function sablonDosyaAdi(TeklifBaskiSablonu $sablon): string
    {
        $ad = (string) ($sablon->ad ?: 'teklif-sablonu-'.$sablon->getKey());

        return (Str::slug($ad) ?: 'teklif-sablonu').'.pdf';
    }

    /**
     * @return array<string, string>
     */
    private function onbellekKapatmaBasliklari(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
