<x-filament-panels::page>
    @php($para = static fn (mixed $tutar, string $birim): string => number_format((float) $tutar, 8, ',', '.').' '.strtoupper($birim))

    <div class="mx-auto w-full max-w-4xl">
        <x-filament::section>
            <x-slot name="heading">Hareket bilgileri</x-slot>
            <div class="grid gap-4 sm:grid-cols-2">
                <div><div class="text-sm text-gray-500">Hareket türü</div><div class="font-medium">{{ $hareket['hareket_turu'] }}</div></div>
                <div><div class="text-sm text-gray-500">Tarih</div><div class="font-medium">{{ date('d.m.Y H:i', strtotime((string) $hareket['tarih'])) }}</div></div>
                <div><div class="text-sm text-gray-500">Proje</div><div class="font-medium">{{ $hareket['proje_kodu'] }} — {{ $hareket['proje'] }}</div></div>
                <div><div class="text-sm text-gray-500">Belge / kayıt</div><div class="font-medium">{{ $hareket['belge'] }}</div></div>
                <div><div class="text-sm text-gray-500">Yön</div><div class="font-medium">{{ $hareket['yon'] }}</div></div>
                <div><div class="text-sm text-gray-500">Durum</div><div class="font-medium">{{ $hareket['durum'] }}</div></div>
                <div><div class="text-sm text-gray-500">Miktar</div><div class="font-medium">{{ $hareket['miktar'] ?? '—' }}</div></div>
                <div><div class="text-sm text-gray-500">Tutar</div><div class="font-semibold">{{ $para($hareket['tutar'], $hareket['para_birimi']) }}</div></div>
                @if (filled($hareket['tur']))
                    <div><div class="text-sm text-gray-500">Alt tür</div><div class="font-medium">{{ $hareket['tur'] }}</div></div>
                @endif
                @if (filled($hareket['referans_turu']) || filled($hareket['referans_id']))
                    <div><div class="text-sm text-gray-500">Referans</div><div class="font-medium">{{ $hareket['referans_turu'] ?? '—' }} #{{ $hareket['referans_id'] ?? '—' }}</div></div>
                @endif
            </div>
            @if (filled($hareket['aciklama']))
                <div class="mt-6 border-t border-gray-200 pt-4 dark:border-white/10">
                    <div class="text-sm text-gray-500">Açıklama</div>
                    <div class="mt-1 whitespace-pre-wrap">{{ $hareket['aciklama'] }}</div>
                </div>
            @endif
        </x-filament::section>

        @if (filled($hareket['baglantilar'] ?? []))
            <x-filament::section class="mt-4">
                <x-slot name="heading">Bağlı kayıtlar</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left dark:bg-white/5">
                            <tr><th class="px-3 py-2">Kayıt</th><th class="px-3 py-2">Tür</th><th class="px-3 py-2 text-right">Tutar</th><th class="px-3 py-2 text-center">İşlem</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($hareket['baglantilar'] as $baglanti)
                                <tr>
                                    <td class="px-3 py-2">{{ $baglanti['etiket'] }}</td>
                                    <td class="px-3 py-2">{{ ucfirst($baglanti['tur']) }}</td>
                                    <td class="px-3 py-2 text-right">{{ $baglanti['tutar'] === null ? '—' : $para($baglanti['tutar'], $baglanti['para_birimi']) }}</td>
                                    <td class="px-3 py-2 text-center"><x-filament::icon-button tag="a" :href="$baglanti['url']" icon="heroicon-o-eye" label="Bağlı kaydı görüntüle" color="gray" /></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
