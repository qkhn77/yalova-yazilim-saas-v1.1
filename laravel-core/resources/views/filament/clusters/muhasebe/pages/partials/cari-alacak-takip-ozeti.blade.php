@php
    $paraBirimi = (string) ($ozet['para_birimi'] ?? 'TRY');
    $formatPara = static fn ($tutar, ?string $pb = null): string => number_format((float) $tutar, 2, ',', '.') . ' ' . strtoupper((string) ($pb ?: $paraBirimi));
    $ana = $ozet['ana_para_ozeti'] ?? [];
    $planlar = collect($ozet['planlar'] ?? []);
    $taksitler = collect($ozet['acik_taksitler'] ?? []);
    $ajanda = collect($ozet['takip_ajandasi'] ?? []);
    $sozler = collect($ozet['odeme_sozleri'] ?? []);
    $notlar = collect($ozet['takip_notlari'] ?? []);
@endphp

<div class="space-y-5">
    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Açık Alacak</div>
            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $formatPara($ana['acik_toplam'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Geciken</div>
            <div class="mt-1 text-lg font-semibold text-rose-700 dark:text-rose-300">{{ $formatPara($ana['geciken_toplam'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Bugün</div>
            <div class="mt-1 text-lg font-semibold text-amber-700 dark:text-amber-300">{{ $formatPara($ana['bugun_toplam'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Plan / Vade</div>
            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ (int) ($ana['plan_adedi'] ?? 0) }} / {{ (int) ($ana['acik_taksit_adedi'] ?? 0) }}</div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex items-center justify-between gap-3">
                <div class="text-sm font-semibold text-gray-950 dark:text-white">Aktif Planlar</div>
                @if(filled($vadeTakipUrl ?? null))
                    <a href="{{ $vadeTakipUrl }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">Vade Takibi</a>
                @endif
            </div>
            <div class="mt-3 space-y-2">
                @forelse ($planlar as $plan)
                    <div class="rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium text-gray-950 dark:text-white">#{{ (int) $plan->id }} · {{ ucfirst(str_replace('_', ' ', (string) $plan->plan_turu)) }}</div>
                            <div class="font-semibold text-gray-950 dark:text-white">{{ $formatPara($plan->kalan_tutar, $plan->para_birimi) }}</div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Durum: {{ ucfirst(str_replace('_', ' ', (string) $plan->durum)) }} · Açık vade: {{ (int) ($plan->acik_taksit_adedi ?? 0) }} · Son vade: {{ optional($plan->son_vade_tarihi)->format('d.m.Y') ?? '-' }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400">Aktif ödeme planı yok.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Yaklaşan Takipler</div>
            <div class="mt-3 space-y-2">
                @forelse ($ajanda as $not)
                    <div class="rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium text-gray-950 dark:text-white">{{ ucfirst(str_replace('_', ' ', (string) $not->durum)) }}</div>
                            <div class="text-gray-600 dark:text-gray-300">{{ optional($not->sonraki_takip_tarihi)->format('d.m.Y H:i') ?? '-' }}</div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ (string) ($not->not ?: '-') }}</div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400">Yaklaşan takip yok.</div>
                @endforelse
            </div>
        </div>
    </div>

    <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="text-sm font-semibold text-gray-950 dark:text-white">Açık Vadeler</div>
        <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
            <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Plan</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Vade</th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Tutar</th>
                        <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Kalan</th>
                        <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Durum</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                    @forelse ($taksitler as $taksit)
                        @php $plan = $taksit->plan; @endphp
                        <tr>
                            <td class="px-3 py-2 text-gray-900 dark:text-gray-100">#{{ (int) $taksit->alacak_plan_id }} / {{ (int) $taksit->sira_no }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ optional($taksit->vade_tarihi)->format('d.m.Y') ?? '-' }}</td>
                            <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ $formatPara($taksit->tutar, $plan?->para_birimi) }}</td>
                            <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ $formatPara($taksit->kalan_tutar, $plan?->para_birimi) }}</td>
                            <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ ucfirst(str_replace('_', ' ', (string) $taksit->durum)) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Açık vade yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Ödeme Sözleri</div>
            <div class="mt-3 space-y-2">
                @forelse ($sozler as $soz)
                    <div class="rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium text-gray-950 dark:text-white">{{ optional($soz->odeme_sozu_tarihi)->format('d.m.Y H:i') ?? '-' }}</div>
                            <div class="font-semibold text-gray-950 dark:text-white">{{ $formatPara($soz->odeme_sozu_tutari, $soz->para_birimi) }}</div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Durum: {{ ucfirst(str_replace('_', ' ', (string) ($soz->odeme_sozu_durumu ?: 'beklemede'))) }} · Plan #{{ (int) $soz->alacak_plan_id }}
                        </div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400">Ödeme sözü kaydı yok.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Son Takip Notları</div>
            <div class="mt-3 space-y-2">
                @forelse ($notlar as $not)
                    <div class="rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
                        <div class="flex items-center justify-between gap-3">
                            <div class="font-medium text-gray-950 dark:text-white">{{ ucfirst(str_replace('_', ' ', (string) $not->takip_tipi)) }}</div>
                            <div class="text-gray-600 dark:text-gray-300">{{ optional($not->takip_tarihi)->format('d.m.Y H:i') ?? '-' }}</div>
                        </div>
                        <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ (string) ($not->not ?: '-') }}</div>
                        @if(filled($not->sonuc_notu))
                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">Sonuç: {{ $not->sonuc_notu }}</div>
                        @endif
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400">Takip notu yok.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
