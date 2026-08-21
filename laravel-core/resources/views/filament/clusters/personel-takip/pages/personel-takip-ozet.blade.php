<x-filament-panels::page>
    @php
        $kpi = $this->kpi();
        $kartlar = [
            ['baslik' => 'Aktif personel', 'deger' => $kpi['aktif_personel'] ?? 0],
            ['baslik' => 'Bugünkü vardiya', 'deger' => $kpi['bugun_vardiya'] ?? 0],
            ['baslik' => 'Açık giriş', 'deger' => $kpi['acik_giris'] ?? 0],
            ['baslik' => 'Bekleyen izin', 'deger' => $kpi['bekleyen_izin'] ?? 0],
            ['baslik' => 'Bekleyen avans', 'deger' => $kpi['bekleyen_avans'] ?? 0],
            ['baslik' => 'Açık maaş dönemi', 'deger' => $kpi['acik_maas_donemi'] ?? 0],
            ['baslik' => 'Yenilenecek belge', 'deger' => $kpi['yenilenecek_belge'] ?? 0],
        ];
    @endphp

    <div class="personel-cork-screen">
    <div class="personel-cork-kpi-grid grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($kartlar as $kart)
            <div class="personel-cork-kpi-card rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $kart['baslik'] }}</div>
                <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ $kart['deger'] }}</div>
            </div>
        @endforeach
    </div>

    <div class="personel-cork-kpi-card mt-4 rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Açık avans tutarı</div>
        <div class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">
            {{ number_format((float) ($kpi['acik_avans_tutari'] ?? 0), 2, ',', '.') }} TL
        </div>
    </div>
    </div>
</x-filament-panels::page>
