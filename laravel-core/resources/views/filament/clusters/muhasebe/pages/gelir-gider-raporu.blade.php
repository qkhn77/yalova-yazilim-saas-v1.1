@php
    /** @var \App\Filament\Clusters\Muhasebe\Pages\GelirGiderRaporuSayfasi $this */
    $satirlar = $this->rapor['satirlar'] ?? [];
    $para = static fn (mixed $tutar, string $paraBirimi): string => number_format((float) $tutar, 2, ',', '.').' '.strtoupper($paraBirimi);
@endphp

<x-filament-panels::page>
    <div class="muhasebe-cork-screen muhasebe-gelir-gider space-y-6">
        <x-filament::section class="muhasebe-cork-card">
            <x-slot name="heading">Rapor dönemi</x-slot>
            <x-slot name="description">Onaylı faturalar ve aktif masraf kayıtları seçilen firma kapsamında hesaplanır.</x-slot>

            <form wire:submit="raporuYukle" class="muhasebe-cork-toolbar space-y-4">
                {{ $this->form }}

                <div class="flex flex-wrap items-center justify-end gap-3">
                    <x-filament::button
                        type="button"
                        color="gray"
                        icon="heroicon-o-arrow-path"
                        wire:click="filtreleriSifirla"
                        wire:loading.attr="disabled"
                        wire:target="filtreleriSifirla"
                    >
                        Bu aya dön
                    </x-filament::button>
                    <x-filament::button
                        type="submit"
                        icon="heroicon-o-funnel"
                        wire:loading.attr="disabled"
                        wire:target="raporuYukle"
                    >
                        Raporu getir
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        @if (($this->rapor['firma_id'] ?? null) === null)
            <x-filament::section class="muhasebe-cork-card">
                <x-slot name="heading">Aktif firma seçilmedi</x-slot>
                <p class="text-sm text-gray-600 dark:text-gray-400">Gelir-gider verisini görmek için üst menüden aktif firma seçin.</p>
            </x-filament::section>
        @else
            <x-filament::section>
                <x-slot name="heading">Dönem özeti</x-slot>
                <x-slot name="description">
                    {{ $this->rapor['baslangic_gosterim'] ?? $this->rapor['baslangic'] ?? '—' }} —
                    {{ $this->rapor['bitis_gosterim'] ?? $this->rapor['bitis'] ?? '—' }} ·
                    {{ number_format((int) ($this->rapor['fatura_adedi'] ?? 0), 0, ',', '.') }} onaylı fatura
                </x-slot>

                @if ($satirlar === [])
                    <div class="rounded-lg border border-dashed border-gray-300 px-4 py-8 text-center text-sm text-gray-500 dark:border-white/20 dark:text-gray-400">
                        Seçilen dönemde onaylı gelir veya gider faturası bulunamadı.
                    </div>
                @else
                    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                        @foreach ($satirlar as $satir)
                            @php
                                $net = (string) ($satir['Net'] ?? '0.00');
                                $netPozitif = bccomp($net, '0', 2) >= 0;
                            @endphp
                            <div class="muhasebe-cork-kpi-card rounded-xl border border-gray-200 bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10">
                                <div class="flex items-center justify-between gap-3">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $satir['Para Birimi'] }}</span>
                                    <span @class([
                                        'muhasebe-cork-badge rounded-full px-2 py-1 text-xs font-medium',
                                        'bg-success-50 text-success-700 dark:bg-success-400/10 dark:text-success-400' => $netPozitif,
                                        'bg-danger-50 text-danger-700 dark:bg-danger-400/10 dark:text-danger-400' => ! $netPozitif,
                                    ])>{{ $netPozitif ? 'Pozitif net' : 'Negatif net' }}</span>
                                </div>
                                <dl class="mt-4 space-y-3 text-sm">
                                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Gelir</dt><dd class="font-semibold text-success-700 dark:text-success-400">{{ $para($satir['Gelir Toplam'] ?? '0.00', $satir['Para Birimi']) }}</dd></div>
                                    <div class="flex justify-between gap-3"><dt class="text-gray-500 dark:text-gray-400">Gider</dt><dd class="font-semibold text-danger-700 dark:text-danger-400">{{ $para($satir['Gider Toplam'] ?? '0.00', $satir['Para Birimi']) }}</dd></div>
                                    <div class="flex justify-between gap-3 border-t border-gray-100 pt-3 dark:border-white/10"><dt class="font-medium">Net</dt><dd class="font-semibold">{{ $para($net, $satir['Para Birimi']) }}</dd></div>
                                </dl>
                                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                                    {{ number_format((int) ($satir['Gelir Fatura Adedi'] ?? 0), 0, ',', '.') }} gelir ·
                                    {{ number_format((int) ($satir['Gider Fatura Adedi'] ?? 0), 0, ',', '.') }} gider faturası ·
                                    {{ number_format((int) ($satir['Masraf Adedi'] ?? 0), 0, ',', '.') }} hızlı masraf
                                </p>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-filament::section>

            @if ($satirlar !== [])
            <x-filament::section class="muhasebe-cork-card">
                    <x-slot name="heading">Para birimi kırılımı</x-slot>
                    <x-slot name="description">Para birimleri birbirine çevrilmeden ayrı tutulur.</x-slot>
                    <div class="muhasebe-cork-table-wrap overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                        <table class="muhasebe-cork-table w-full min-w-[54rem] text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-left dark:bg-white/5">
                                    <th class="px-4 py-3 font-medium">Para birimi</th>
                                    <th class="px-4 py-3 text-right font-medium">Fatura</th>
                                    <th class="px-4 py-3 text-right font-medium">Gelir faturası</th>
                                    <th class="px-4 py-3 text-right font-medium">Gider faturası</th>
                                    <th class="px-4 py-3 text-right font-medium">Hızlı masraf</th>
                                    <th class="px-4 py-3 text-right font-medium">Gelir</th>
                                    <th class="px-4 py-3 text-right font-medium">Gider</th>
                                    <th class="px-4 py-3 text-right font-medium">Net</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($satirlar as $satir)
                                    <tr class="muhasebe-cork-total">
                                        <td class="px-4 py-3 font-semibold">{{ $satir['Para Birimi'] }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format((int) ($satir['Fatura Adedi'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format((int) ($satir['Gelir Fatura Adedi'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format((int) ($satir['Gider Fatura Adedi'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right">{{ number_format((int) ($satir['Masraf Adedi'] ?? 0), 0, ',', '.') }}</td>
                                        <td class="px-4 py-3 text-right text-success-700 dark:text-success-400">{{ $para($satir['Gelir Toplam'] ?? '0.00', $satir['Para Birimi']) }}</td>
                                        <td class="px-4 py-3 text-right text-danger-700 dark:text-danger-400">{{ $para($satir['Gider Toplam'] ?? '0.00', $satir['Para Birimi']) }}</td>
                                        <td class="px-4 py-3 text-right font-semibold">{{ $para($satir['Net'] ?? '0.00', $satir['Para Birimi']) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>
            @endif

            <x-filament::section class="muhasebe-cork-card" collapsible>
                <x-slot name="heading">Hesaplama kapsamı</x-slot>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-600 dark:text-gray-400">
                    <li>Gelir: giden ve proforma faturalar.</li>
                    <li>Gider: gelen ve gider faturaları ile aktif hızlı masraf kayıtları.</li>
                    <li>Yalnızca onaylı faturalar ve aktif masraflar dikkate alınır; taslak, iptal ve bekleyen kayıtlar dahil edilmez.</li>
                    <li>Farklı para birimleri tek toplamda birleştirilmez.</li>
                </ul>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
