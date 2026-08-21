@php
    $rapor = $this->rapor();
    $kpi = $rapor['kpi'] ?? [];
    $performans = $rapor['personel_performansi'] ?? [];
    $restoran = $rapor['restoran_performansi'] ?? [];
    $teknikServis = $rapor['teknik_servis_performansi'] ?? [];
    $dakika = fn (int|float|null $v): string => floor(((int) $v) / 60).' sa '.(((int) $v) % 60).' dk';
    $tutar = fn (int|float|null $v): string => number_format((float) ($v ?? 0), 2, ',', '.').' TRY';
@endphp

<x-filament-panels::page>
    <div class="personel-cork-screen">
    <form wire:submit.prevent="$refresh" class="personel-cork-toolbar mb-6 grid gap-3 md:grid-cols-4">
        <x-filament::input.wrapper>
            <x-filament::input type="date" wire:model.defer="baslangic_tarihi" />
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input type="date" wire:model.defer="bitis_tarihi" />
        </x-filament::input.wrapper>

        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.defer="sube_id">
                <option value="">Tüm şubeler</option>
                @foreach($this->subeSecenekleri() as $id => $ad)
                    <option value="{{ $id }}">{{ $ad }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>

        <x-filament::button type="submit" icon="heroicon-o-arrow-path">
            Raporu güncelle
        </x-filament::button>

        <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="csvIndir">
            CSV indir
        </x-filament::button>
    </form>

    <div class="personel-cork-kpi-grid grid gap-4 md:grid-cols-4">
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Aktif personel</div>
            <div class="mt-1 text-2xl font-semibold">{{ $kpi['aktif_personel'] ?? 0 }}</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Planlı vardiya</div>
            <div class="mt-1 text-2xl font-semibold">{{ $kpi['planli_vardiya'] ?? 0 }}</div>
            <div class="mt-1 text-sm text-gray-500">{{ $dakika($kpi['planli_calisma_dakika'] ?? 0) }}</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Fiili çalışma</div>
            <div class="mt-1 text-2xl font-semibold">{{ $dakika($kpi['fiili_calisma_dakika'] ?? 0) }}</div>
            <div class="mt-1 text-sm text-gray-500">{{ $kpi['onayli_giris_cikis'] ?? 0 }} kayıt</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Fazla mesai</div>
            <div class="mt-1 text-2xl font-semibold">{{ $dakika($kpi['fazla_mesai_dakika'] ?? 0) }}</div>
        </div>
    </div>

    <div class="personel-cork-kpi-grid mt-4 grid gap-4 md:grid-cols-4">
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Geç kalma</div>
            <div class="mt-1 text-2xl font-semibold">{{ $dakika($kpi['gec_kalma_dakika'] ?? 0) }}</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">İzin</div>
            <div class="mt-1 text-2xl font-semibold">{{ number_format((float) ($kpi['izin_gun'] ?? 0), 2, ',', '.') }} gün</div>
            <div class="mt-1 text-sm text-gray-500">{{ number_format((float) ($kpi['izin_saat'] ?? 0), 2, ',', '.') }} saat</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Açık avans</div>
            <div class="mt-1 text-2xl font-semibold">{{ $tutar($kpi['acik_avans'] ?? 0) }}</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Maaş kalan</div>
            <div class="mt-1 text-2xl font-semibold">{{ $tutar($kpi['maas_kalan'] ?? 0) }}</div>
            <div class="mt-1 text-sm text-gray-500">Net {{ $tutar($kpi['maas_net'] ?? 0) }}</div>
        </div>
    </div>

    <div class="personel-cork-kpi-grid mt-4 grid gap-4 md:grid-cols-3">
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Restoran adisyon</div>
            <div class="mt-1 text-2xl font-semibold">{{ $kpi['restoran_adisyon'] ?? 0 }}</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Restoran ciro</div>
            <div class="mt-1 text-2xl font-semibold">{{ $tutar($kpi['restoran_ciro'] ?? 0) }}</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Mutfak kalem</div>
            <div class="mt-1 text-2xl font-semibold">{{ $kpi['restoran_mutfak_kalem'] ?? 0 }}</div>
        </div>
    </div>

    <div class="personel-cork-kpi-grid mt-4 grid gap-4 md:grid-cols-2">
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Teknik servis görev</div>
            <div class="mt-1 text-2xl font-semibold">{{ $kpi['teknik_servis_gorev'] ?? 0 }}</div>
        </div>
        <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-sm text-gray-500">Tamamlanan görev</div>
            <div class="mt-1 text-2xl font-semibold">{{ $kpi['teknik_servis_tamamlanan_gorev'] ?? 0 }}</div>
        </div>
    </div>

    <div class="personel-cork-table-wrap mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">
            Personel performansı
        </div>
        <div class="overflow-x-auto">
            <table class="personel-cork-table w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-left text-gray-500 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3 font-medium">Personel</th>
                        <th class="px-4 py-3 font-medium">Kayıt</th>
                        <th class="px-4 py-3 font-medium">Çalışma</th>
                        <th class="px-4 py-3 font-medium">Fazla mesai</th>
                        <th class="px-4 py-3 font-medium">Geç kalma</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($performans as $satir)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $satir['ad_soyad'] }}</td>
                            <td class="px-4 py-3">{{ $satir['giris_cikis_sayisi'] }}</td>
                            <td class="px-4 py-3">{{ $dakika($satir['calisma_dakika']) }}</td>
                            <td class="px-4 py-3">{{ $dakika($satir['fazla_mesai_dakika']) }}</td>
                            <td class="px-4 py-3">{{ $dakika($satir['gec_kalma_dakika']) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="personel-cork-empty px-4 py-6 text-center text-gray-500">Bu aralıkta onaylı giriş-çıkış kaydı yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-6 grid gap-4 lg:grid-cols-3">
        <div class="personel-cork-table-wrap overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">
                Garson performansı
            </div>
            <div class="overflow-x-auto">
                <table class="personel-cork-table w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-gray-500 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3 font-medium">Personel</th>
                            <th class="px-4 py-3 font-medium">Adisyon</th>
                            <th class="px-4 py-3 font-medium">Ciro</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse(($restoran['garsonlar'] ?? []) as $satir)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $satir['ad_soyad'] }}</td>
                                <td class="px-4 py-3">{{ $satir['adisyon_sayisi'] }}</td>
                                <td class="px-4 py-3">{{ $tutar($satir['ciro']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500">Restoran garson verisi yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="personel-cork-table-wrap overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">
                Kasiyer performansı
            </div>
            <div class="overflow-x-auto">
                <table class="personel-cork-table w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-gray-500 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3 font-medium">Personel</th>
                            <th class="px-4 py-3 font-medium">Adisyon</th>
                            <th class="px-4 py-3 font-medium">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse(($restoran['kasiyerler'] ?? []) as $satir)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $satir['ad_soyad'] }}</td>
                                <td class="px-4 py-3">{{ $satir['adisyon_sayisi'] }}</td>
                                <td class="px-4 py-3">{{ $tutar($satir['ciro']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500">Restoran kasiyer verisi yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="personel-cork-table-wrap overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">
                Mutfak performansı
            </div>
            <div class="overflow-x-auto">
                <table class="personel-cork-table w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-gray-500 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3 font-medium">Personel</th>
                            <th class="px-4 py-3 font-medium">Kalem</th>
                            <th class="px-4 py-3 font-medium">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse(($restoran['mutfak'] ?? []) as $satir)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $satir['ad_soyad'] }}</td>
                                <td class="px-4 py-3">{{ $satir['kalem_sayisi'] }}</td>
                                <td class="px-4 py-3">{{ $tutar($satir['toplam_tutar']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-4 py-6 text-center text-gray-500">Restoran mutfak verisi yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="personel-cork-table-wrap mt-6 overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-4 py-3 text-sm font-semibold dark:border-gray-800">
            Teknik servis personel görevleri
        </div>
        <div class="overflow-x-auto">
            <table class="personel-cork-table w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-left text-gray-500 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3 font-medium">Personel</th>
                        <th class="px-4 py-3 font-medium">Görev</th>
                        <th class="px-4 py-3 font-medium">Aktif</th>
                        <th class="px-4 py-3 font-medium">Tamamlanan</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse(($teknikServis['personeller'] ?? []) as $satir)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $satir['ad_soyad'] }}</td>
                            <td class="px-4 py-3">{{ $satir['gorev_sayisi'] }}</td>
                            <td class="px-4 py-3">{{ $satir['aktif_gorev_sayisi'] }}</td>
                            <td class="px-4 py-3">{{ $satir['tamamlanan_gorev_sayisi'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="personel-cork-empty px-4 py-6 text-center text-gray-500">Teknik servis personel görev verisi yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
</x-filament-panels::page>
