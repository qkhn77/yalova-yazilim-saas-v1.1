<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <x-filament::section heading="Depo bazlı stok bakiyesi" description="Her depo için fiziksel, rezerve ve kullanılabilir stok miktarını gösterir.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b text-left">
                            <th class="px-3 py-2">Depo</th>
                            <th class="px-3 py-2">Stok</th>
                            <th class="px-3 py-2">Birim</th>
                            <th class="px-3 py-2 text-right">Fiziksel</th>
                            <th class="px-3 py-2 text-right">Rezerve</th>
                            <th class="px-3 py-2 text-right">Kullanılabilir</th>
                            <th class="px-3 py-2 text-right">Birim maliyet</th>
                            <th class="px-3 py-2 text-right">Stok değeri</th>
                            <th class="px-3 py-2 text-right">Toplam m²</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->bakiyeler as $bakiye)
                            @php
                                $miktar = (float) $bakiye->miktar;
                                $rezerve = (float) $bakiye->rezerve_miktar;
                                $maliyet = (float) ($bakiye->stokKarti?->guncel_birim_maliyet ?? 0);
                                $birimMetrekare = $bakiye->stokKarti?->birimMetrekare();
                                $toplamMetrekare = $birimMetrekare !== null ? $birimMetrekare * $miktar : null;
                            @endphp
                            <tr class="border-b last:border-0">
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $bakiye->depo?->ad ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $bakiye->depo?->kod }}</div>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="font-medium">{{ $bakiye->stokKarti?->ad ?? '-' }}</div>
                                    <div class="text-xs text-gray-500">{{ $bakiye->stokKarti?->kod }}</div>
                                </td>
                                <td class="px-3 py-2">{{ $bakiye->stokKarti?->birim ?? '-' }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($miktar, 4, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($rezerve, 4, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ number_format($miktar - $rezerve, 4, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($maliyet, 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right font-semibold">{{ number_format($miktar * $maliyet, 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right">{{ $toplamMetrekare !== null ? number_format($toplamMetrekare, 4, ',', '.') : '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="px-3 py-8 text-center text-gray-500">Seçilen ölçütlere göre depo bakiyesi bulunamadı.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
