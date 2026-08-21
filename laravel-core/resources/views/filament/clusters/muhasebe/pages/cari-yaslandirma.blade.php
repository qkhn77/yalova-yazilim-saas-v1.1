<x-filament-panels::page>
    <div class="muhasebe-cork-screen muhasebe-cari-yaslandirma space-y-6">
        <div class="muhasebe-cork-toolbar">
            {{ $this->form }}
        </div>

        @if(count($satirlar) === 0)
            <x-filament::section class="muhasebe-cork-card">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Aktif firma bağlamı yoksa veya cari bulunmuyorsa liste boş görünür.
                </p>
            </x-filament::section>
        @else
            <x-filament::section class="muhasebe-cork-card" heading="Yaşlandırma">
                <div class="muhasebe-cork-table-wrap overflow-x-auto rounded-xl border border-gray-200 dark:border-white/10">
                    <table class="muhasebe-cork-table w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-3 py-2 text-left font-medium">Cari</th>
                                <th class="px-3 py-2 text-right font-medium">Güncel bakiye</th>
                                <th class="px-3 py-2 text-right font-medium">Vadesi gelmemiş (net)</th>
                                <th class="px-3 py-2 text-right font-medium">1–30 gün</th>
                                <th class="px-3 py-2 text-right font-medium">31–60 gün</th>
                                <th class="px-3 py-2 text-right font-medium">61–90 gün</th>
                                <th class="px-3 py-2 text-right font-medium">90+ gün</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach($satirlar as $s)
                                <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                    <td class="px-3 py-2">{{ $s['unvan'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $s['guncel_bakiye'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $s['vadesi_gelmemis_net'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $s['gun_0_30'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $s['gun_30_60'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $s['gun_60_90'] }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ $s['gun_90_arti'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
