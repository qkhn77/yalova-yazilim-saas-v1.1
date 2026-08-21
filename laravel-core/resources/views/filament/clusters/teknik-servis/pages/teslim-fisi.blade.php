<x-filament-panels::page>
    <style>
        {!! $this->sayfaCss() !!}
        @media print {
            html, body {
                margin: 0 !important;
                padding: 0 !important;
            }
            .fi-main, .fi-main-ctn, .fi-page, .fi-page-content, [data-filament-main], [data-filament-page] {
                margin: 0 !important;
                padding-top: 0 !important;
                padding-bottom: 0 !important;
            }
            .fi-topbar, .fi-sidebar, .fi-breadcrumbs, .fi-header, .fi-footer, .fi-tabs, .print\:hidden {
                display: none !important;
            }
            .rounded-lg.border.border-gray-300.bg-white.p-6.text-black {
                border: 0 !important;
                padding: 0 !important;
                margin: 0 !important;
            }
        }
    </style>

    <div class="space-y-4">
        <div class="flex items-center justify-end print:hidden">
            <x-filament::button color="success" icon="heroicon-o-printer" onclick="window.print()">
                Yazdır
            </x-filament::button>
        </div>

        <div class="rounded-lg border border-gray-300 bg-white p-6 text-black" style="{{ $this->belgeKapsayiciStili() }}">
            {!! $this->icerik() !!}
        </div>
    </div>
</x-filament-panels::page>

@if($otomatikYazdir ?? false)
<script>
    setTimeout(() => window.print(), 250);
</script>
@endif
