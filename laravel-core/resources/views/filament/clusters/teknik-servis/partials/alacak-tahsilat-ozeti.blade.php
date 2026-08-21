@php
    use App\Filament\Clusters\Muhasebe\Pages\TahsilatOlusturSayfasi;

    $paraBirimi = (string) ($ozet['para_birimi'] ?? 'TRY');
    $formatPara = static fn ($tutar): string => number_format((float) $tutar, 2, ',', '.') . ' ' . $paraBirimi;
    $plan = $ozet['plan'] ?? null;
    $taksitler = collect($ozet['taksitler'] ?? []);
    $planTahsilatlari = collect($ozet['plan_tahsilatlari'] ?? []);
    $teknikTahsilatlar = collect($ozet['teknik_tahsilatlar'] ?? []);
@endphp

<div class="space-y-5">
    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Servis Toplamı</div>
            <div class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ $formatPara($ozet['toplam_tutar'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Tahsil Edilen</div>
            <div class="mt-1 text-lg font-semibold text-emerald-700 dark:text-emerald-300">{{ $formatPara($ozet['tahsilat_toplami'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Plan Bakiyesi</div>
            <div class="mt-1 text-lg font-semibold text-amber-700 dark:text-amber-300">{{ $formatPara($ozet['plan_kalan_tutar'] ?? 0) }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Plansız Kalan</div>
            <div class="mt-1 text-lg font-semibold text-rose-700 dark:text-rose-300">{{ $formatPara($ozet['plansiz_kalan_tutar'] ?? 0) }}</div>
        </div>
    </div>

    @if ($plan)
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">
                        Plan #{{ (int) $plan->id }} · {{ ucfirst(str_replace('_', ' ', (string) $plan->plan_turu)) }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Durum: {{ ucfirst(str_replace('_', ' ', (string) $plan->durum)) }} · Son vade: {{ optional($plan->son_vade_tarihi)->format('d.m.Y') ?? '-' }}
                    </div>
                </div>
                <a href="{{ $vadeTakipUrl }}" class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                    Vade takibine git
                </a>
            </div>

            <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Taksit</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Vade</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Tutar</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Kalan</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Durum</th>
                            <th class="sticky right-0 bg-gray-50 px-3 py-2 text-right font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($taksitler as $taksit)
                            <tr>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">#{{ (int) $taksit->sira_no }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ optional($taksit->vade_tarihi)->format('d.m.Y') ?? '-' }}</td>
                                <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ $formatPara($taksit->tutar) }}</td>
                                <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ $formatPara($taksit->kalan_tutar) }}</td>
                                <td class="px-3 py-2 text-gray-600 dark:text-gray-300">{{ ucfirst(str_replace('_', ' ', (string) $taksit->durum)) }}</td>
                                <td class="sticky right-0 bg-white px-3 py-2 text-right shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.35)] dark:bg-gray-900">
                                    @if ((float) $taksit->kalan_tutar > 0.009 && ! in_array((string) $taksit->durum, ['odendi', 'iptal'], true))
                                        <a
                                            href="{{ TahsilatOlusturSayfasi::getUrl([
                                                'alacak_plan_taksiti_id' => (int) $taksit->id,
                                                'tutar' => number_format((float) $taksit->kalan_tutar, 2, '.', ''),
                                                'aciklama' => 'Teknik servis vade tahsilatı - Taksit #'.(int) $taksit->sira_no,
                                            ]) }}"
                                            class="inline-flex items-center justify-center rounded-md px-3 py-1.5 text-xs font-bold shadow-sm focus:outline-none focus:ring-2 focus:ring-orange-500 focus:ring-offset-2"
                                            style="min-width: 92px; background-color: #ea580c; border: 1px solid #c2410c; color: #ffffff;"
                                        >
                                            Tahsilat Al
                                        </a>
                                    @else
                                        <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Taksit kaydı yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            Bu servis için aktif ödeme planı bulunmuyor. Açık tutar varsa teslimden önce tahsilat alınmalı veya ödeme planı oluşturulmalı.
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Teknik Servis Tahsilatları</div>
            <div class="mt-3 space-y-2">
                @forelse ($teknikTahsilatlar as $tahsilat)
                    <div class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
                        <div class="text-gray-600 dark:text-gray-300">{{ optional($tahsilat->tarih)->format('d.m.Y H:i') ?? '-' }} · {{ strtoupper((string) $tahsilat->kanal) }}</div>
                        <div class="font-semibold text-gray-950 dark:text-white">{{ $formatPara($tahsilat->tutar) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400">Kayıtlı teknik servis tahsilatı yok.</div>
                @endforelse
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Plan Tahsilatları</div>
            <div class="mt-3 space-y-2">
                @forelse ($planTahsilatlari as $eslesme)
                    <div class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
                        <div class="text-gray-600 dark:text-gray-300">
                            {{ optional($eslesme->tarih)->format('d.m.Y H:i') ?? '-' }}
                            @if ($eslesme->taksit)
                                · Taksit #{{ (int) $eslesme->taksit->sira_no }}
                            @endif
                        </div>
                        <div class="font-semibold text-gray-950 dark:text-white">{{ $formatPara($eslesme->tutar) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400">Plan üzerinden tahsilat yok.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
