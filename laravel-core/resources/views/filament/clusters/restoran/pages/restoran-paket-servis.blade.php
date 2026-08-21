@php
    $siparisler = $this->siparisler();
    $ozet = $this->durumOzeti();
    $kuryeler = $this->kuryeSecenekleri();
    $guncelleyebilir = $this->guncellemeYetkisiVarMi();
@endphp

<x-filament-panels::page>
    <div class="restoran-cork-screen restoran-paket-servis space-y-4">
    <div class="restoran-cork-kpi-grid grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Hazirlaniyor</div>
            <div class="mt-1 text-2xl font-semibold">{{ $ozet['hazirlaniyor'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Kuryede</div>
            <div class="mt-1 text-2xl font-semibold">{{ $ozet['kuryede'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Yolda</div>
            <div class="mt-1 text-2xl font-semibold">{{ $ozet['yolda'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Geciken</div>
            <div class="mt-1 text-2xl font-semibold text-danger-600 dark:text-danger-400">{{ $ozet['geciken'] ?? 0 }}</div>
        </div>
    </div>

    <div class="restoran-cork-toolbar grid gap-3 md:grid-cols-4">
        <x-filament::input.wrapper>
            <select wire:model.live="paketDurumFiltresi" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                <option value="aktif">Aktif siparişler</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonu::PAKET_DURUM_HAZIRLANIYOR }}">Hazırlanıyor</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI }}">Kuryede</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonu::PAKET_DURUM_YOLDA }}">Yolda</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonu::PAKET_DURUM_TESLIM_EDILDI }}">Teslim edildi</option>
                <option value="{{ \App\Models\Restoran\RestoranAdisyonu::PAKET_DURUM_IPTAL }}">İptal</option>
            </select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <select wire:model.live="siparisTipiFiltresi" class="fi-input block w-full rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                <option value="tum">Tüm kanallar</option>
                <option value="paket">Paket</option>
                <option value="online">Online</option>
            </select>
        </x-filament::input.wrapper>
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Sipariş</div>
            <div class="mt-1 text-xl font-semibold">{{ $ozet['toplam'] ?? 0 }}</div>
        </div>
        <div class="rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <div class="text-xs font-medium uppercase text-gray-500">Tutar</div>
            <div class="mt-1 text-xl font-semibold">{{ number_format((float) ($ozet['tutar'] ?? 0), 2, ',', '.') }} TL</div>
        </div>
    </div>

    <div class="restoran-cork-table-wrap overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900">
        <div class="overflow-x-auto">
            <table class="restoran-cork-table min-w-full divide-y divide-gray-200 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 text-left text-xs font-medium uppercase tracking-wide text-gray-500 dark:bg-gray-950">
                    <tr>
                        <th class="px-4 py-3">Adisyon</th>
                        <th class="px-4 py-3">Durum</th>
                        <th class="px-4 py-3">Kurye</th>
                        <th class="px-4 py-3">Telefon</th>
                        <th class="px-4 py-3">Tahmini teslimat</th>
                        <th class="px-4 py-3">Adres</th>
                        <th class="px-4 py-3">Toplam</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse($siparisler as $siparis)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $siparis->adisyon_no }}</td>
                            <td class="px-4 py-3"><span class="restoran-cork-badge">{{ str_replace('_', ' ', (string) $siparis->paket_durum) }}</span></td>
                            <td class="px-4 py-3">
                                @if($guncelleyebilir && ! $siparis->kurye_personel_id)
                                    <select wire:model="kuryeSecimleri.{{ $siparis->id }}" class="fi-input block w-44 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                                        <option value="">Seçiniz</option>
                                        @foreach($kuryeler as $id => $ad)
                                            <option value="{{ $id }}">{{ $ad }}</option>
                                        @endforeach
                                    </select>
                                @else
                                    {{ $siparis->kurye?->ad_soyad ?? '-' }}
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $siparis->teslimat_telefon ?: '-' }}</td>
                            <td class="px-4 py-3">
                                @if($siparis->tahmini_teslimat_at)
                                    <div @class([
                                        'font-medium',
                                        'text-danger-600 dark:text-danger-400' => $siparis->tahmini_teslimat_at->isPast(),
                                    ])>
                                        {{ $siparis->tahmini_teslimat_at->format('d.m H:i') }}
                                    </div>
                                    @if($siparis->teslimat_notu)
                                        <div class="mt-1 max-w-48 truncate text-xs text-gray-500">{{ $siparis->teslimat_notu }}</div>
                                    @endif
                                @elseif($guncelleyebilir)
                                    <div class="space-y-2">
                                        <input type="datetime-local" wire:model="tahminiTeslimatSecimleri.{{ $siparis->id }}" class="fi-input block w-44 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                                        <input type="text" wire:model="teslimatNotlari.{{ $siparis->id }}" placeholder="Not" class="fi-input block w-44 rounded-lg border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900" />
                                    </div>
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">{{ $siparis->teslimat_adresi ?: '-' }}</td>
                            <td class="px-4 py-3">{{ number_format((float) $siparis->genel_toplam, 2, ',', '.') }} TL</td>
                            <td class="px-4 py-3 text-right">
                                @if($guncelleyebilir)
                                    <div class="flex justify-end gap-2">
                                        @if(! $siparis->kurye_personel_id)
                                            <x-filament::button size="xs" color="gray" wire:click="kuryeAta({{ $siparis->id }})">Ata</x-filament::button>
                                        @endif
                                        @if(! $siparis->tahmini_teslimat_at)
                                            <x-filament::button size="xs" color="gray" wire:click="teslimatPlanla({{ $siparis->id }})">Planla</x-filament::button>
                                        @endif
                                        @if($siparis->kurye_personel_id && $siparis->paket_durum === \App\Models\Restoran\RestoranAdisyonu::PAKET_DURUM_KURYEE_ATANDI)
                                            <x-filament::button size="xs" color="warning" wire:click="yolaCikar({{ $siparis->id }})">Yola çıkar</x-filament::button>
                                        @elseif($siparis->paket_durum === \App\Models\Restoran\RestoranAdisyonu::PAKET_DURUM_YOLDA)
                                            <x-filament::button size="xs" color="success" wire:click="teslimEdildi({{ $siparis->id }})">Teslim</x-filament::button>
                                        @endif
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">Aktif paket servis siparişi yok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    </div>
</x-filament-panels::page>
