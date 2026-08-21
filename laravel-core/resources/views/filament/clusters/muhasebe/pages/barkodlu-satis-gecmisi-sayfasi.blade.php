<x-filament-panels::page>
    <div class="muhasebe-cork-screen cork-sales-operations">
    @if($this->perakendeOzetGoster())
        @php($perakendeOzet = $this->perakendeOzet())

        <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-700 dark:bg-amber-900/20">
                <div class="text-xs text-amber-800 dark:text-amber-200">Perakende Cari</div>
                <div class="mt-1 text-sm font-semibold text-amber-900 dark:text-amber-100">{{ $perakendeOzet['cari_ad'] ?? 'Perakende Musteri' }}</div>
                <div class="mt-1 text-[11px] text-amber-700 dark:text-amber-300">Donem: {{ $perakendeOzet['tarih_etiketi'] ?? 'Tum zamanlar' }}</div>
            </div>
            <div class="rounded-xl border border-blue-200 bg-blue-50 p-3 dark:border-blue-700 dark:bg-blue-900/20">
                <div class="text-xs text-blue-800 dark:text-blue-200">Satis Adedi</div>
                <div class="mt-1 text-lg font-semibold text-blue-900 dark:text-blue-100">{{ number_format((int) ($perakendeOzet['satis_adedi'] ?? 0), 0, ',', '.') }}</div>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-700 dark:bg-emerald-900/20">
                <div class="text-xs text-emerald-800 dark:text-emerald-200">Ciro</div>
                <div class="mt-1 text-lg font-semibold text-emerald-900 dark:text-emerald-100">{{ number_format((float) ($perakendeOzet['ciro'] ?? 0), 2, ',', '.') }}</div>
            </div>
            <div class="rounded-xl border border-red-200 bg-red-50 p-3 dark:border-red-700 dark:bg-red-900/20">
                <div class="text-xs text-red-800 dark:text-red-200">Iade</div>
                <div class="mt-1 text-lg font-semibold text-red-900 dark:text-red-100">{{ number_format((float) ($perakendeOzet['iade'] ?? 0), 2, ',', '.') }}</div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-3 dark:border-slate-700 dark:bg-slate-900/20">
                <div class="text-xs text-slate-800 dark:text-slate-200">Net</div>
                <div class="mt-1 text-lg font-semibold text-slate-900 dark:text-slate-100">{{ number_format((float) ($perakendeOzet['net'] ?? 0), 2, ',', '.') }}</div>
                <div class="mt-1 text-[11px] text-slate-700 dark:text-slate-300">Iptal: {{ number_format((int) ($perakendeOzet['iptal_adedi'] ?? 0), 0, ',', '.') }}</div>
            </div>
        </div>

        @if(count($perakendeOzet['para_birimi_kirilimi'] ?? []) > 0)
            <div class="mb-4 rounded-xl border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900/30">
                <div class="mb-2 text-xs font-semibold text-gray-700 dark:text-gray-200">Perakende Para Birimi Kirilimi (Tamamlanan satislar)</div>
                <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach(($perakendeOzet['para_birimi_kirilimi'] ?? []) as $satir)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-2 dark:border-gray-700 dark:bg-gray-800/40">
                            <div class="text-xs text-gray-600 dark:text-gray-300">{{ $satir['para_birimi'] ?? 'TRY' }}</div>
                            <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-gray-100">Adet: {{ number_format((int) ($satir['satis_adedi'] ?? 0), 0, ',', '.') }}</div>
                            <div class="text-sm text-gray-800 dark:text-gray-200">Ciro: {{ number_format((float) ($satir['ciro'] ?? 0), 2, ',', '.') }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

        {{ $this->table }}
    </div>
</x-filament-panels::page>
