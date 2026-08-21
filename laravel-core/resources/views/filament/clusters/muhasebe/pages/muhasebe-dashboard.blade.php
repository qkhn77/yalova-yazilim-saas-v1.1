@php
    /** @var \App\Filament\Clusters\Muhasebe\Pages\MuhasebeDashboardSayfasi $this */
    $ozet = $this->ozet();
    $paraTr = static function (string $tutar, string $pb = 'TRY'): string {
        return number_format((float) $tutar, 2, ',', '.').' '.strtoupper($pb);
    };
@endphp

<x-filament-panels::page>
    @if (($ozet['firma_id'] ?? null) === null)
        <x-filament::section>
            <x-slot name="heading">Aktif firma yok</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Özet rakamlar için üst menüden bir firma seçin. Süper yönetici iseniz kiracı bağlamını açmadan bu ekran veri göstermez.
            </p>
        </x-filament::section>
    @else
        <div class="yk-dashboard-shell yk-muhasebe-dashboard space-y-4">
        @php
            $kpi = $ozet['kpi'];
            $netAkis = (string) ($kpi['net_akis_bugun'] ?? '0');
            $mutabakat = $ozet['barkodlu_satis_mutabakat'] ?? null;
            $mutabakatSorun = is_array($mutabakat) ? (int) ($mutabakat['toplam_sorun'] ?? 0) : 0;
            $mutabakatKritikSorun = is_array($mutabakat) ? (int) ($mutabakat['kritik_sorun'] ?? 0) : 0;
            $mutabakatGuncel = is_array($mutabakat) && filled($mutabakat['updated_at'] ?? null)
                ? \Illuminate\Support\Carbon::parse((string) $mutabakat['updated_at'])
                : null;
            $uyarilar = [];
            if ((int) $kpi['kritik_stok'] > 0) {
                $uyarilar[] = ['tone' => 'warning', 'text' => $kpi['kritik_stok'].' stok kartı kritik seviyede veya altında.'];
            }
            if ((int) $kpi['negatif_stok'] > 0) {
                $uyarilar[] = ['tone' => 'danger', 'text' => $kpi['negatif_stok'].' stok kartında negatif stok işareti var.'];
            }
            if (bccomp((string) $kpi['vadesi_gecmis_acik'], '0', 2) > 0) {
                $uyarilar[] = ['tone' => 'warning', 'text' => 'Vadesi geçmiş açık alacak/borç tutarı: '.$paraTr($kpi['vadesi_gecmis_acik']).'.'];
            }
            if (bccomp($netAkis, '0', 2) < 0) {
                $uyarilar[] = ['tone' => 'danger', 'text' => 'Bugünkü finans net akışı negatif: '.$paraTr($netAkis).'.'];
            }
        @endphp

        @if ($mutabakatKritikSorun > 0)
            <div class="mb-4 rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-900 dark:border-danger-600/40 dark:bg-danger-500/10 dark:text-danger-100">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                <div class="font-semibold">Kritik muhasebe uyarısı: Barkodlu satış mutabakatında {{ $mutabakatKritikSorun }} kritik sorun bulundu.</div>
                        <div class="mt-1 opacity-90">
                            @if ($mutabakatGuncel)
                                Son kontrol: {{ $mutabakatGuncel->format('d.m.Y H:i') }}
                            @else
                                Son kontrol zamanı bilinmiyor.
                            @endif
                        </div>
                    </div>
                    <a href="{{ \App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisMuhasebeMutabakatSayfasi::getUrl() }}" class="inline-flex items-center rounded-md bg-danger-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-danger-500">
                        Mutabakat ekranına git
                    </a>
                </div>
            </div>
        @elseif ($mutabakatSorun > 0)
            <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 px-4 py-3 text-sm text-warning-900 dark:border-warning-600/40 dark:bg-warning-500/10 dark:text-warning-100">
                Barkodlu satış mutabakatında kritik olmayan {{ $mutabakatSorun }} uyarı bulundu.
                <a href="{{ \App\Filament\Clusters\Muhasebe\Pages\BarkodluSatisMuhasebeMutabakatSayfasi::getUrl() }}" class="font-semibold underline-offset-2 hover:underline">Detaylar</a>
            </div>
        @endif

        @if ($uyarilar !== [])
            <div class="mb-4 space-y-2">
                @foreach ($uyarilar as $u)
                    <div @class([
                        'rounded-lg border px-3 py-2 text-sm',
                        'border-warning-300 bg-warning-50 text-warning-900 dark:border-warning-600/40 dark:bg-warning-500/10 dark:text-warning-100' => $u['tone'] === 'warning',
                        'border-danger-300 bg-danger-50 text-danger-900 dark:border-danger-600/40 dark:bg-danger-500/10 dark:text-danger-100' => $u['tone'] === 'danger',
                    ])>
                        {{ $u['text'] }}
                    </div>
                @endforeach
            </div>
        @endif

        @php
            $kartlar = [
                ['baslik' => 'Tahsilat (bugün)', 'deger' => $paraTr($kpi['tahsilat_bugun']), 'aciklama' => 'Bu ay: '.$paraTr($kpi['tahsilat_ay']), 'icon' => 'heroicon-m-arrow-down-circle'],
                ['baslik' => 'Ödeme (bugün)', 'deger' => $paraTr($kpi['odeme_bugun']), 'aciklama' => 'Bu ay: '.$paraTr($kpi['odeme_ay']), 'icon' => 'heroicon-m-arrow-up-circle'],
                ['baslik' => 'Net akış (bugün)', 'deger' => $paraTr($netAkis), 'aciklama' => 'Tahsilat eksi ödeme', 'icon' => 'heroicon-m-arrows-right-left', 'degerSinifi' => bccomp($netAkis, '0', 2) >= 0 ? 'text-success-700 dark:text-success-400' : 'text-danger-700 dark:text-danger-400'],
                ['baslik' => 'Açık fatura (onaylı)', 'deger' => $paraTr($kpi['acik_fatura']), 'aciklama' => number_format((int) ($kpi['acik_fatura_sayisi'] ?? 0), 0, ',', '.').' açık kayıt · PB dağılımı değişebilir.', 'icon' => 'heroicon-m-document-text'],
                ['baslik' => 'Vadesi geçmiş açık', 'deger' => $paraTr($kpi['vadesi_gecmis_acik']), 'aciklama' => number_format((int) ($kpi['vadesi_gecmis_acik_sayisi'] ?? 0), 0, ',', '.').' açık kayıt', 'icon' => 'heroicon-m-clock'],
                ['baslik' => 'Kritik stok', 'deger' => (int) $kpi['kritik_stok'], 'aciklama' => 'Takipte, min. ≤ mevcut', 'icon' => 'heroicon-m-exclamation-triangle'],
                ['baslik' => 'Negatif stok bayrağı', 'deger' => (int) $kpi['negatif_stok'], 'aciklama' => 'Negatif miktarlı stok kartları', 'icon' => 'heroicon-m-arrow-trending-down'],
                ['baslik' => 'Stok değeri (takip)', 'deger' => $paraTr($kpi['stok_degeri']), 'aciklama' => 'Kartlardaki stok değerlerinin toplamı.', 'icon' => 'heroicon-m-cube'],
            ];
        @endphp
        <div class="yk-info-card-grid yk-muhasebe-kpi-grid grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($kartlar as $kart)
                <div class="yk-info-card yk-dashboard-kpi-card min-w-0 rounded-xl border border-gray-200 bg-white p-3 shadow-sm ring-1 ring-gray-950/5 dark:border-white/10 dark:bg-gray-900 dark:ring-white/10 sm:p-4">
                    <div class="flex items-start justify-between gap-3">
                        <p class="min-w-0 text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">{{ $kart['baslik'] }}</p>
                        <x-filament::icon :icon="$kart['icon']" class="h-8 w-8 shrink-0 rounded-md bg-primary-50 p-1.5 text-primary-600 dark:bg-primary-500/15 dark:text-primary-300" aria-hidden="true" />
                    </div>
                    <p class="mt-1 text-xl font-semibold tracking-tight {{ $kart['degerSinifi'] ?? 'text-gray-950 dark:text-white' }}">{{ $kart['deger'] }}</p>
                    @if (isset($kart['url']))
                        <a href="{{ $kart['url'] }}" class="mt-1 inline-block text-xs font-medium text-primary-600 hover:underline">{{ $kart['aciklama'] }}</a>
                    @else
                        <p class="mt-1 text-xs leading-4 text-gray-500 dark:text-gray-400">{{ $kart['aciklama'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        @php
            $sistemDetaylari = $this->sistemDetaylariYuklendi
                ? $this->sistemDetaylari()
                : ['tutarsizliklar' => [], 'sistem_uyarilari' => collect()];
        @endphp

        @if (! $this->sistemDetaylariYuklendi)
            <div class="mt-6 flex justify-end">
                <x-filament::button
                    color="gray"
                    icon="heroicon-m-shield-check"
                    wire:click="sistemDetaylariniYukle"
                    wire:loading.attr="disabled"
                    wire:target="sistemDetaylariniYukle"
                >
                    Sistem detaylarını yükle
                </x-filament::button>
            </div>
        @endif

        @if (($sistemDetaylari['sistem_uyarilari'] ?? collect())->isNotEmpty())
            <x-filament::section class="mt-6">
                <x-slot name="heading">Sistem Uyarıları</x-slot>
                <x-slot name="description">Son 10 warning/error/critical olay.</x-slot>
                <div class="space-y-2">
                    @foreach ($sistemDetaylari['sistem_uyarilari'] as $olay)
                        <div @class([
                            'rounded-lg border px-3 py-2 text-sm',
                            'border-danger-300 bg-danger-50 text-danger-900 dark:border-danger-600/40 dark:bg-danger-500/10 dark:text-danger-100' => in_array($olay->seviye, ['error', 'critical'], true),
                            'border-warning-300 bg-warning-50 text-warning-900 dark:border-warning-600/40 dark:bg-warning-500/10 dark:text-warning-100' => $olay->seviye === 'warning',
                            'border-gray-200 bg-white text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-white' => ! in_array($olay->seviye, ['error', 'critical', 'warning'], true),
                        ])>
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-semibold">{{ strtoupper((string) $olay->seviye) }} · {{ $olay->tip }}</span>
                                <span class="text-xs opacity-80">{{ optional($olay->created_at)->format('d.m.Y H:i') }}</span>
                            </div>
                            <div class="mt-1">{{ $olay->mesaj }}</div>
                        </div>
                    @endforeach
                </div>
            </x-filament::section>
        @endif

        @if (($sistemDetaylari['tutarsizliklar'] ?? []) !== [])
            <x-filament::section class="mt-6" collapsible>
                <x-slot name="heading">Sistem tutarlılık uyarıları</x-slot>
                <x-slot name="description">Salt okunur kontrol özeti; muhasebe hesapları değiştirilmez.</x-slot>
                <ul class="list-inside list-disc space-y-1 text-sm text-gray-700 dark:text-gray-300">
                    @foreach (array_slice($sistemDetaylari['tutarsizliklar'], 0, 8) as $h)
                        <li>
                            <span class="font-mono text-xs">{{ $h['kod'] ?? '—' }}</span>
                            — {{ $h['detay'] ?? '' }}
                        </li>
                    @endforeach
                </ul>
                @if (count($sistemDetaylari['tutarsizliklar']) > 8)
                    <p class="mt-2 text-xs text-gray-500">+{{ count($sistemDetaylari['tutarsizliklar']) - 8 }} kayıt daha…</p>
                @endif
            </x-filament::section>
        @endif

        <div class="mt-6 grid grid-cols-1 gap-4 xl:grid-cols-2">
            <x-filament::section>
                <x-slot name="heading">Son faturalar</x-slot>
                <x-slot name="description">Onaylı faturalar, tarihe göre.</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[28rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-start dark:bg-white/5">
                                <th class="px-3 py-2 font-medium">No</th>
                                <th class="px-3 py-2 font-medium">Tür</th>
                                <th class="px-3 py-2 font-medium">Cari</th>
                                <th class="px-3 py-2 font-medium">Tarih</th>
                                <th class="px-3 py-2 font-medium">Ödeme / vade</th>
                                <th class="px-3 py-2 font-medium text-end">Açık</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ozet['son_faturalar'] as $f)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="px-3 py-2">
                                        <a href="{{ \App\Filament\Clusters\Muhasebe\Resources\FaturaKaynagi::getUrl('view', ['record' => $f->id]) }}" class="text-primary-600 hover:underline font-medium">
                                            {{ $f->fatura_no ?: '#'.$f->id }}
                                        </a>
                                    </td>
                                    @php
                                        $faturaTuru = $f->tur instanceof \App\Muhasebe\Enumlar\FaturaTuru
                                            ? $f->tur->etiket()
                                            : (\App\Muhasebe\Enumlar\FaturaTuru::tryFrom((string) $f->tur)?->etiket() ?? (string) $f->tur);
                                    @endphp
                                    <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ $faturaTuru ?: '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $f->cari?->ad ?? '—' }}</td>
                                    <td class="px-3 py-2">{{ optional($f->tarih)->format('d.m.Y') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">
                                        <div>{{ $f->odeme_durumu ? str_replace('_', ' ', (string) $f->odeme_durumu) : '—' }}</div>
                                        <div @class([
                                            'text-danger-600 dark:text-danger-400' => $f->vade_tarihi && $f->vade_tarihi->isPast() && bccomp((string) ($f->acik_tutar ?? '0'), '0', 2) > 0,
                                        ])>{{ optional($f->vade_tarihi)->format('d.m.Y') ?? 'Vade yok' }}</div>
                                    </td>
                                    <td class="px-3 py-2 text-end">{{ $paraTr((string) ($f->acik_tutar ?? '0'), (string) ($f->para_birimi ?: 'TRY')) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-3 py-6 text-center text-gray-500">Kayıt yok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">Son finans hareketleri</x-slot>
                <x-slot name="description">Aktif tahsilat / ödeme kayıtları.</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[28rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-start dark:bg-white/5">
                                <th class="px-3 py-2 font-medium">Tür</th>
                                <th class="px-3 py-2 font-medium">Tarih</th>
                                <th class="px-3 py-2 font-medium">Cari</th>
                                <th class="px-3 py-2 font-medium text-end">Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ozet['son_finans'] as $fh)
                                @php
                                    $tur = $fh->tur instanceof \App\Muhasebe\Enumlar\FinansHareketTuru ? $fh->tur->value : (string) $fh->tur;
                                @endphp
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="px-3 py-2">
                                        <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset
                                            {{ $tur === 'tahsilat' ? 'bg-success-50 text-success-700 ring-success-600/20 dark:bg-success-400/10 dark:text-success-400' : '' }}
                                            {{ $tur === 'odeme' ? 'bg-gray-50 text-gray-700 ring-gray-600/20 dark:bg-white/5 dark:text-gray-300' : '' }}
                                            {{ ! in_array($tur, ['tahsilat', 'odeme'], true) ? 'bg-gray-50 text-gray-700 ring-gray-600/10' : '' }}">
                                            {{ $tur }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2">{{ optional($fh->tarih)->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 dark:text-gray-400">{{ $fh->cari?->ad ?? '—' }}</td>
                                    <td class="px-3 py-2 text-end font-medium">{{ $paraTr((string) ($fh->tutar ?? '0'), (string) ($fh->para_birimi ?: 'TRY')) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-6 text-center text-gray-500">Kayıt yok.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        </div>
        </div>
    @endif
</x-filament-panels::page>
