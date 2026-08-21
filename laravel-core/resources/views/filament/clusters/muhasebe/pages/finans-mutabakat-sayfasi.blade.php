<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 md:grid-cols-3">
            <x-filament::section>
                <div class="text-sm text-gray-600 dark:text-gray-300">Bulgu sayısı</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format($kontrolEdilen, 0, ',', '.') }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-600 dark:text-gray-300">Kritik bulgu</div>
                <div class="mt-1 text-2xl font-semibold text-danger-600">{{ number_format(collect($sorunlar)->where('seviye', 'kritik')->count(), 0, ',', '.') }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="text-sm text-gray-600 dark:text-gray-300">Toplam bulgu</div>
                <div class="mt-1 text-2xl font-semibold {{ count($sorunlar) ? 'text-warning-600' : 'text-success-600' }}">{{ number_format(count($sorunlar), 0, ',', '.') }}</div>
            </x-filament::section>
        </div>

        <x-filament::section heading="Mutabakat bulguları">
            @if(count($sorunlar) === 0)
                <p class="text-sm text-success-700 dark:text-success-300">Aktif firma için finans mutabakat sorunu bulunamadı.</p>
            @else
                <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left">Seviye</th>
                                <th class="px-3 py-2 text-left">Kod</th>
                                <th class="px-3 py-2 text-left">Kaynak ID</th>
                                <th class="px-3 py-2 text-left">Açıklama</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($sorunlar as $sorun)
                                <tr>
                                    <td class="px-3 py-2">
                                        <span class="inline-flex rounded px-2 py-0.5 text-xs {{ ($sorun['seviye'] ?? '') === 'kritik' ? 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300' : 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300' }}">
                                            {{ strtoupper((string) ($sorun['seviye'] ?? 'uyarı')) }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 font-mono">{{ $sorun['kod'] ?? '-' }}</td>
                                    <td class="px-3 py-2 font-mono">{{ $sorun['kaynak_id'] ?? '-' }}</td>
                                    <td class="px-3 py-2">{{ $sorun['detay'] ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Bu ekran otomatik düzeltme yapmaz. Düzeltme, ilgili kaynak modülün iptal veya yeniden oluşturma servisi üzerinden yapılmalıdır.</p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
