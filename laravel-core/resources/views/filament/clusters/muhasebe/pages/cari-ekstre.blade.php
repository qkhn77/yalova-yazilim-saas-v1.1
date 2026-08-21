<x-filament-panels::page>
    <div class="muhasebe-cork-screen muhasebe-cari-ekstre space-y-6">
        <div class="muhasebe-cork-toolbar">
            {{ $this->form }}
        </div>

        @if($rapor === null)
            <x-filament::section class="muhasebe-cork-card">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Cari ve dönemi seçip üstteki <strong>Raporu güncelle</strong> ile ekstreyi oluşturun.
                </p>
            </x-filament::section>
        @else
            @php
                $cari = \App\Models\Muhasebe\Cari::query()->find($this->form->getState()['cari_id'] ?? null);
                $paraBirimi = strtoupper((string) ($this->form->getState()['para_birimi'] ?? $cari?->para_birimi ?? 'TRY'));
                $alacakTakipOzeti = $cari
                    ? app(\App\Muhasebe\Servisler\CariAlacakTakipOzetServisi::class)->ozet($cari, $paraBirimi)
                    : null;
            @endphp

            <x-filament::section class="muhasebe-cork-card" heading="Özet">
                <dl class="muhasebe-cork-summary grid grid-cols-1 gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Cari</dt>
                        <dd class="font-medium text-gray-950 dark:text-white">{{ $cari?->ad ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Devreden bakiye (B−A)</dt>
                        <dd class="font-mono font-medium">{{ number_format(abs((float) $rapor['devreden']), 2, ',', '.') }} @if ((float) $rapor['devreden'] < 0)<span class="text-success-600">(Alacak)</span>@elseif ((float) $rapor['devreden'] > 0)<span class="text-danger-600">(Borç)</span>@endif</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Dönem toplam borç</dt>
                        <dd class="font-mono font-medium">{{ $rapor['toplam_borc'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Dönem toplam alacak</dt>
                        <dd class="font-mono font-medium">{{ $rapor['toplam_alacak'] }}</dd>
                    </div>
                    <div class="sm:col-span-2 lg:col-span-4">
                        <dt class="text-gray-500 dark:text-gray-400">Dönem sonu bakiye (B−A)</dt>
                        <dd class="text-lg font-semibold text-primary-600 dark:text-primary-400 font-mono">{{ number_format(abs((float) $rapor['guncel_bakiye']), 2, ',', '.') }} @if ((float) $rapor['guncel_bakiye'] < 0)<span class="text-success-600">(Alacak)</span>@elseif ((float) $rapor['guncel_bakiye'] > 0)<span class="text-danger-600">(Borç)</span>@endif</dd>
                    </div>
                </dl>
            </x-filament::section>

            @if($alacakTakipOzeti)
                <x-filament::section class="muhasebe-cork-card" heading="Vade ve Takip Özeti">
                    @include('filament.clusters.muhasebe.pages.partials.cari-alacak-takip-ozeti', [
                        'ozet' => $alacakTakipOzeti,
                        'vadeTakipUrl' => \App\Filament\Clusters\Muhasebe\Pages\VadeTakipSayfasi::getUrl(),
                    ])
                </x-filament::section>
            @endif

            <x-filament::section class="muhasebe-cork-card" heading="Hareketler">
                <div class="muhasebe-cork-table-wrap overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="muhasebe-cork-table w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Tarih</th>
                                <th class="px-3 py-2 text-left font-medium">İşlem</th>
                                <th class="px-3 py-2 text-right font-medium">Ref.</th>
                                <th class="px-3 py-2 text-right font-medium">Borç</th>
                                <th class="px-3 py-2 text-right font-medium">Alacak</th>
                                <th class="px-3 py-2 text-right font-medium">Net</th>
                                <th class="px-3 py-2 text-center font-medium">Açık / Kapalı</th>
                                <th class="px-3 py-2 text-right font-medium">Kalan (FIFO)</th>
                                <th class="px-3 py-2 text-right font-medium">Bakiye</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse($rapor['satirlar'] as $satir)
                                @php $h = $satir['hareket']; @endphp
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                    <td class="px-3 py-2 whitespace-nowrap">{{ $h->islem_tarihi->format('d.m.Y H:i') }}</td>
                                    <td class="px-3 py-2">{{ $h->belge_turu->etiket() }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $h->belge_id }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $h->borc }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $h->alacak }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format(abs((float) $satir['net']), 2, ',', '.') }} @if ((float) $satir['net'] < 0)<span class="text-success-600">(Alacak)</span>@elseif ((float) $satir['net'] > 0)<span class="text-danger-600">(Borç)</span>@endif</td>
                                    <td class="px-3 py-2 text-center">
                                        @if($satir['fifo_acik'])
                                            <span class="muhasebe-cork-badge inline-flex items-center rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800 ring-1 ring-inset ring-amber-600/20 dark:bg-amber-400/10 dark:text-amber-300 dark:ring-amber-400/30">Açık</span>
                                        @else
                                            <span class="muhasebe-cork-badge inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 ring-1 ring-inset ring-gray-500/10 dark:bg-gray-500/10 dark:text-gray-300 dark:ring-gray-400/20">Kapalı</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono text-xs sm:text-sm">{{ $satir['kalan_tutar'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-semibold">{{ number_format(abs((float) $satir['bakiye_sonrasi']), 2, ',', '.') }} @if ((float) $satir['bakiye_sonrasi'] < 0)<span class="text-success-600">(Alacak)</span>@elseif ((float) $satir['bakiye_sonrasi'] > 0)<span class="text-danger-600">(Borç)</span>@endif</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="muhasebe-cork-empty px-3 py-6 text-center text-gray-500">Bu dönemde hareket yok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
