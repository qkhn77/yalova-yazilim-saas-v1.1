<?php

namespace App\Livewire\Muhasebe;

use App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi;
use App\Models\Muhasebe\CariHareketi;
use App\Models\Muhasebe\Fatura;
use App\Models\Muhasebe\FaturaFinansKapama;
use App\Models\Muhasebe\FaturaKalemi;
use App\Models\Muhasebe\StokHareketi;
use App\Muhasebe\Enumlar\FaturaTuru;
use App\Muhasebe\Enumlar\FinansHareketTuru;
use App\Muhasebe\Enumlar\StokHareketIslemTuru;
use App\Muhasebe\Servisler\FaturaFinansKapamaServisi;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class FaturaDetaySekmesi extends Component
{
    public int $faturaId;

    public string $sekme = 'kalemler';

    public function mount(Fatura $record, string $sekme = 'kalemler'): void
    {
        $this->faturaId = (int) $record->getKey();
        $this->sekme = $sekme;
    }

    public function render(): View
    {
        return view('livewire.muhasebe.fatura-detay-sekmesi', [
            'html' => match ($this->sekme) {
                'odemeler' => $this->odemelerHtml(),
                'cari' => $this->cariHareketleriHtml(),
                'stok' => $this->stokHareketleriHtml(),
                'baglantilar' => $this->baglantilarHtml(),
                default => $this->kalemlerHtml(),
            },
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.muhasebe.fatura-detay-sekmesi-placeholder');
    }

    private function temelFatura(): Fatura
    {
        /** @var Fatura $fatura */
        $fatura = Fatura::query()
            ->whereKey($this->faturaId)
            ->firstOrFail([
                'id',
                'firma_id',
                'cari_id',
                'tur',
                'fatura_no',
                'bagli_fatura_id',
                'genel_toplam',
                'odenecek_tutar',
                'odendi_tutari',
                'acik_tutar',
                'para_birimi',
            ]);

        return $fatura;
    }

    private function para(Fatura|FaturaKalemi|FaturaFinansKapama|null $kaynak, Fatura $fatura): string
    {
        if ($kaynak instanceof Fatura) {
            return $kaynak->para_birimi ?: 'TRY';
        }

        if ($kaynak instanceof FaturaKalemi || $kaynak instanceof FaturaFinansKapama) {
            $paraBirimi = $kaynak->para_birimi ?? null;

            return $paraBirimi ? (string) $paraBirimi : ($fatura->para_birimi ?: 'TRY');
        }

        return $fatura->para_birimi ?: 'TRY';
    }

    private function kalemlerHtml(): HtmlString
    {
        $fatura = $this->temelFatura();
        $fatura->load([
            'kalemler' => fn ($query) => $query
                ->select([
                    'id',
                    'firma_id',
                    'fatura_id',
                    'satir_no',
                    'kalem_tipi',
                    'stok_id',
                    'aciklama',
                    'birim',
                    'miktar',
                    'seri_nolari',
                    'birim_fiyat',
                    'kdv_orani',
                    'satir_toplami',
                    'satir_genel_toplam',
                    'para_birimi',
                    'toplam',
                ])
                ->orderBy('satir_no')
                ->orderBy('id'),
            'kalemler.stokKarti:id,kod,ad,birim',
            'kalemler.olcuDagilimlari' => fn ($query) => $query
                ->select([
                    'id',
                    'firma_id',
                    'fatura_kalemi_id',
                    'ana_miktar',
                    'adet_esdegeri',
                    'takip_turu',
                    'olcu_birimi',
                    'en',
                    'boy',
                    'yukseklik',
                ])
                ->orderBy('sira')
                ->orderBy('id'),
        ]);

        $rows = '';
        foreach ($fatura->kalemler as $index => $kalem) {
            /** @var FaturaKalemi $kalem */
            $ad = $kalem->stokKarti?->ad ?? $kalem->aciklama ?? '—';
            $takipBilgisi = $this->kalemTakipBilgisi($kalem);
            $pb = $this->para($kalem, $fatura);
            $siraNo = (int) ($kalem->satir_no ?: $index + 1);
            $brutToplam = (string) ($kalem->satir_toplami ?? '0');
            $netToplam = (string) ($kalem->satir_genel_toplam ?? $kalem->toplam ?? '0');
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm text-center">%s</td>
                    <td class="px-3 py-2 text-sm"><div>%s</div>%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                </tr>',
                e((string) $siraNo),
                e($ad),
                $takipBilgisi,
                e((string) $kalem->miktar),
                e((string) ($kalem->birim ?? '—')),
                e(number_format((float) $kalem->birim_fiyat, 2, ',', '.').' '.$pb),
                e((string) $kalem->kdv_orani),
                e(number_format((float) $brutToplam, 2, ',', '.').' '.$pb),
                e(number_format((float) $netToplam, 2, ',', '.').' '.$pb)
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="8" class="px-3 py-4 text-sm text-gray-500">Kalem yok.</td></tr>';
        }

        $toplamSatir = '0.00';
        foreach ($fatura->kalemler as $kalem) {
            $toplamSatir = bcadd($toplamSatir, (string) ($kalem->satir_genel_toplam ?? $kalem->toplam ?? '0'), 2);
        }

        $genel = (string) ($fatura->genel_toplam ?? '0');
        $uyum = bccomp($toplamSatir, $genel, 2) === 0;
        $kontrol = $uyum
            ? 'Kalem satır toplamı genel toplam ile uyumlu görünüyor.'
            : 'Kalem satır toplamı ('.$toplamSatir.') ile genel toplam ('.$genel.') farklı; başlık alanlarını kontrol edin.';

        $html = '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium text-center">Sıra No</th>
                <th class="px-3 py-2 font-medium">Ürün / stok / açıklama</th>
                <th class="px-3 py-2 font-medium text-end">Miktar</th>
                <th class="px-3 py-2 font-medium">Birim</th>
                <th class="px-3 py-2 font-medium text-end">Birim fiyat</th>
                <th class="px-3 py-2 font-medium text-end">KDV %</th>
                <th class="px-3 py-2 font-medium text-end">Satır toplamı</th>
                <th class="px-3 py-2 font-medium text-end">Net toplam</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $html .= '<p class="mt-3 text-sm '.($uyum ? 'text-gray-600 dark:text-gray-400' : 'text-warning-600 dark:text-warning-400').'">'.e($kontrol).'</p>';

        return new HtmlString($html);
    }

    private function kalemTakipBilgisi(FaturaKalemi $kalem): string
    {
        $satirlar = [];

        foreach ($kalem->olcuDagilimlari as $dagilim) {
            $miktar = rtrim(rtrim(number_format((float) $dagilim->ana_miktar, 8, '.', ''), '0'), '.');
            $adetEsdegeri = rtrim(rtrim(number_format((float) $dagilim->adet_esdegeri, 8, '.', ''), '0'), '.');
            $birim = match ((string) $dagilim->takip_turu) {
                'uzunluk' => 'm',
                'alan' => 'm²',
                'hacim' => 'm³',
                'agirlik' => 'kg',
                default => trim((string) $dagilim->olcu_birimi),
            };
            $boyutlar = collect([$dagilim->en, $dagilim->boy, $dagilim->yukseklik])
                ->filter(fn ($deger): bool => $deger !== null && (float) $deger > 0)
                ->map(fn ($deger): string => rtrim(rtrim(number_format((float) $deger, 4, '.', ''), '0'), '.'))
                ->implode(' × ');

            $etiket = $miktar !== '' ? $miktar.($birim !== '' ? ' '.$birim : '') : '';
            if ($adetEsdegeri !== '') {
                $etiket .= ($etiket !== '' ? ' · ' : '').$adetEsdegeri.' adet eşdeğeri';
            }
            if ($boyutlar !== '') {
                $etiket .= ($etiket !== '' ? ' · ' : '').$boyutlar;
            }

            if ($etiket !== '') {
                $satirlar[] = '<span class="text-xs text-gray-500">Ölçü dağılımı: '.e($etiket).'</span>';
            }
        }

        $seriler = array_values(array_filter(array_map(
            static fn ($seri): string => trim((string) $seri),
            (array) ($kalem->seri_nolari ?? [])
        ), static fn (string $seri): bool => $seri !== ''));
        if ($seriler !== []) {
            $satirlar[] = '<span class="text-xs text-gray-500">Seri No Barkodu: '.e(implode(', ', $seriler)).'</span>';
        }

        return $satirlar === [] ? '' : '<div class="mt-1 space-y-0.5">'.implode('', $satirlar).'</div>';
    }

    private function odemelerHtml(): HtmlString
    {
        $fatura = $this->temelFatura();
        $fatura->load([
            'finansKapatmalari' => fn ($query) => $query
                ->select(['id', 'firma_id', 'fatura_id', 'finans_hareket_id', 'uygulanan_tutar', 'para_birimi'])
                ->orderBy('id'),
            'finansKapatmalari.finansHareketi:id,tarih,tur,durum',
        ]);

        $rows = '';
        foreach ($fatura->finansKapatmalari as $kapama) {
            /** @var FaturaFinansKapama $kapama */
            $hareket = $kapama->finansHareketi;
            $tur = $hareket?->tur instanceof FinansHareketTuru ? $hareket->tur->value : (string) ($hareket?->tur ?? '—');
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                </tr>',
                e(optional($hareket?->tarih)->format('d.m.Y H:i') ?? '—'),
                e(number_format((float) $kapama->uygulanan_tutar, 2, ',', '.').' '.$this->para($kapama, $fatura)),
                e((string) ($kapama->para_birimi ?: 'TRY')),
                e($tur),
                e($kapama->finans_hareket_id ? '#'.$kapama->finans_hareket_id : '—')
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="px-3 py-4 text-sm text-gray-500">Bu faturaya henüz finans kapaması düşmemiş.</td></tr>';
        }

        $html = '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium">Tarih</th>
                <th class="px-3 py-2 font-medium text-end">Tutar</th>
                <th class="px-3 py-2 font-medium">Para birimi</th>
                <th class="px-3 py-2 font-medium">Finans tipi</th>
                <th class="px-3 py-2 font-medium">Finans kaydı</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $html .= '<p class="mt-3 text-sm text-gray-600 dark:text-gray-400">'.nl2br(e(app(FaturaFinansKapamaServisi::class)->faturaOtomasyonOzetiMetni($fatura))).'</p>';

        return new HtmlString($html);
    }

    private function cariHareketleriHtml(): HtmlString
    {
        $fatura = $this->temelFatura();
        if (! $fatura->cari_id) {
            return new HtmlString('<p class="text-sm text-gray-500">Bu fatura için cari bağlantısı yok.</p>');
        }

        $fatura->load([
            'cariHareketleri' => fn ($query) => $query
                ->select(['id', 'firma_id', 'belge_id', 'belge_turu', 'islem_tarihi', 'borc', 'alacak', 'aciklama'])
                ->orderBy('islem_tarihi')
                ->orderBy('id'),
        ]);

        $running = '0.00';
        $pb = $fatura->para_birimi ?: 'TRY';
        $rows = '';
        foreach ($fatura->cariHareketleri as $hareket) {
            /** @var CariHareketi $hareket */
            $borc = (string) ($hareket->borc ?? '0');
            $alacak = (string) ($hareket->alacak ?? '0');
            $running = bcadd($running, $borc, 2);
            $running = bcsub($running, $alacak, 2);
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm text-end font-medium">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                </tr>',
                e(optional($hareket->islem_tarihi)->format('d.m.Y H:i') ?? '—'),
                e(number_format((float) $borc, 2, ',', '.').' '.$pb),
                e(number_format((float) $alacak, 2, ',', '.').' '.$pb),
                e(number_format((float) $running, 2, ',', '.').' '.$pb),
                e((string) ($hareket->aciklama ?? '—'))
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="px-3 py-4 text-sm text-gray-500">Bu fatura için cari hareketi bulunamadı.</td></tr>';
        }

        $html = '<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium">Tarih</th>
                <th class="px-3 py-2 font-medium text-end">Borç</th>
                <th class="px-3 py-2 font-medium text-end">Alacak</th>
                <th class="px-3 py-2 font-medium text-end">Bakiye (işlem sırası)</th>
                <th class="px-3 py-2 font-medium">Açıklama</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>';
        $html .= '<p class="mt-2 text-xs text-gray-500">Bakiye, bu faturaya bağlı cari hareket satırları üzerinden kronolojik birikimli gösterimdir; resmi ekstre ile mutlaka karşılaştırın.</p>';

        return new HtmlString($html);
    }

    private function stokHareketleriHtml(): HtmlString
    {
        $fatura = $this->temelFatura();
        $fatura->load([
            'stokHareketleri' => fn ($query) => $query
                ->select(['id', 'firma_id', 'belge_id', 'belge_turu', 'stok_id', 'islem_turu', 'miktar', 'tarih'])
                ->orderBy('tarih')
                ->orderBy('id'),
            'stokHareketleri.stokKarti:id,ad',
            'stokHareketleri.seriHareketleri.seriNo:id,seri_no,barkod',
        ]);

        $rows = '';
        foreach ($fatura->stokHareketleri as $hareket) {
            /** @var StokHareketi $hareket */
            $ad = $hareket->stokKarti?->ad ?? '—';
            $tur = $hareket->islem_turu instanceof StokHareketIslemTuru ? $hareket->islem_turu->value : (string) $hareket->islem_turu;
            $takip = $this->stokHareketiTakipBilgisi($hareket);
            $rows .= sprintf(
                '<tr class="border-b border-gray-100 dark:border-white/10">
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm text-end">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                    <td class="px-3 py-2 text-sm">%s</td>
                </tr>',
                e($ad),
                e($tur),
                e((string) $hareket->miktar),
                e($takip),
                e(optional($hareket->tarih)->format('d.m.Y H:i') ?? '—')
            );
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" class="px-3 py-4 text-sm text-gray-500">Stok hareketi yok.</td></tr>';
        }

        return new HtmlString('<div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10"><table class="w-full text-sm">
            <thead><tr class="bg-gray-50 dark:bg-white/5 text-start">
                <th class="px-3 py-2 font-medium">Stok</th>
                <th class="px-3 py-2 font-medium">İşlem türü</th>
                <th class="px-3 py-2 font-medium text-end">Miktar</th>
                <th class="px-3 py-2 font-medium">Seri No Barkodu</th>
                <th class="px-3 py-2 font-medium">Tarih</th>
            </tr></thead><tbody>'.$rows.'</tbody></table></div>');
    }

    private function stokHareketiTakipBilgisi(StokHareketi $hareket): string
    {
        $seriler = $hareket->seriHareketleri
            ->map(fn ($seriHareketi): string => (string) ($seriHareketi->seriNo?->barkod ?: $seriHareketi->seriNo?->seri_no ?: ''))
            ->filter()
            ->values()
            ->all();

        $satirlar = [];
        if ($seriler !== []) {
            $satirlar[] = 'Seri No Barkodu: '.implode(', ', $seriler);
        }

        return $satirlar === [] ? '—' : implode(' · ', $satirlar);
    }

    private function baglantilarHtml(): HtmlString
    {
        $fatura = $this->temelFatura();
        $fatura->load('bagliFatura:id,fatura_no,tur');

        $bagli = $fatura->bagliFatura
            ? '<p class="text-sm">Bağlı fatura: <a class="text-primary-600 hover:underline font-medium" href="'.e(FaturaKaynagi::getUrl('view', ['record' => $fatura->bagliFatura->id])).'">'.e($fatura->bagliFatura->fatura_no ?: '#'.$fatura->bagliFatura->id).'</a></p>'
            : '<p class="text-sm text-gray-500">Bağlı fatura yok.</p>';

        $digerleri = Fatura::query()
            ->where('bagli_fatura_id', $fatura->getKey())
            ->orderBy('id')
            ->get(['id', 'fatura_no', 'tur']);

        if ($digerleri->isEmpty()) {
            $altlar = '<span class="text-sm text-gray-500">Bu faturaya bağlı alt kayıt yok.</span>';
        } else {
            $parts = [];
            foreach ($digerleri as $diger) {
                $tur = $diger->tur instanceof FaturaTuru ? $diger->tur->value : (string) $diger->tur;
                $parts[] = '<a href="'.e(FaturaKaynagi::getUrl('view', ['record' => $diger->id])).'" class="text-primary-600 hover:underline text-sm font-medium">'.e($diger->fatura_no ?: '#'.$diger->id).'</a> <span class="text-gray-500 text-xs">('.e($tur).')</span>';
            }
            $altlar = '<div class="flex flex-wrap gap-x-3 gap-y-1">'.implode('', $parts).'</div>';
        }

        return new HtmlString('<div class="space-y-4">'.$bagli.'<div><h3 class="mb-2 text-sm font-medium">Bu faturaya bağlı kayıtlar</h3>'.$altlar.'</div></div>');
    }
}
