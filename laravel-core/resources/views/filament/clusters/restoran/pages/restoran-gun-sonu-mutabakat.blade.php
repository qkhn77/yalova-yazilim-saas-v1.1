@php
    $mutabakat = $this->mutabakat();
    $kapanis = $mutabakat['kapanis'] ?? null;
    $para = fn ($deger): string => number_format((float) $deger, 2, ',', '.').' TL';
@endphp

<x-filament-panels::page>
    <div class="restoran-cork-screen restoran-gun-sonu-mutabakat space-y-6">
        <div class="restoran-cork-toolbar max-w-sm">
            <x-filament::input.wrapper>
                <x-filament::input type="date" wire:model.live="tarih" />
            </x-filament::input.wrapper>
        </div>

        <div class="grid gap-4 md:grid-cols-4">
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Restoran tahsilatı</div>
                <div class="mt-1 text-2xl font-semibold">{{ $para($mutabakat['toplam_tahsilat'] ?? 0) }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Muhasebe hareketi</div>
                <div class="mt-1 text-2xl font-semibold">{{ $para($mutabakat['toplam_muhasebe'] ?? 0) }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Fark</div>
                <div class="mt-1 text-2xl font-semibold">{{ $para($mutabakat['toplam_fark'] ?? 0) }}</div>
            </div>
            <div class="restoran-cork-kpi-card rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
                <div class="text-sm text-gray-500">Durum</div>
                <div class="mt-1 text-2xl font-semibold {{ ($mutabakat['mutabik_mi'] ?? false) ? 'text-success-600' : 'text-danger-600' }}">
                    {{ ($mutabakat['mutabik_mi'] ?? false) ? 'Mutabık' : 'Fark var' }}
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h2 class="text-base font-semibold">Kanal kırılımı</h2>
            <div class="restoran-cork-table-wrap mt-4 overflow-x-auto">
                <table class="restoran-cork-table min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 text-left text-xs font-medium uppercase text-gray-500 dark:bg-gray-950">
                        <tr>
                            <th class="px-4 py-3">Kanal</th>
                            <th class="px-4 py-3">Tahsilat</th>
                            <th class="px-4 py-3">Muhasebe</th>
                            <th class="px-4 py-3">Fark</th>
                            <th class="px-4 py-3">Durum</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse($mutabakat['kanallar'] as $satir)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $satir['kanal_etiketi'] }}</td>
                                <td class="px-4 py-3">{{ (int) $satir['tahsilat_sayisi'] }} işlem / {{ $para($satir['tahsilat_tutari']) }}</td>
                                <td class="px-4 py-3">{{ (int) $satir['muhasebe_sayisi'] }} hareket / {{ $para($satir['muhasebe_tutari']) }}</td>
                                <td class="px-4 py-3">{{ $para($satir['fark']) }}</td>
                                <td class="px-4 py-3">
                                    <span class="restoran-cork-badge {{ $satir['mutabik_mi'] ? 'text-success-600' : 'text-danger-600' }}">
                                        {{ $satir['mutabik_mi'] ? 'Mutabık' : 'Kontrol gerekli' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-8 text-center text-gray-500">Kayıt yok.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-base font-semibold">Gün sonu kapanışı</h2>
                @if($kapanis)
                    <span class="text-sm text-gray-500">
                        Kaydedildi: {{ $kapanis->kapandi_at?->format('d.m.Y H:i') }}
                    </span>
                @endif
            </div>

            <div class="mt-4 grid gap-4 md:grid-cols-2">
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model.defer="farkAciklamasi" placeholder="Fark varsa açıklama" />
                </x-filament::input.wrapper>
                <x-filament::input.wrapper>
                    <x-filament::input type="text" wire:model.defer="notlar" placeholder="Kapanış notu" />
                </x-filament::input.wrapper>
            </div>

            @if($kapanis)
                <div class="mt-3 text-sm text-gray-500">
                    Son kayıt: {{ $para($kapanis->toplam_tahsilat) }} tahsilat, {{ $para($kapanis->toplam_muhasebe) }} muhasebe, {{ $para($kapanis->toplam_fark) }} fark.
                </div>
            @endif

            <div class="mt-4">
                <x-filament::button wire:click="gunSonunuKapat">
                    Gün sonunu kapat
                </x-filament::button>
            </div>
        </div>
    </div>
</x-filament-panels::page>
