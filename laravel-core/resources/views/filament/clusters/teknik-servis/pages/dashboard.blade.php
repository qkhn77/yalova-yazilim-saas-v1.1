<x-filament-panels::page>
    @php
        $durumKartlari = $durumKartlari ?? [];
        $listeKisayollari = $listeKisayollari ?? [];
        $operasyonMetrikleri = $operasyonMetrikleri ?? [];
        $durumKartKoleksiyonu = collect($durumKartlari);
        $operasyonMetrikKoleksiyonu = collect($operasyonMetrikleri);
        $acikServisKarti = collect($durumKartlari)->firstWhere('baslik', 'Açık Servisler');
        $toplamAcikIs = (int) ($acikServisKarti['deger'] ?? 0);

        $kartRenkleri = [
            'primary' => 'text-primary-600 bg-primary-50 ring-primary-600/15 dark:bg-primary-500/10 dark:text-primary-400 dark:ring-primary-400/20',
            'info' => 'text-sky-700 bg-sky-50 ring-sky-600/15 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-400/20',
            'warning' => 'text-warning-700 bg-warning-50 ring-warning-600/15 dark:bg-warning-500/10 dark:text-warning-300 dark:ring-warning-400/20',
            'success' => 'text-success-700 bg-success-50 ring-success-600/15 dark:bg-success-500/10 dark:text-success-300 dark:ring-success-400/20',
            'gray' => 'text-gray-700 bg-gray-50 ring-gray-600/15 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10',
        ];

        $birinciSatirKartlari = array_values(array_filter([
            $durumKartKoleksiyonu->firstWhere('baslik', 'Açık Servisler'),
            $durumKartKoleksiyonu->firstWhere('baslik', 'Tezgahtakiler'),
            $durumKartKoleksiyonu->firstWhere('baslik', 'Parça Bekleyen'),
            $operasyonMetrikKoleksiyonu->firstWhere('baslik', 'Teslim bekleyen'),
        ]));

        $ikinciSatirKartlari = array_values(array_filter([
            $operasyonMetrikKoleksiyonu->firstWhere('baslik', 'Bu ay teslim edilen'),
            $operasyonMetrikKoleksiyonu->firstWhere('baslik', 'Bakımı yaklaşan'),
            $durumKartKoleksiyonu->firstWhere('baslik', 'Garantiye Giden'),
            $operasyonMetrikKoleksiyonu->firstWhere('baslik', 'Garantili cihazlar'),
        ]));
    @endphp

    <div class="yk-dashboard-shell yk-teknik-servis-dashboard space-y-5">
        <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_26rem]">
            <div class="yk-dashboard-panel yk-dashboard-summary-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div class="min-w-0 flex-1">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <div class="text-xs font-medium uppercase text-gray-500 dark:text-gray-400">Aktif servis yükü</div>
                                <div class="mt-1 flex items-end gap-2">
                                    <div class="text-3xl font-semibold leading-none text-gray-950 dark:text-white">{{ number_format($toplamAcikIs, 0, ',', '.') }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">açık kayıt</div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="flex flex-wrap gap-2">
                        <a href="{{ $acikListeUrl }}" class="inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5">
                            <x-filament::icon icon="heroicon-m-list-bullet" class="h-4 w-4" />
                            Açık servisler
                        </a>
                        <a href="{{ $tumKayitlarUrl }}" class="inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-semibold text-gray-700 ring-1 ring-gray-300 hover:bg-gray-50 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5">
                            <x-filament::icon icon="heroicon-m-archive-box" class="h-4 w-4" />
                            Tüm kayıtlar
                        </a>
                    </div>
                </div>
            </div>

            <div class="yk-dashboard-panel yk-dashboard-quick-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="text-sm font-semibold text-gray-950 dark:text-white">Yeni servis aç</div>
                <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                    @foreach (($hizliIslemler ?? []) as $islem)
                        <a href="{{ $islem['url'] }}" class="inline-flex h-9 items-center justify-center rounded-md bg-primary-600 px-3 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600">
                            {{ $islem['etiket'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-3">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($birinciSatirKartlari as $kart)
                    @php
                        $renk = $kartRenkleri[$kart['renk'] ?? 'gray'] ?? $kartRenkleri['gray'];
                    @endphp

                    <a href="{{ $kart['url'] }}" class="yk-dashboard-module-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-500/60">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $kart['baslik'] }}</div>
                                @if (filled($kart['aciklama'] ?? null))
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kart['aciklama'] }}</div>
                                @endif
                                <div class="mt-3 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((int) $kart['deger'], 0, ',', '.') }}</div>
                            </div>
                            <div class="rounded-md p-2 ring-1 {{ $renk }}">
                                <x-filament::icon icon="{{ $kart['ikon'] }}" class="h-5 w-5" />
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($ikinciSatirKartlari as $kart)
                    @php
                        $renk = $kartRenkleri[$kart['renk'] ?? 'gray'] ?? $kartRenkleri['gray'];
                    @endphp

                    <a href="{{ $kart['url'] }}" class="yk-dashboard-module-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm transition hover:border-primary-300 hover:shadow-md dark:border-white/10 dark:bg-gray-900 dark:hover:border-primary-500/60">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $kart['baslik'] }}</div>
                                @if (filled($kart['aciklama'] ?? null))
                                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kart['aciklama'] }}</div>
                                @endif
                                <div class="mt-3 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format((int) $kart['deger'], 0, ',', '.') }}</div>
                            </div>
                            <div class="rounded-md p-2 ring-1 {{ $renk }}">
                                <x-filament::icon icon="{{ $kart['ikon'] }}" class="h-5 w-5" />
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>

        <div class="yk-dashboard-panel rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">Operasyon kısayolları</div>
                    <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Sık bakılan servis listelerine tek dokunuş.</div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($listeKisayollari as $kisayol)
                    @php
                        $renk = $kartRenkleri[$kisayol['renk'] ?? 'gray'] ?? $kartRenkleri['gray'];
                    @endphp

                    <a href="{{ $kisayol['url'] }}" class="yk-dashboard-quick-card rounded-md border border-gray-200 px-3 py-3 transition hover:border-primary-300 hover:bg-primary-50/40 dark:border-white/10 dark:hover:border-primary-500/50 dark:hover:bg-primary-500/10">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-sm font-semibold text-gray-800 dark:text-gray-100">{{ $kisayol['baslik'] }}</div>
                                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $kisayol['aciklama'] }}</div>
                            </div>
                            <div class="shrink-0 rounded-md px-2 py-1 text-sm font-semibold ring-1 {{ $renk }}">
                                {{ number_format((int) $kisayol['deger'], 0, ',', '.') }}
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</x-filament-panels::page>
