@php
    /** @var \App\Filament\Clusters\Muhasebe\Pages\IsAnalitigiSayfasi $this */
    $payload = $this->analiz();
    $fmt = static function (string $tutar, string $pb): string {
        return number_format((float) $tutar, 2, ',', '.').' '.$pb;
    };
@endphp

<x-filament-panels::page>
    @if (($payload['firma_id'] ?? null) === null)
        <x-filament::section>
            <x-slot name="heading">Aktif firma yok</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-400">Analiz verisi icin aktif firma secin.</p>
        </x-filament::section>
    @else
        @php
            $data = $payload['data'];
            $kpi = $data['kpi'];
            $ops = $data['operasyon'];
            $trend7 = $data['trend']['siparis_7'] ?? [];
            $maxSiparis = max(array_map(fn ($x) => (int) ($x['adet'] ?? 0), $trend7) ?: [1]);
        @endphp

        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-xs uppercase tracking-wide text-gray-500">Bugun siparis</p>
                <p class="mt-1 text-xl font-semibold">{{ (int) $kpi['bugun_siparis'] }}</p>
                <p class="text-xs text-gray-500">Hafta: {{ (int) $kpi['hafta_siparis'] }} | Ay: {{ (int) $kpi['ay_siparis'] }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-xs uppercase tracking-wide text-gray-500">Odeme basari orani</p>
                <p class="mt-1 text-xl font-semibold">%{{ number_format((float) $kpi['odeme_basarili_orani'], 2, ',', '.') }}</p>
                <p class="text-xs text-gray-500">Basarisiz: %{{ number_format((float) $kpi['odeme_basarisiz_orani'], 2, ',', '.') }}</p>
            </div>
            <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <p class="text-xs uppercase tracking-wide text-gray-500">Iptal orani (ay)</p>
                <p class="mt-1 text-xl font-semibold">%{{ number_format((float) $kpi['iptal_orani'], 2, ',', '.') }}</p>
            </div>
        </div>

        <x-filament::section class="mt-6">
            <x-slot name="heading">Ciro (para birimi bazli)</x-slot>
            <x-slot name="description">Karışık para biriminde yanlış toplama yapmamak için KPI'lar para birimi kırılımında gösterilir.</x-slot>
            <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs font-medium text-gray-500">Bugun</p>
                    @forelse (($kpi['bugun_ciro_pb'] ?? []) as $pb => $tutar)
                        <p class="text-sm">{{ $fmt((string) $tutar, (string) $pb) }}</p>
                    @empty
                        <p class="text-sm text-gray-500">Kayit yok</p>
                    @endforelse
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs font-medium text-gray-500">Hafta</p>
                    @forelse (($kpi['hafta_ciro_pb'] ?? []) as $pb => $tutar)
                        <p class="text-sm">{{ $fmt((string) $tutar, (string) $pb) }}</p>
                    @empty
                        <p class="text-sm text-gray-500">Kayit yok</p>
                    @endforelse
                </div>
                <div class="rounded-lg border border-gray-200 p-3 dark:border-white/10">
                    <p class="text-xs font-medium text-gray-500">Ay</p>
                    @forelse (($kpi['ay_ciro_pb'] ?? []) as $pb => $tutar)
                        <p class="text-sm">{{ $fmt((string) $tutar, (string) $pb) }}</p>
                    @empty
                        <p class="text-sm text-gray-500">Kayit yok</p>
                    @endforelse
                </div>
            </div>
        </x-filament::section>

        <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Son 7 gun siparis trendi</x-slot>
                <div class="space-y-2">
                    @foreach ($trend7 as $row)
                        @php $adet = (int) $row['adet']; $w = max(2, (int) round(($adet / max(1, $maxSiparis)) * 100)); @endphp
                        <div>
                            <div class="mb-1 flex justify-between text-xs text-gray-500">
                                <span>{{ $row['gun'] }}</span><span>{{ $adet }}</span>
                            </div>
                            <div class="h-2 rounded bg-gray-100 dark:bg-white/10">
                                <div class="h-2 rounded bg-primary-500" style="width: {{ $w }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Operasyon ozeti</x-slot>
                <ul class="space-y-2 text-sm">
                    <li>Odeme bekleyen siparis: <strong>{{ (int) $ops['odeme_bekleyen'] }}</strong></li>
                    <li>Terk edilmis (timeout) siparis: <strong>{{ (int) $ops['terk_edilmis'] }}</strong></li>
                    <li>Negatif stok: <strong>{{ (int) $ops['negatif_stok'] }}</strong></li>
                    <li>Rezerv sorunu (rezerv > stok): <strong>{{ (int) $ops['rezerv_sorunlu'] }}</strong></li>
                </ul>
            </x-filament::section>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">En cok satan urunler</x-slot>
                <div class="space-y-2 text-sm">
                    @forelse (($data['listeler']['en_cok_satanlar'] ?? []) as $s)
                        <div class="flex items-center justify-between rounded border border-gray-200 px-3 py-2 dark:border-white/10">
                            <span>{{ $s['urun_adi'] }}</span>
                            <span class="text-gray-600 dark:text-gray-300">{{ number_format((float) $s['toplam_miktar'], 2, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="text-gray-500">Kayit yok</p>
                    @endforelse
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">En cok goruntulenen urunler</x-slot>
                <div class="space-y-2 text-sm">
                    @forelse (($data['listeler']['en_cok_goruntulenenler'] ?? []) as $g)
                        <div class="rounded border border-gray-200 px-3 py-2 dark:border-white/10">
                            <div class="flex items-center justify-between">
                                <span>{{ $g['ad'] }}</span>
                                <span>{{ (int) $g['goruntulenme_sayisi'] }} goruntulenme</span>
                            </div>
                            @if (($g['yuksek_ilgi_dusuk_stok'] ?? false) === true)
                                <p class="mt-1 text-xs text-warning-600 dark:text-warning-400">Yuksek ilgi + dusuk stok</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-gray-500">Kayit yok</p>
                    @endforelse
                </div>
            </x-filament::section>
        </div>
    @endif
</x-filament-panels::page>

