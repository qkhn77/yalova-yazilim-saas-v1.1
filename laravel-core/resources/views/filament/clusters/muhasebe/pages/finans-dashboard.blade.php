@php
    /** @var \App\Filament\Clusters\Muhasebe\Pages\FinansDashboardSayfasi $this */
    $ozet = $this->ozet();
    $paraTr = static function (string $tutar, string $pb = 'TRY'): string {
        return number_format((float) $tutar, 2, ',', '.') . ' ' . strtoupper($pb);
    };
    $satirlar = static function ($rows) use ($paraTr): string {
        if ($rows === null || count($rows) === 0) {
            return '—';
        }

        return collect($rows)->map(fn ($r) => $paraTr($r->toplam, $r->para_birimi))->implode(' · ');
    };
    $tlSatirlar = static function ($rows) use ($paraTr, $satirlar): string {
        $rows = collect($rows ?? []);
        if ($rows->isEmpty() || $rows->contains(fn ($row): bool => $row->tl_toplam === null)) {
            return $satirlar($rows);
        }

        $toplam = $rows->reduce(
            fn (string $carry, $row): string => bcadd($carry, (string) ($row->tl_toplam ?? '0'), 2),
            '0'
        );

        return $paraTr($toplam, 'TRY');
    };
    $turEtiketi = static function ($tur): string {
        $value = $tur instanceof \App\Muhasebe\Enumlar\FinansHareketTuru ? $tur->value : (string) $tur;

        return match ($value) {
            'tahsilat' => 'Tahsilat',
            'odeme' => 'Ödeme',
            'virman' => 'Virman',
            'mahsup' => 'Mahsup',
            default => $value !== '' ? ucfirst(str_replace('_', ' ', $value)) : '—',
        };
    };
    $kaynakHedef = static function ($fh) use ($paraTr): string {
        $hesapHareketi = collect([
            $fh->kasaHareketleri ?? [],
            $fh->bankaHareketleri ?? [],
            $fh->posHareketleri ?? [],
        ])->flatten()->first();
        $cariMetni = $paraTr((string) ($fh->tutar ?? '0'), (string) ($fh->para_birimi ?: 'TRY'));
        $hesapMetni = $hesapHareketi
            ? $paraTr((string) abs((float) ($hesapHareketi->tutar ?? 0)), (string) ($hesapHareketi->para_birimi ?: ($fh->para_birimi ?: 'TRY')))
            : $cariMetni;
        $tahsilatMi = ($fh->tur instanceof \App\Muhasebe\Enumlar\FinansHareketTuru ? $fh->tur->value : (string) $fh->tur) === 'tahsilat';

        return $tahsilatMi ? $cariMetni.' → '.$hesapMetni : $hesapMetni.' → '.$cariMetni;
    };
@endphp

