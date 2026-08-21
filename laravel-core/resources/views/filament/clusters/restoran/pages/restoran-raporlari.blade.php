@php
    $rapor = $this->rapor();
    $ozet = $rapor['ozet'] ?? [];
    $paket = $rapor['paket'] ?? [];
    $karlilik = $rapor['karlilik'] ?? [];
    $tutar = fn ($deger): string => number_format((float) $deger, 2, ',', '.').' TL';
@endphp

<x-filament-panels::page>
    <div class="restoran-cork-screen restoran-raporlari space-y-6">
        <div class="restoran-cork-toolbar grid gap-4 md:grid-cols-2">
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="baslangicTarihi" />
            </x-filament::input.wrapper>
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="bitisTarihi" />
            </x-filament::input.wrapper>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Adisyon</div>
                <div class="mt-1 text-2xl font-semibold">{{ $ozet['adisyon_sayisi'] ?? 0 }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Toplam</div>
                <div class="mt-1 text-2xl font-semibold">{{ $tutar($ozet['toplam_tutar'] ?? 0) }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Tahsil edilen</div>
                <div class="mt-1 text-2xl font-semibold">{{ $tutar($ozet['tahsil_edilen_tutar'] ?? 0) }}</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Paket siparis</div>
                <div class="mt-1 text-2xl font-semibold">{{ $paket['siparis_sayisi'] ?? 0 }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Teslim edilen</div>
                <div class="mt-1 text-2xl font-semibold">{{ $paket['teslim_edildi_sayisi'] ?? 0 }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Geciken</div>
                <div class="mt-1 text-2xl font-semibold">{{ $paket['geciken_sayisi'] ?? 0 }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Ort. teslimat</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format((float) ($paket['ortalama_teslimat_dakika'] ?? 0), 1, ',', '.') }} dk</div>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Satış tutarı</div>
                <div class="mt-1 text-2xl font-semibold">{{ $tutar($karlilik['satis_tutari'] ?? 0) }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Stok maliyeti</div>
                <div class="mt-1 text-2xl font-semibold">{{ $tutar($karlilik['stok_maliyeti'] ?? 0) }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Brüt kar</div>
                <div class="mt-1 text-2xl font-semibold">{{ $tutar($karlilik['brut_kar'] ?? 0) }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Brüt kar oranı</div>
                <div class="mt-1 text-2xl font-semibold">{{ number_format((float) ($karlilik['brut_kar_orani'] ?? 0), 2, ',', '.') }}%</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-4">
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Garson Performansı</h2>
                <div class="mt-4 space-y-3">
                    @forelse($rapor['garsonlar'] as $satir)
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span>#{{ $satir->garson_personel_id }}</span>
                            <span>{{ (int) $satir->adisyon_sayisi }} adisyon / {{ $tutar($satir->toplam_tutar) }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Kayıt yok.</div>
                    @endforelse
                </div>
            </div>

            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Kasiyer Performansı</h2>
                <div class="mt-4 space-y-3">
                    @forelse($rapor['kasiyerler'] as $satir)
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span>#{{ $satir->kasiyer_personel_id }}</span>
                            <span>{{ (int) $satir->tahsilat_sayisi }} tahsilat / {{ $tutar($satir->tahsilat_tutari) }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Kayıt yok.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Kurye Performansı</h2>
                <div class="mt-4 space-y-3">
                    @forelse($rapor['kuryeler'] as $satir)
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span>#{{ $satir->kurye_personel_id }}</span>
                            <span>{{ (int) $satir->teslimat_sayisi }} teslimat / {{ $tutar($satir->teslimat_tutari) }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Kayıt yok.</div>
                    @endforelse
                </div>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <h2 class="text-base font-semibold">Mutfak Performansı</h2>
                <div class="mt-4 space-y-3">
                    @forelse($rapor['mutfak'] as $satir)
                        <div class="flex items-center justify-between gap-4 text-sm">
                            <span>#{{ $satir->hazirlayan_personel_id }}</span>
                            <span>{{ (int) $satir->kalem_sayisi }} kalem / {{ $tutar($satir->toplam_tutar) }}</span>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">Kayıt yok.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold">Tahsilat Kanalları</h2>
            <div class="restoran-cork-table-wrap mt-4 overflow-x-auto">
                <table class="restoran-cork-table min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase text-gray-500 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3">Kanal</th>
                            <th class="px-4 py-3">İşlem</th>
                            <th class="px-4 py-3">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rapor['tahsilatlar'] as $satir)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ match((string) $satir->odeme_kanali) { 'kasa' => 'Kasa', 'banka' => 'Banka', 'pos' => 'POS', default => $satir->odeme_kanali ?: '-' } }}</td>
                                <td class="px-4 py-3">{{ (int) $satir->tahsilat_sayisi }}</td>
                                <td class="px-4 py-3">{{ number_format((float) $satir->toplam_tutar, 2, ',', '.') }} {{ strtoupper((string) ($satir->para_birimi ?: 'TRY')) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-8 text-center text-gray-500">Tahsilat kaydı yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold">En Çok Satan Ürünler</h2>
            <div class="restoran-cork-table-wrap mt-4 overflow-x-auto">
                <table class="restoran-cork-table min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase text-gray-500 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3">Ürün</th>
                            <th class="px-4 py-3">Miktar</th>
                            <th class="px-4 py-3">Satış</th>
                            <th class="px-4 py-3">İkram</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($rapor['urunler'] as $urun)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $urun->urun_adi }}</td>
                                <td class="px-4 py-3">{{ rtrim(rtrim((string) $urun->toplam_miktar, '0'), '.') }}</td>
                                <td class="px-4 py-3">{{ $tutar($urun->toplam_tutar) }}</td>
                                <td class="px-4 py-3">{{ $tutar($urun->ikram_tutari) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-4 py-8 text-center text-gray-500">Ürün satışı yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
