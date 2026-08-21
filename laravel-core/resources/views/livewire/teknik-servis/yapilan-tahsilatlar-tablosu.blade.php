<div class="ts-cork-screen ts-cork-payment-panel">
    {{ $this->table }}

    @if ($this->record?->getKey())
        @livewire(
            \App\Livewire\TeknikServis\TeknikServisMasraflariTablosu::class,
            ['recordId' => (int) $this->record->getKey()],
            key('servis-masraflari-'.(int) $this->record->getKey())
        )
    @endif

    @php($vadeliPlanSatirlari = $this->vadeliPlanSatirlari())

    @if ($vadeliPlanSatirlari !== [])
        <div class="ts-cork-card mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-2 border-b border-gray-200 px-4 py-3 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $this->vadeliPlanBaslik() }}
                    </div>
                    <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        {{ $this->vadeliPlanAltBaslik() }}
                    </div>
                </div>
                <a
                    href="{{ $this->vadeTakipUrl() }}"
                    target="_blank"
                    class="text-xs font-medium text-primary-600 underline-offset-4 hover:underline dark:text-primary-400"
                >
                    Vade takibinde aç
                </a>
            </div>

            <div class="ts-cork-table-wrap overflow-x-auto">
                <table class="ts-cork-table min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2 text-left">Taksit</th>
                            <th class="px-4 py-2 text-left">Vade</th>
                            <th class="px-4 py-2 text-right">Tutar</th>
                            <th class="px-4 py-2 text-right">Ödenen</th>
                            <th class="px-4 py-2 text-right">Kalan</th>
                            <th class="px-4 py-2 text-left">Ödeme tarihi</th>
                            <th class="px-4 py-2 text-left">Durum</th>
                            <th class="sticky right-0 bg-gray-50 px-4 py-2 text-right dark:bg-gray-800">İşlem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($vadeliPlanSatirlari as $satir)
                            <tr class="text-gray-700 dark:text-gray-200">
                                <td class="whitespace-nowrap px-4 py-2 font-medium">{{ $satir['sira'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2">{{ $satir['vade_tarihi'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">{{ $satir['tutar'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">{{ $satir['odenen'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2 text-right font-medium">{{ $satir['kalan'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2">{{ $satir['odeme_tarihi'] }}</td>
                                <td class="whitespace-nowrap px-4 py-2">
                                    <span class="ts-cork-status-badge inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset {{ $satir['durum_sinifi'] }}">
                                        {{ $satir['durum'] }}
                                    </span>
                                </td>
                                <td class="sticky right-0 whitespace-nowrap bg-white px-4 py-2 text-right shadow-[-8px_0_12px_-12px_rgba(15,23,42,0.35)] dark:bg-gray-900">
                                    @if (! empty($satir['tahsilat_url']))
                                        <a
                                            href="{{ $satir['tahsilat_url'] }}"
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
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
