@php
    $paraBirimi = (string) ($ozet['para_birimi'] ?? $satis->para_birimi ?? 'TRY');
    $formatPara = static fn ($tutar): string => number_format((float) $tutar, 2, ',', '.') . ' ' . $paraBirimi;
    $plan = $ozet['plan'] ?? null;
    $taksitler = collect($ozet['taksitler'] ?? []);
    $planTahsilatlari = collect($ozet['plan_tahsilatlari'] ?? []);
    $dogrudanTahsilatlar = collect($ozet['dogrudan_tahsilatlar'] ?? []);
@endphp

<div class="space-y-5">
    <div class="grid gap-3 md:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-xs font-medium text-gray-500 dark:text-gray-400">Satış Toplamı</div>
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

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Satış Kalemleri</div>
            <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Ürün</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Miktar</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Tutar</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white dark:divide-gray-800 dark:bg-gray-900">
                        @forelse ($satis->kalemler as $kalem)
                            <tr>
                                <td class="px-3 py-2 text-gray-900 dark:text-gray-100">{{ $kalem->stok_adi }}</td>
                                <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ number_format((float) $kalem->miktar, 2, ',', '.') }}</td>
                                <td class="px-3 py-2 text-right text-gray-900 dark:text-gray-100">{{ $formatPara($kalem->satir_toplami) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Kalem kaydı yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Finans Durumu</div>
            <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Ödeme Tipi</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ strtoupper((string) $satis->odeme_tipi) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Durum</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $ozet['durum_etiketi'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Doğrudan Tahsilat</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $formatPara($ozet['dogrudan_tahsilat_toplami'] ?? 0) }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Finansal Açık</dt>
                    <dd class="font-medium text-gray-950 dark:text-white">{{ $formatPara($ozet['finansal_acik_tutar'] ?? 0) }}</dd>
                </div>
            </dl>

            @if ($plan)
                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">
                    <div class="font-semibold text-gray-950 dark:text-white">Plan #{{ (int) $plan->id }}</div>
                    <div class="mt-1 text-gray-600 dark:text-gray-300">
                        {{ ucfirst(str_replace('_', ' ', (string) $plan->plan_turu)) }} · {{ ucfirst(str_replace('_', ' ', (string) $plan->durum)) }} · Son vade {{ optional($plan->son_vade_tarihi)->format('d.m.Y') ?? '-' }}
                    </div>
                    <a href="{{ $vadeTakipUrl }}" class="mt-2 inline-block text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400">
                        Vade takibine git
                    </a>
                </div>
            @endif
        </div>
    </div>

    @if ($plan)
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Taksitler</div>
            <div class="mt-3 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                <table class="min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Taksit</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Vade</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Tutar</th>
                            <th class="px-3 py-2 text-right font-semibold text-gray-700 dark:text-gray-200">Kalan</th>
                            <th class="px-3 py-2 text-left font-semibold text-gray-700 dark:text-gray-200">Durum</th>
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
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-4 text-center text-sm text-gray-500 dark:text-gray-400">Taksit kaydı yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="text-sm font-semibold text-gray-950 dark:text-white">Doğrudan Tahsilatlar</div>
            <div class="mt-3 space-y-2">
                @forelse ($dogrudanTahsilatlar as $tahsilat)
                    <div class="flex items-center justify-between gap-3 rounded-md bg-gray-50 px-3 py-2 text-sm dark:bg-gray-800">
                        <div class="text-gray-600 dark:text-gray-300">{{ optional($tahsilat->tarih)->format('d.m.Y H:i') ?? '-' }}</div>
                        <div class="font-semibold text-gray-950 dark:text-white">{{ $formatPara($tahsilat->tutar) }}</div>
                    </div>
                @empty
                    <div class="text-sm text-gray-500 dark:text-gray-400">Doğrudan tahsilat yok.</div>
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
                    <div class="text-sm text-gray-500 dark:text-gray-400">Plan tahsilatı yok.</div>
                @endforelse
            </div>
        </div>
    </div>

    @if ($satis->durum === 'iptal')
        <div class="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-900 dark:border-rose-800 dark:bg-rose-950 dark:text-rose-100">
            İptal nedeni: {{ (string) ($satis->iptal_nedeni ?: '-') }}
        </div>
    @endif
</div>
