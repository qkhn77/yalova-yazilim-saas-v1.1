<x-filament-panels::page>
    <div class="teklif-cork-screen teklif-cork-offer-view">
    @if(! $this->detayModu())
        <div class="fi-section teklif-cork-summary rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @foreach($this->hizliOzetSatirlari() as $etiket => $deger)
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $etiket }}</dt>
                        <dd class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $deger }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @else
    <style>
        {!! $this->onizlemeCss() !!}

        .teklif-kayit-onizleme {
            width: 100%;
            overflow-x: hidden !important;
        }

        .teklif-kayit-onizleme .teklif-wrap,
        .teklif-kayit-onizleme .teklif-mini,
        .teklif-kayit-onizleme .eas-sheet,
        .teklif-kayit-onizleme .pdfpage {
            box-sizing: border-box !important;
            max-width: 100% !important;
            margin: 0 auto !important;
            min-width: 0 !important;
            overflow: hidden !important;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .teklif-kayit-onizleme .pdfpage {
            background: #fff !important;
            width: 100% !important;
            height: auto !important;
            aspect-ratio: 793 / 1123;
            min-height: auto !important;
            position: relative !important;
        }

        .teklif-kayit-onizleme .pdfpage > svg {
            position: absolute !important;
            inset: 0 !important;
            width: 100% !important;
            height: 100% !important;
        }

        .teklif-kayit-onizleme img,
        .teklif-kayit-onizleme table,
        .teklif-kayit-onizleme svg {
            max-width: 100% !important;
        }

        .teklif-kayit-onizleme > div {
            width: 100% !important;
            max-width: 100% !important;
        }

        .teklif-kayit-onizleme .yb-offer-page {
            width: 100% !important;
            max-width: none !important;
        }

        .teklif-kayit-onizleme .pdfpage text,
        .teklif-kayit-onizleme .pdfpage tspan {
            font-family: "DejaVu Sans", "Segoe UI", Arial, sans-serif !important;
            text-rendering: geometricPrecision;
        }

        @media print {
            @page {
                size: A4 portrait;
                margin: 0;
            }

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            .fi-topbar,
            .fi-sidebar,
            .fi-header,
            .fi-page-header,
            .fi-btn {
                display: none !important;
            }

            .fi-main,
            .fi-main-ctn,
            .fi-page,
            .fi-layout,
            .teklif-kayit-onizleme,
            .teklif-kayit-onizleme > div {
                padding: 0 !important;
                margin: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                min-height: auto !important;
                box-shadow: none !important;
                border: 0 !important;
                background: #fff !important;
                overflow: visible !important;
            }

            .teklif-kayit-onizleme > div {
                max-width: none !important;
                min-height: 0 !important;
            }

            .teklif-kayit-onizleme .teklif-wrap,
            .teklif-kayit-onizleme .teklif-mini,
            .teklif-kayit-onizleme .eas-sheet,
            .teklif-kayit-onizleme .pdfpage,
            .teklif-kayit-onizleme .yb-offer-page {
                width: 100% !important;
                max-width: none !important;
                margin: 0 !important;
                padding-left: 0 !important;
                padding-right: 0 !important;
                overflow: visible !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .teklif-kayit-onizleme .yb-offer-brand,
            .teklif-kayit-onizleme .yb-offer-brand::after,
            .teklif-kayit-onizleme .yb-offer-summary__total td,
            .teklif-kayit-onizleme .yb-offer-disclaimer {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .teklif-kayit-onizleme .yb-offer-header,
            .teklif-kayit-onizleme .yb-offer-info-grid,
            .teklif-kayit-onizleme .yb-offer-bottom-grid,
            .teklif-kayit-onizleme .yb-offer-table-wrap {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }
        }
    </style>

    <div class="teklif-kayit-onizleme">
        <div class="mx-auto bg-white text-black" style="{{ $this->onizlemeKapsayiciStili() }}">
            {!! $this->onizlemeHtml() !!}
        </div>
    </div>

    @if(request()->boolean('auto_print'))
        <script>
            window.addEventListener('load', () => {
                window.setTimeout(() => window.print(), 250)
            }, { once: true })
        </script>
    @endif
    @endif
    </div>
</x-filament-panels::page>
