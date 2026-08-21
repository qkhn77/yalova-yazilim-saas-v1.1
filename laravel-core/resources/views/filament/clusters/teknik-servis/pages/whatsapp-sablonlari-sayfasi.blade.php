<x-filament-panels::page>
    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(360px,440px)]">
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Sablonlar</h2>
                <button
                    type="button"
                    wire:click="yeni"
                    class="rounded-md bg-gray-900 px-3 py-1.5 text-xs font-semibold text-white hover:bg-gray-700 dark:bg-white dark:text-gray-900"
                >
                    Yeni
                </button>
            </div>

            <div class="overflow-hidden rounded-md border border-gray-200 dark:border-gray-700">
                <table class="w-full divide-y divide-gray-200 text-left text-sm dark:divide-gray-700">
                    <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-3 py-2">Sablon</th>
                            <th class="px-3 py-2">Sira</th>
                            <th class="px-3 py-2">Durum</th>
                            <th class="px-3 py-2 text-right">Islem</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->sablonlar() as $sablon)
                            <tr wire:key="whatsapp-sablon-{{ $sablon->id }}" class="text-gray-700 dark:text-gray-200">
                                <td class="px-3 py-2 font-medium">{{ $sablon->ad }}</td>
                                <td class="px-3 py-2">{{ $sablon->siralama }}</td>
                                <td class="px-3 py-2">
                                    <button
                                        type="button"
                                        wire:click="aktifDegistir({{ (int) $sablon->id }})"
                                        class="rounded px-2 py-1 text-xs font-semibold {{ $sablon->aktif ? 'bg-success-100 text-success-700 dark:bg-success-900/40 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}"
                                    >
                                        {{ $sablon->aktif ? 'Aktif' : 'Pasif' }}
                                    </button>
                                </td>
                                <td class="px-3 py-2">
                                    <div class="flex justify-end gap-2">
                                        <button
                                            type="button"
                                            wire:click="duzenle({{ (int) $sablon->id }})"
                                            class="inline-flex rounded-md border border-gray-300 p-2 text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                                            aria-label="Düzenle"
                                            title="Düzenle"
                                        >
                                            <x-filament::icon icon="heroicon-o-pencil-square" class="h-4 w-4" />
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="sil({{ (int) $sablon->id }})"
                                            wire:confirm="Bu sablon silinsin mi?"
                                            class="inline-flex rounded-md border border-danger-300 p-2 text-danger-700 hover:bg-danger-50 dark:border-danger-700 dark:text-danger-300 dark:hover:bg-danger-950/30"
                                            aria-label="Sil"
                                            title="Sil"
                                        >
                                            <x-filament::icon icon="heroicon-o-trash" class="h-4 w-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-3 py-5 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Kayit bulunamadi.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            @if ($duzenleyiciAcik)
                <h2 class="mb-3 text-sm font-semibold text-gray-950 dark:text-white">
                    {{ $duzenlenenSablonId ? 'Sablon duzenle' : 'Yeni sablon' }}
                </h2>

                <form wire:submit="kaydet" class="space-y-3">
                    <label class="block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Sablon adi</span>
                        <input
                            type="text"
                            wire:model="data.ad"
                            maxlength="191"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Kod</span>
                        <input
                            type="text"
                            wire:model="data.kod"
                            maxlength="64"
                            class="mt-1 w-full rounded-md border-gray-300 font-mono text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        >
                    </label>

                    <label class="block">
                        <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Mesaj</span>
                        <textarea
                            wire:model="data.mesaj"
                            rows="15"
                            class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            required
                        ></textarea>
                    </label>

                    <div class="grid grid-cols-[120px_1fr] gap-3">
                        <label class="block">
                            <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Sira</span>
                            <input
                                type="number"
                                wire:model="data.siralama"
                                min="0"
                                max="9999"
                                class="mt-1 w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            >
                        </label>

                        <label class="mt-6 inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-200">
                            <input
                                type="checkbox"
                                wire:model="data.aktif"
                                class="rounded border-gray-300 text-primary-600 shadow-sm dark:border-gray-700 dark:bg-gray-950"
                            >
                            Aktif
                        </label>
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button
                            type="submit"
                            class="rounded-md bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500"
                        >
                            Kaydet
                        </button>
                        <button
                            type="button"
                            wire:click="formuKapat"
                            class="rounded-md border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                        >
                            Kapat
                        </button>
                    </div>
                </form>
            @else
                <div class="rounded-md border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-700 dark:text-gray-400">
                    Duzenlemek icin listeden sablon secin veya yeni sablon olusturun.
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
