<x-filament-panels::page>
    @if(!$iade)
        <div class="rounded-lg border border-gray-200 bg-white p-4 text-sm text-gray-700 shadow-sm dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200">
            <h2 class="font-semibold">Iade kaydi bulunamadi</h2>
            <p class="mt-1">Gecersiz kayit secimi yapildi.</p>
        </div>
    @else
        <style>
            @media print {
                body * {
                    visibility: hidden;
                }
                #iade-fisi,
                #iade-fisi * {
                    visibility: visible;
                }
                #iade-fisi {
                    position: absolute;
                    inset: 0;
                    margin: 0;
                    padding: 16px;
                }
            }
        </style>

        <div class="mb-4">
            <x-filament::button color="success" icon="heroicon-o-printer" onclick="window.print()">
                Fisi Yazdir
            </x-filament::button>
        </div>

        <div id="iade-fisi" class="space-y-4 rounded-lg border border-gray-300 bg-white p-4 text-black">
            <div class="flex items-start justify-between">
                <div>
                    <div class="mb-2 flex items-center gap-3">
                        @if(filled($firmaLogoUrl))
                            <img src="{{ $firmaLogoUrl }}" alt="Firma logosu" class="h-14 w-auto object-contain" />
                        @endif
                        <div>
                            <h2 class="text-lg font-bold">{{ filled($firmaUnvan) ? $firmaUnvan : 'Barkodlu Satis Iade Fisi' }}</h2>
                            @if(filled($firmaTelefon) || filled($firmaEposta))
                                <p class="text-xs text-gray-700">{{ trim(($firmaTelefon ? $firmaTelefon : '').' '.($firmaEposta ? '| '.$firmaEposta : '')) }}</p>
                            @endif
                        </div>
                    </div>
                    <h3 class="text-base font-semibold">Barkodlu Satis Iade Fisi</h3>
                    <p class="text-sm">Iade No: {{ $iade->iade_no }}</p>
                    <p class="text-sm">Dogrulama Kodu: {{ $iade->dogrulama_kodu ?: '-' }}</p>
                    <p class="text-sm">Iade Tarihi: {{ optional($iade->iade_tarihi)->format('d.m.Y H:i') }}</p>
                    <p class="text-sm">Satis No: {{ $iade->satis?->satis_no ?? '-' }}</p>
                </div>
                <div class="text-right text-sm">
                    <p>Cari: {{ $iade->satis?->cari?->ad ?? '-' }}</p>
                    <p>Olusturan: {{ $iade->olusturan?->name ?? '-' }}</p>
                    @if(filled($qrSvg))
                        <div class="mt-2 inline-flex flex-col items-center rounded border border-gray-300 p-1">
                            <div class="h-24 w-24 [&>svg]:h-full [&>svg]:w-full">{!! $qrSvg !!}</div>
                            <span class="mt-1 text-[10px]">QR ile dogrula</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="rounded border px-2 py-1 text-xs {{ $dogrulamaBasarili ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-amber-500 bg-amber-50 text-amber-700' }}">
                {{ $dogrulamaBasarili ? 'Dogrulama basarili: Kod eslesmesi saglandi.' : 'Dogrulama bilgisi: Bu fisin QR kodu veya dogrulama kodu ile kontrol edilebilir.' }}
            </div>

            @if(filled($firmaAdres))
                <p class="text-xs text-gray-700">{{ $firmaAdres }}</p>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full border border-gray-300 text-sm">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="border border-gray-300 px-2 py-1 text-left">Urun</th>
                            <th class="border border-gray-300 px-2 py-1 text-right">Miktar</th>
                            <th class="border border-gray-300 px-2 py-1 text-right">Birim Fiyat</th>
                            <th class="border border-gray-300 px-2 py-1 text-right">KDV</th>
                            <th class="border border-gray-300 px-2 py-1 text-right">Toplam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($iade->kalemler as $kalem)
                            <tr>
                                <td class="border border-gray-300 px-2 py-1">
                                    {{ $kalem->satisKalemi?->stok_adi ?? '-' }}
                                    @php
                                        $partiler = [];
                                        if (filled($kalem->parti_no ?? null)) {
                                            $partiler[] = (string) $kalem->parti_no;
                                        }
                                        foreach ((array) ($kalem->parti_dagilimi ?? []) as $parti) {
                                            if (is_array($parti) && filled($parti['parti_no'] ?? null)) {
                                                $partiler[] = (string) $parti['parti_no'];
                                            }
                                        }
                                        $seriler = array_values(array_filter(array_map('trim', (array) ($kalem->seri_nolari ?? []))));
                                    @endphp
                                    @if ($partiler !== [])
                                        <div class="text-xs text-gray-600">Parti / Lot: {{ implode(', ', array_unique($partiler)) }}</div>
                                    @endif
                                    @if ($seriler !== [])
                                        <div class="text-xs text-gray-600">Seri No Barkodu: {{ implode(', ', array_unique($seriler)) }}</div>
                                    @endif
                                </td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ number_format((float) $kalem->miktar, 2, ',', '.') }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ number_format((float) $kalem->birim_fiyat, 2, ',', '.') }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ number_format((float) $kalem->kdv_tutari, 2, ',', '.') }}</td>
                                <td class="border border-gray-300 px-2 py-1 text-right">{{ number_format((float) $kalem->satir_toplami, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <div class="text-right text-sm">
                    <p class="font-semibold">
                        Toplam Iade:
                        {{ number_format((float) $iade->toplam_iade_tutari, 2, ',', '.') }}
                        {{ strtoupper((string) ($iade->satis?->para_birimi ?? 'TRY')) }}
                    </p>
                </div>
            </div>

            @if(filled($iade->neden))
                <div class="rounded border border-gray-300 bg-gray-50 p-2 text-sm">
                    <span class="font-semibold">Iade Nedeni:</span> {{ $iade->neden }}
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
