<x-filament-panels::page>
    <div class="muhasebe-cork-screen cork-sales-operations space-y-6">
        {{ $this->form }}

        <div class="grid gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-600 dark:text-gray-300">Kontrol edilen kayit</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format((int) ($ozet['kontrol_edilen'] ?? 0), 0, ',', '.') }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-600 dark:text-gray-300">Sorunlu kayit</div>
                <div class="mt-1 text-2xl font-semibold text-warning-600">{{ number_format((int) ($ozet['sorunlu_kayit'] ?? 0), 0, ',', '.') }}</div>
            </x-filament::section>

            <x-filament::section>
                <div class="text-sm text-gray-600 dark:text-gray-300">Toplam sorun</div>
                <div class="mt-1 text-2xl font-semibold {{ (int) ($ozet['toplam_sorun'] ?? 0) > 0 ? 'text-danger-600' : 'text-success-600' }}">
                    {{ number_format((int) ($ozet['toplam_sorun'] ?? 0), 0, ',', '.') }}
                </div>
            </x-filament::section>
        </div>

        @php
            $kodDagilimi = (array) ($ozet['kod_dagilimi'] ?? []);
        @endphp
        @if(count($kodDagilimi) > 0)
            <x-filament::section heading="Sorun dagilimi">
                <div class="flex flex-wrap gap-2">
                    @foreach($kodDagilimi as $kod => $adet)
                        <span class="inline-flex items-center rounded-lg border border-gray-200 px-3 py-1 text-xs dark:border-white/10">
                            {{ $kod }}: {{ $adet }}
                        </span>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        <x-filament::section heading="Mutabakat sorunlari">
            @if(count($sorunlar) === 0)
                <p class="text-sm text-success-700 dark:text-success-300">
                    Secili tarih araliginda mutabakat sorunu bulunamadi.
                </p>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Seviye</th>
                                <th class="px-3 py-2 text-left font-medium">Referans</th>
                                <th class="px-3 py-2 text-left font-medium">Iade No</th>
                                <th class="px-3 py-2 text-left font-medium">Satis No</th>
                                <th class="px-3 py-2 text-left font-medium">Tarih</th>
                                <th class="px-3 py-2 text-left font-medium">Cari</th>
                                <th class="px-3 py-2 text-left font-medium">Durum</th>
                                <th class="px-3 py-2 text-right font-medium">Beklenen</th>
                                <th class="px-3 py-2 text-right font-medium">Aktif Finans</th>
                                <th class="px-3 py-2 text-right font-medium">Adet</th>
                                <th class="px-3 py-2 text-left font-medium">Detay</th>
                                <th class="px-3 py-2 text-left font-medium">Islem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($sorunlar as $sorun)
                                @php
                                    $iadeUrl = $this->iadeGecmisiUrl($sorun);
                                @endphp
                                <tr class="hover:bg-gray-50/70 dark:hover:bg-white/5">
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs {{ ($sorun['seviye'] ?? '') === 'critical' ? 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300' }}">
                                            {{ strtoupper((string) ($sorun['seviye'] ?? 'info')) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 font-mono">{{ $sorun['referans_turu'] ?? 'barkodlu_satis' }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $sorun['iade_no'] ?? '-' }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $sorun['satis_no'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $sorun['satis_tarihi'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $sorun['cari'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $sorun['durum'] ?? '-' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $sorun['beklenen_tutar'] ?? '0.00' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $sorun['aktif_finans_toplami'] ?? '0.00' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $sorun['aktif_finans_adedi'] ?? 0 }}</td>
                                    <td class="px-3 py-2">{{ $sorun['detay'] ?? '-' }}</td>
                                    <td class="px-3 py-2">
                                        @if($iadeUrl)
                                            <a
                                                href="{{ $iadeUrl }}"
                                                class="inline-flex items-center rounded-md bg-primary-600 px-2 py-1 text-xs font-medium text-white hover:bg-primary-500"
                                            >
                                                Iadeye git
                                            </a>
                                        @else
                                            <span class="text-xs text-gray-500 dark:text-gray-400">-</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
