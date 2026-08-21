<?php

namespace App\TeklifYonetimi\Servisler;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class TeklifPdfServisi
{
    public function a4PdfOlustur(string $html, string $css): string
    {
        $geciciKlasor = storage_path('app/teklif-pdf');
        File::ensureDirectoryExists($geciciKlasor);

        $isId = (string) Str::uuid();
        $htmlKlasor = $geciciKlasor.DIRECTORY_SEPARATOR.$isId;
        $chromeProfilKlasoru = $htmlKlasor.DIRECTORY_SEPARATOR.'chrome';
        $htmlYolu = $htmlKlasor.DIRECTORY_SEPARATOR.'teklif.html';
        $pdfYolu = $geciciKlasor.DIRECTORY_SEPARATOR.$isId.'.pdf';

        File::ensureDirectoryExists($chromeProfilKlasoru);
        File::put($htmlYolu, $this->a4HtmlBelgesi($this->yerelGorselleriGom($html), $css));

        try {
            $this->chromeIlePdfYazdir($htmlYolu, $pdfYolu, $chromeProfilKlasoru);
        } finally {
            File::delete($htmlYolu);
            File::deleteDirectory($htmlKlasor);
        }

        if (! is_file($pdfYolu) || filesize($pdfYolu) < 1) {
            throw new RuntimeException('PDF dosyası oluşturulamadı.');
        }

        return $pdfYolu;
    }

    private function chromeIlePdfYazdir(string $htmlYolu, string $pdfYolu, string $chromeProfilKlasoru): void
    {
        $chrome = $this->chromeYolu();
        $htmlUri = 'file:///'.str_replace('\\', '/', $htmlYolu);

        $komut = [
            $chrome,
            '--headless=new',
            '--disable-gpu',
            '--disable-extensions',
            '--disable-background-networking',
            '--no-sandbox',
            '--allow-file-access-from-files',
            '--run-all-compositor-stages-before-draw',
            '--window-size=1600,2200',
            '--virtual-time-budget=2000',
            '--user-data-dir='.$chromeProfilKlasoru,
            '--print-to-pdf='.$pdfYolu,
            '--print-to-pdf-no-header',
            '--no-pdf-header-footer',
            $htmlUri,
        ];

        $process = new Process($komut, null, null, null, 75);
        $process->run();

        if (! $process->isSuccessful()) {
            $hata = trim($process->getErrorOutput() ?: $process->getOutput());

            throw new RuntimeException('PDF oluşturulurken Chrome çalıştırılamadı.'.($hata !== '' ? ' '.$hata : ''));
        }
    }

    private function chromeYolu(): string
    {
        $adaylar = array_filter([
            env('TEKLIF_PDF_CHROME_PATH'),
            env('CHROME_PATH'),
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'google-chrome',
            'chromium',
            'chromium-browser',
            'chrome',
            'msedge',
        ]);

        $calistirilabilirBulucu = new ExecutableFinder();

        foreach ($adaylar as $aday) {
            $aday = (string) $aday;

            if (is_file($aday)) {
                return $aday;
            }

            if (! str_contains($aday, DIRECTORY_SEPARATOR)) {
                $bulunan = $calistirilabilirBulucu->find($aday);

                if ($bulunan) {
                    return $bulunan;
                }
            }
        }

        throw new RuntimeException('PDF oluşturmak için Chrome veya Edge bulunamadı.');
    }