<x-filament-panels::page>
    @if (($ozet['firma_id'] ?? null) === null)
        <x-filament::section>
            <x-slot name="heading">Aktif firma yok</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Finans özetleri için üst menüden firma seçin.
            </p>
        </x-filament::section>
    @else
        <div class="muhasebe-cork-screen yk-dashboard-shell yk-finans-dashboard space-y-4">
        @php
            $kpi = $ozet['kpi'];
            $kartlar = [
                [
                    'label' => 'Bugün tahsilat',
                    'value' => $satirlar($kpi['tahsilat_bugun']),
                    'description' => 'Gün içinde kaydedilen tahsilatlar',
                    'color' => 'success',
                    'icon' => 'heroicon-m-arrow-down-circle',
                ],
                [
                    'label' => 'Bugün ödeme',
                    'value' => $satirlar($kpi['odeme_bugun']),
                    'description' => 'Gün içinde kaydedilen ödemeler',
                    'color' => 'warning',
                    'icon' => 'heroicon-m-arrow-up-circle',
                ],
                [
                    'label' => 'Net akış (bugün)',
                    'value' => $satirlar($kpi['net_akis_bugun'] ?? collect()),
                    'description' => 'Tahsilat eksi ödeme',
                    'color' => collect($kpi['net_akis_bugun'] ?? [])->contains(fn ($row): bool => bccomp((string) ($row->toplam ?? '0'), '0', 2) < 0) ? 'danger' : 'success',
                    'icon' => 'heroicon-m-arrows-right-left',
                ],
                [
                    'label' => 'Toplam kasa',
                    'value' => $tlSatirlar($kpi['kasa']),
                    'description' => 'Güncel kurla tüm para birimlerinin TL karşılığı',
                    'color' => 'primary',
                    'icon' => 'heroicon-m-wallet',
                ],
                [
                    'label' => 'Toplam banka',
                    'value' => $tlSatirlar($kpi['banka']),
                    'description' => 'Güncel kurla tüm para birimlerinin TL karşılığı',
                    'color' => 'primary',
                    'icon' => 'heroicon-m-building-library',
                ],
                [
                    'label' => 'Toplam POS',
                    'value' => $tlSatirlar($kpi['pos']),
                    'description' => 'Güncel kurla tüm para birimlerinin TL karşılığı',
                    'color' => 'primary',
                    'icon' => 'heroicon-m-credit-card',
                ],
                [
                    'label' => 'Açık tahsilat',
                    'value' => $tlSatirlar($kpi['acik_tahsilat']),
                    'description' => 'Güncel kurla tüm para birimlerinin TL karşılığı',
                    'color' => 'danger',
                    'icon' => 'heroicon-m-exclamation-triangle',
                ],
            ];

            $renkSiniflari = [
                'primary' => 'fi-color fi-color-primary fi-text-color-600 dark:fi-text-color-400',
                'success' => 'fi-color fi-color-success fi-text-color-700 dark:fi-text-color-400',
                'warning' => 'fi-color fi-color-warning fi-text-color-700 dark:fi-text-color-400',
                'danger' => 'fi-color fi-color-danger fi-text-color-700 dark:fi-text-color-400',
            ];
        @endphp

        <div class="yk-dashboard-alert mb-3 flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
            <span>Finans hareketleri firma ve aktif kayıt kapsamındadır.</span>
            <span class="whitespace-nowrap">Özet yenilendi: {{ $ozet['ozet_zamani'] ?? '—' }}</span>
        </div>

        <div class="yk-finans-kpi-grid mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-7" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
            @foreach ($kartlar as $kart)
                @php
                    $renk = $renkSiniflari[$kart['color']] ?? $renkSiniflari['primary'];
                @endphp

                <div class="yk-info-card yk-dashboard-kpi-card min-w-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            {{ $kart['label'] }}
                        </p>
                        <x-filament::icon
                            :icon="$kart['icon']"
                            class="h-4 w-4 shrink-0 {{ $renk }}"
                            aria-hidden="true"
                        />
                    </div>

                    <p class="mt-1 truncate text-xl font-semibold tracking-tight text-gray-950 dark:text-white sm:text-2xl">
                        {{ $kart['value'] }}
                    </p>

                    <div class="mt-1 text-xs leading-4 {{ $renk }}">
                        <span>
                            {{ $kart['description'] }}
                        </span>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-3">
            <x-filament::section class="xl:col-span-3">
                <x-slot name="heading">Son finans hareketleri</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[36rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-start dark:bg-white/5">
                                <th class="px-3 py-2 font-medium">Tür</th>
                                <th class="px-3 py-2 font-medium">Tarih</th>
                                <th class="px-3 py-2 font-medium">Modül</th>
                                <th class="px-3 py-2 font-medium">Cari</th>
                                <th class="px-3 py-2 font-medium">Kaynak → Hedef</th>
                                <th class="px-3 py-2 font-medium">Açıklama</th>
                                <th class="px-3 py-2 font-medium text-end">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ozet['son_finans'] as $fh)
                                @php
                                    $tur = $turEtiketi($fh->tur);
                                @endphp
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="px-3 py-2">{{ $tur }}</td>
                                    <td class="px-3 py-2">{{ optional($fh->tarih)->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $fh->modul_etiketi }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $fh->cari?->ad ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $kaynakHedef($fh) }}</td>
                                    <td class="max-w-[16rem] truncate px-3 py-2 text-gray-600 dark:text-gray-400" title="{{ $fh->aciklama ?? '' }}">{{ $fh->aciklama ?: '—' }}</td>
                                    <td class="px-3 py-2 text-end font-medium">{{ $paraTr((string) ($fh->tutar ?? '0'), (string) ($fh->para_birimi ?: 'TRY')) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-3 py-6 text-center text-gray-500">Kayıt yok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Son tahsilatlar</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-start dark:bg-white/5">
                                <th class="px-3 py-2 font-medium">Tarih</th>
                                <th class="px-3 py-2 font-medium">Cari</th>
                                <th class="px-3 py-2 font-medium">Kaynak → Hedef</th>
                                <th class="px-3 py-2 font-medium text-end">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ozet['son_tahsilat'] as $fh)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="px-3 py-2">{{ optional($fh->tarih)->format('d.m.Y') ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $fh->cari?->ad ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $kaynakHedef($fh) }}</td>
                                    <td class="px-3 py-2 text-end">{{ $paraTr((string) ($fh->tutar ?? '0'), (string) ($fh->para_birimi ?: 'TRY')) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Yok</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section class="xl:col-span-2">
                <x-slot name="heading">Son ödemeler</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-start dark:bg-white/5">
                                <th class="px-3 py-2 font-medium">Tarih</th>
                                <th class="px-3 py-2 font-medium">Cari</th>
                                <th class="px-3 py-2 font-medium">Kaynak → Hedef</th>
                                <th class="px-3 py-2 font-medium text-end">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ozet['son_odeme'] as $fh)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="px-3 py-2">{{ optional($fh->tarih)->format('d.m.Y') ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ $fh->cari?->ad ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $kaynakHedef($fh) }}</td>
                                    <td class="px-3 py-2 text-end">{{ $paraTr((string) ($fh->tutar ?? '0'), (string) ($fh->para_birimi ?: 'TRY')) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Yok</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
        </div>
    @endif
</x-filament-panels::page>
