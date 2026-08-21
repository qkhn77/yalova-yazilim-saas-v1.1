<x-filament-panels::page>
    <style>
        @page {
            size: {{ $printPageSize ?? '80mm auto' }};
            margin: 5mm;
        }

        {!! $renderedCss ?? '' !!}

        #satis-fisi .fis-kapsayici {
            box-sizing: border-box !important;
            padding-right: {{ $printRightGap ?? '0mm' }} !important;
            max-width: calc(100% - {{ $printRightGap ?? '0mm' }}) !important;
            overflow-wrap: anywhere;
            word-break: break-word;
        }

        #satis-fisi .fis-finans-ozet {
            margin-top: 6px;
            border-top: 1px dashed #888;
            padding-top: 5px;
            font-size: 11px;
        }

        #satis-fisi .fis-finans-ozet > div,
        #satis-fisi .fis-taksit-ozet > div {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 2px;
        }

        #satis-fisi .fis-taksit-ozet {
            margin-top: 4px;
            padding-top: 4px;
            border-top: 1px dashed #bbb;
        }

        @media print {
            .fi-topbar, .fi-sidebar, .fi-breadcrumbs, .fi-header, .fi-footer, .fi-tabs, .fi-main-ctn > .fi-section {
                display: none !important;
            }
            #satis-fisi,
            #satis-fisi * {
                visibility: visible !important;
            }
            #satis-fisi {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                box-sizing: border-box;
            }
        }
    </style>

    <div class="space-y-4">
        <div class="flex items-center justify-end print:hidden">
            <x-filament::button color="success" icon="heroicon-o-printer" onclick="window.print()">
                Yazdir
            </x-filament::button>
        </div>

        @if (! $satis)
            <x-filament::section>
                <p class="text-sm text-gray-600 dark:text-gray-300">Satis kaydi bulunamadi.</p>
            </x-filament::section>
        @else
            <div id="satis-fisi" class="space-y-4 rounded-lg border border-gray-300 bg-white p-4 text-black">
                @if (! empty($renderedHtml))
                    {!! $this->renderedHtmlCikti() !!}
                    {!! $this->ekFinansOzetiCikti() !!}
                @else
                    <div class="text-sm text-gray-700">
                        Fis sablonu bulunamadi. Lutfen Barkodlu Satis > Ayarlar > Satis Fisi Duzenle ekranindan sablon tanimlayin.
                    </div>
                @endif
            </div>
        @endif
    </div>
</x-filament-panels::page>

@if($otomatikYazdir ?? false)
<script>
    setTimeout(() => window.print(), 250);
</script>
@endif