    private function a4HtmlBelgesi(string $html, string $css): string
    {
        $baseUrl = rtrim(url('/'), '/').'/';
        $css = str_replace('</style>', '<\/style>', $css);

        return <<<HTML
<!doctype html>
<html lang="tr">
<head>
    <meta charset="utf-8">
    <base href="{$baseUrl}">
    <style>
        @page {
            size: A4 portrait;
            margin: 5mm;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        body {
            min-width: 200mm;
            font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        img,
        table,
        svg {
            max-width: 100%;
        }
    </style>
    <style>
        {$css}
    </style>
    <style>
        * {
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        .teklif-pdf-a4 {
            box-sizing: border-box;
            width: 200mm;
            height: 287mm;
            margin: 0 auto;
            overflow: hidden;
            background: #fff;
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .teklif-pdf-scale {
            width: 210mm;
            flex: 0 0 210mm;
            transform-origin: top center;
        }

        .teklif-pdf-scale > div {
            box-sizing: border-box !important;
            width: 210mm !important;
            max-width: 210mm !important;
            min-height: 297mm !important;
            margin: 0 auto !important;
            overflow: visible !important;
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }

        .teklif-pdf-scale .yb-offer-page,
        .teklif-pdf-scale .teklif-wrap,
        .teklif-pdf-scale .eas-sheet,
        .teklif-pdf-scale .pdfpage {
            box-sizing: border-box !important;
            width: 210mm !important;
            max-width: 210mm !important;
            min-height: 297mm !important;
            margin: 0 auto !important;
            overflow: visible !important;
        }

        .teklif-pdf-scale .yb-offer-header,
        .teklif-pdf-scale .yb-offer-info-grid,
        .teklif-pdf-scale .yb-offer-bottom-grid,
        .teklif-pdf-scale .yb-offer-table-wrap {
            break-inside: avoid !important;
            page-break-inside: avoid !important;
        }
    </style>
    <script>
        function teklifPdfSayfayaSigdir() {
            const alan = document.querySelector('.teklif-pdf-a4');
            const olcekKutu = document.querySelector('.teklif-pdf-scale');
            const sayfa = olcekKutu ? olcekKutu.firstElementChild : null;

            if (!alan || !olcekKutu || !sayfa) {
                return;
            }

            olcekKutu.style.transform = 'scale(1)';
            olcekKutu.style.height = 'auto';

            const olcu = sayfa.getBoundingClientRect();
            const genislikOrani = alan.clientWidth / Math.max(olcu.width, 1);
            const yukseklikOrani = alan.clientHeight / Math.max(olcu.height, 1);
            const oran = Math.min(genislikOrani, yukseklikOrani, 1);

            olcekKutu.style.transform = 'scale(' + oran + ')';
            olcekKutu.style.height = (olcu.height * oran) + 'px';
            olcekKutu.dataset.scale = oran.toFixed(4);
        }

        window.addEventListener('load', () => {
            teklifPdfSayfayaSigdir();

            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(teklifPdfSayfayaSigdir);
            }

            window.setTimeout(teklifPdfSayfayaSigdir, 100);
            window.setTimeout(teklifPdfSayfayaSigdir, 350);
        }, { once: true });
    </script>
</head>
<body>
    <main class="teklif-pdf-a4">
        <div class="teklif-pdf-scale">
            {$html}
        </div>
    </main>
</body>
</html>
HTML;
    }

    private function yerelGorselleriGom(string $html): string
    {
        return preg_replace_callback(
            '/(<img\b[^>]*\bsrc\s*=\s*)(["\'])([^"\']+)(\2)/i',
            function (array $eslesme): string {
                $src = html_entity_decode($eslesme[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $dataUri = $this->gorselDataUri($src);

                if (! $dataUri) {
                    return $eslesme[0];
                }

                return $eslesme[1].$eslesme[2].htmlspecialchars($dataUri, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$eslesme[2];
            },
            $html
        ) ?? $html;
    }

    private function gorselDataUri(string $src): ?string
    {
        $src = trim($src);

        if ($src === '' || str_starts_with($src, 'data:')) {
            return null;
        }

        $yol = parse_url($src, PHP_URL_PATH);
        if (! is_string($yol) || trim($yol) === '') {
            return null;
        }

        $yol = rawurldecode($yol);
        $yol = str_replace('\\', '/', $yol);
        $yol = trim($yol, '/');

        $prefixler = array_filter(array_unique([
            trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/'),
            trim((string) request()->getBasePath(), '/'),
        ]));

        foreach ($prefixler as $prefix) {
            if (str_starts_with($yol, $prefix.'/')) {
                $yol = substr($yol, strlen($prefix) + 1);
                break;
            }
        }

        $dosyaYolu = $this->yerelGorselDosyaYolu($yol);
        if (! $dosyaYolu) {
            return null;
        }

        $mime = File::mimeType($dosyaYolu) ?: 'image/png';
        $icerik = File::get($dosyaYolu);

        return 'data:'.$mime.';base64,'.base64_encode($icerik);
    }

    private function yerelGorselDosyaYolu(string $yol): ?string
    {
        $yol = trim(str_replace('\\', '/', $yol), '/');

        if ($yol === '' || str_contains($yol, '..')) {
            return null;
        }

        $adaylar = [public_path($yol)];

        if (str_starts_with($yol, 'storage/')) {
            $adaylar[] = storage_path('app/public/'.substr($yol, strlen('storage/')));
        }

        foreach ($adaylar as $aday) {
            if (is_file($aday)) {
                return $aday;
            }
        }

        return null;
    }
}
