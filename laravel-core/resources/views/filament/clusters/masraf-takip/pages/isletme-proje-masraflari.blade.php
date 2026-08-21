@php
    /** @var \App\Models\Proje\IsletmeProjesi $proje */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Muhasebe\Masraf> $masraflar */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Muhasebe\Fatura> $faturalar */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Muhasebe\FinansHareketi> $finansHareketleri */
    /** @var \Illuminate\Support\Collection<int, \App\Models\Muhasebe\CariHareketi> $cariHareketleri */
    /** @var array<int, array{para_birimi:string, gelir:string, odeme:string, masraf:string, net:string}> $finansOzetleri */
@endphp

<div class="space-y-4">
    @if ($finansOzetleri !== [])
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($finansOzetleri as $ozet)
                <div class="rounded-xl border border-gray-200 p-3 dark:border-gray-700">
                    <div class="text-xs font-medium uppercase text-gray-500">{{ $ozet['para_birimi'] }} özeti</div>
                    <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                        <div><span class="text-gray-500">Gelir</span><div class="font-semibold text-success-600">{{ number_format((float) $ozet['gelir'], 8, ',', '.') }}</div></div>
                        <div><span class="text-gray-500">Ödeme</span><div class="font-semibold">{{ number_format((float) $ozet['odeme'], 8, ',', '.') }}</div></div>
                        <div><span class="text-gray-500">Masraf</span><div class="font-semibold">{{ number_format((float) $ozet['masraf'], 8, ',', '.') }}</div></div>
                        <div><span class="text-gray-500">Net</span><div class="font-semibold {{ str_starts_with($ozet['net'], '-') ? 'text-danger-600' : 'text-success-600' }}">{{ number_format((float) $ozet['net'], 8, ',', '.') }}</div></div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
    @if ($masraflar->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Bu projeye bağlı aktif veya iptal edilmiş masraf bulunamadı.</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Tarih</th>
                        <th class="px-4 py-3">Masraf türü</th>
                        <th class="px-4 py-3 text-right">Tutar</th>
                        <th class="px-4 py-3">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($masraflar as $masraf)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3">{{ $masraf->tarih?->format('d.m.Y') ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $masraf->kategori?->ad ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3 text-right font-medium">
                                {{ number_format((float) $masraf->tutar, 8, ',', '.') }} {{ strtoupper($masraf->para_birimi ?: $proje->para_birimi) }}
                            </td>
                            <td class="px-4 py-3">
                                <x-filament::badge :color="$masraf->durum === \App\Models\Muhasebe\Masraf::DURUM_AKTIF ? 'success' : 'gray'">
                                    {{ $masraf->durum === \App\Models\Muhasebe\Masraf::DURUM_AKTIF ? 'Aktif' : 'İptal' }}
                                </x-filament::badge>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($masraflar->count() === 50)
            <p class="text-xs text-gray-500 dark:text-gray-400">En son 50 masraf gösteriliyor. Tüm kayıtlar için Masraf Takibi listesini kullanabilirsiniz.</p>
        @endif
    @endif

    @if ($faturalar->isNotEmpty())
        <div>
            <h3 class="mb-2 text-sm font-semibold">Faturalar</h3>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr><th class="px-4 py-3">Tarih</th><th class="px-4 py-3">Fatura</th><th class="px-4 py-3">Cari</th><th class="px-4 py-3 text-right">Tutar</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($faturalar as $fatura)
                            <tr>
                                <td class="px-4 py-3">{{ $fatura->tarih?->format('d.m.Y') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $fatura->fatura_no ?: '#'.$fatura->id }}</td>
                                <td class="px-4 py-3">{{ $fatura->cari?->ad ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $fatura->genel_toplam, 8, ',', '.') }} {{ strtoupper($fatura->para_birimi ?: 'TRY') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($finansHareketleri->isNotEmpty())
        <div>
            <h3 class="mb-2 text-sm font-semibold">Finans hareketleri</h3>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr><th class="px-4 py-3">Tarih</th><th class="px-4 py-3">Tür</th><th class="px-4 py-3">Cari</th><th class="px-4 py-3 text-right">Tutar</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($finansHareketleri as $hareket)
                            <tr>
                                <td class="px-4 py-3">{{ $hareket->tarih?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $hareket->tur?->value ?? $hareket->tur }}</td>
                                <td class="px-4 py-3">{{ $hareket->cari?->ad ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $hareket->tutar, 8, ',', '.') }} {{ strtoupper($hareket->para_birimi ?: 'TRY') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($cariHareketleri->isNotEmpty())
        <div>
            <h3 class="mb-2 text-sm font-semibold">Cari hareketleri</h3>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr><th class="px-4 py-3">Tarih</th><th class="px-4 py-3">Cari</th><th class="px-4 py-3 text-right">Borç</th><th class="px-4 py-3 text-right">Alacak</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach ($cariHareketleri as $hareket)
                            <tr>
                                <td class="px-4 py-3">{{ $hareket->islem_tarihi?->format('d.m.Y H:i') ?? '—' }}</td>
                                <td class="px-4 py-3">{{ $hareket->cari?->ad ?? '—' }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $hareket->borc, 8, ',', '.') }} {{ strtoupper($hareket->para_birimi ?: 'TRY') }}</td>
                                <td class="px-4 py-3 text-right">{{ number_format((float) $hareket->alacak, 8, ',', '.') }} {{ strtoupper($hareket->para_birimi ?: 'TRY') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($masraflar->isEmpty() && $faturalar->isEmpty() && $finansHareketleri->isEmpty() && $cariHareketleri->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">Bu projeye henüz finansal hareket bağlanmamış.</p>
    @endif
</div>
