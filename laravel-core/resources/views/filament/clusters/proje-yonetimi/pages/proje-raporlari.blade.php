@php
    $satirlar = $this->raporSatirlari();
    $para = static fn (mixed $tutar, string $birim): string => number_format((float) $tutar, 8, ',', '.').' '.strtoupper($birim);
@endphp

<x-filament-panels::page>
    <div class="mx-auto w-full max-w-6xl space-y-6">
        <x-filament::section>
            <x-slot name="heading">Rapor filtresi</x-slot>
            <form wire:submit="filtreleriUygula" class="space-y-4">
                {{ $this->filtreForm }}
                <div class="flex flex-wrap items-center gap-2 border-t border-gray-200 pt-4 dark:border-white/10">
                    <span class="mr-1 text-sm font-medium text-gray-600 dark:text-gray-300">Hızlı tarih:</span>
                    <x-filament::button type="button" size="sm" color="gray" wire:click="hizliTarihFiltrele('bugun')">Bugün</x-filament::button>
                    <x-filament::button type="button" size="sm" color="gray" wire:click="hizliTarihFiltrele('bu_hafta')">Bu hafta</x-filament::button>
                    <x-filament::button type="button" size="sm" color="gray" wire:click="hizliTarihFiltrele('bu_ay')">Bu ay</x-filament::button>
                    <x-filament::button type="button" size="sm" color="gray" wire:click="hizliTarihFiltrele('son_30_gun')">Son 30 gün</x-filament::button>
                    <x-filament::button type="button" size="sm" color="gray" wire:click="hizliTarihFiltrele('bu_yil')">Bu yıl</x-filament::button>
                </div>
                <div class="flex justify-end gap-3">
                    <x-filament::button type="button" color="gray" wire:click="filtreleriSifirla">Sıfırla</x-filament::button>
                    <x-filament::button type="submit" icon="heroicon-o-funnel">Raporu getir</x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Proje finans özeti</x-slot>
            <x-slot name="description">Masraf ayrı, net finans gelir eksi ödeme olarak gösterilir. Para birimleri birbirine eklenmez.</x-slot>
            @if (($uyumsuzlukSayisi = $this->projeBaglantiUyumsuzlukSayisi()) > 0)
                <div class="mb-4 rounded-lg border border-warning-300 bg-warning-50 px-3 py-2 text-sm text-warning-800 dark:border-warning-500/30 dark:bg-warning-500/10 dark:text-warning-200">
                    {{ number_format($uyumsuzlukSayisi, 0, ',', '.') }} masraf–fatura bağlantısında proje bilgisi eşleşmiyor. Bu kayıtları kontrol edin.
                </div>
            @endif
            @php($ozetler = $this->raporOzetleri($satirlar))
            <div class="mb-4 flex flex-wrap gap-3">
                @forelse ($ozetler as $ozet)
                    <div class="min-w-0 flex-1 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-white/10 dark:bg-white/5">
                        <div class="flex items-center justify-between text-sm font-semibold text-gray-950 dark:text-white">
                            <span>{{ $ozet['para_birimi'] }} toplamı</span>
                            <span class="text-xs font-normal text-gray-500">Özet</span>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                            <div><div class="text-gray-500">Masraf</div><div class="font-semibold">{{ $para($ozet['masraf'], $ozet['para_birimi']) }}</div></div>
                            <div><div class="text-gray-500">Net finans</div><div class="font-semibold">{{ $para($ozet['net'], $ozet['para_birimi']) }}</div></div>
                            <div><div class="text-gray-500">Bütçe</div><div class="font-semibold">{{ $para($ozet['butce'], $ozet['para_birimi']) }}</div></div>
                            <div><div class="text-gray-500">Kalan bütçe</div><div class="font-semibold">{{ $para($ozet['kalan'], $ozet['para_birimi']) }}</div></div>
                        </div>
                    </div>
                @empty
                    <div class="w-full rounded-lg border border-dashed border-gray-300 p-4 text-sm text-gray-500">Seçilen filtrelerle özet oluşturulamadı.</div>
                @endforelse
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[76rem] text-sm">
                    <thead class="bg-gray-50 text-left dark:bg-white/5"><tr>
                        <th class="px-4 py-3">Kod</th><th class="px-4 py-3">Proje</th><th class="px-4 py-3">Durum</th><th class="px-4 py-3 text-right">Bütçe</th><th class="px-4 py-3 text-right">Masraf</th><th class="px-4 py-3 text-right">Gelir</th><th class="px-4 py-3 text-right">Kâr / zarar</th><th class="px-4 py-3 text-right">Kâr marjı</th><th class="px-4 py-3 text-right">Ödeme</th><th class="px-4 py-3 text-right">Net</th><th class="px-4 py-3 text-right">Kalan</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($satirlar as $satir)
                            <tr><td class="px-4 py-3 font-semibold">{{ $satir['kod'] }}</td><td class="px-4 py-3">{{ $satir['proje'] }}</td><td class="px-4 py-3">{{ $satir['durum'] }}</td><td class="px-4 py-3 text-right">{{ $satir['butce'] === null ? '—' : $para($satir['butce'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right">{{ $para($satir['masraf'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right text-success-600">{{ $para($satir['gelir'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right font-semibold {{ bccomp($satir['kar'], '0', 2) < 0 ? 'text-danger-600' : 'text-success-600' }}">{{ $para($satir['kar'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right">{{ $satir['kar_marji'] === null ? '—' : number_format((float) $satir['kar_marji'], 2, ',', '.').' %' }}</td><td class="px-4 py-3 text-right">{{ $para($satir['odeme'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right font-semibold">{{ $para($satir['net'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right">{{ $satir['kalan'] === null ? '—' : $para($satir['kalan'], $satir['para_birimi']) }}</td></tr>
                        @empty
                            <tr><td colspan="11" class="px-4 py-8 text-center text-gray-500">Seçilen filtrelerle proje bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        @php($hareketler = $this->projeHareketleri())
        <x-filament::section>
            <x-slot name="heading">Proje hareketleri</x-slot>
            <x-slot name="description">Seçilen filtrelere uyan, proje bağlantısı bulunan tüm masraf, fatura, finans, cari ve stok hareketleri listelenir. Para birimleri birbirine çevrilmez.</x-slot>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500">
                    <span>Toplam {{ number_format($hareketler->total(), 0, ',', '.') }} kayıt</span>
                    <label class="flex items-center gap-2">
                        <span>Sayfa başına</span>
                        <select wire:model.live="hareketlerPerPage" class="rounded-lg border-gray-300 bg-white text-sm dark:border-white/10 dark:bg-white/5">
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                        </select>
                    </label>
                </div>
                <div class="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
                    <div class="flex gap-2">
                        <input
                            type="search"
                            wire:model.live.debounce.400ms="hareketArama"
                            placeholder="Hareketlerde ara…"
                            aria-label="Proje hareketlerinde ara"
                            class="fi-input block min-w-0 flex-1 rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white sm:w-64"
                        >
                        @if (trim($hareketArama) !== '')
                            <x-filament::button type="button" size="sm" color="gray" wire:click="$set('hareketArama', '')">
                                Temizle
                            </x-filament::button>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-document-arrow-down" wire:click="projeHareketleriCsvIndir">CSV indir</x-filament::button>
                        <x-filament::button type="button" size="sm" color="gray" icon="heroicon-o-table-cells" wire:click="projeHareketleriCsvIndir(true)">Excel indir</x-filament::button>
                    </div>
                </div>
            </div>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[92rem] text-sm">
                    <thead class="bg-gray-50 text-left dark:bg-white/5"><tr>
                        <th class="px-4 py-3">Tarih</th><th class="px-4 py-3">Hareket türü</th><th class="px-4 py-3">Proje</th><th class="px-4 py-3">Belge / kayıt</th><th class="px-4 py-3">Açıklama</th><th class="px-4 py-3">Yön</th><th class="px-4 py-3 text-right">Miktar</th><th class="px-4 py-3 text-right">Tutar</th><th class="px-4 py-3">Para birimi</th><th class="px-4 py-3 text-center">İşlem</th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($hareketler as $hareket)
                            <tr>
                                <td class="whitespace-nowrap px-4 py-3">{{ date('d.m.Y H:i', strtotime((string) $hareket->tarih)) }}</td>
                                <td class="px-4 py-3 font-medium">{{ $hareket->hareket_turu }}</td>
                                <td class="px-4 py-3"><div class="font-medium">{{ $hareket->proje }}</div><div class="text-xs text-gray-500">{{ $hareket->proje_kodu }}</div></td>
                                <td class="px-4 py-3">{{ $hareket->belge }}</td>
                                <td class="max-w-xs truncate px-4 py-3" title="{{ $hareket->aciklama }}">{{ $hareket->aciklama ?: '—' }}</td>
                                <td class="px-4 py-3">{{ $hareket->yon }}</td>
                                <td class="px-4 py-3 text-right">{{ $hareket->miktar === null ? '—' : $hareket->miktar }}</td>
                                <td class="px-4 py-3 text-right font-semibold">{{ $para($hareket->tutar, strtoupper((string) ($hareket->para_birimi ?: 'TRY'))) }}</td>
                                <td class="px-4 py-3">{{ strtoupper((string) ($hareket->para_birimi ?: 'TRY')) }}</td>
                                <td class="px-4 py-3 text-center">
                                    <x-filament::icon-button
                                        tag="a"
                                        :href="$this->projeHareketDetayUrl($hareket)"
                                        icon="heroicon-o-eye"
                                        label="Hareket detayını görüntüle"
                                        color="gray"
                                    />
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="px-4 py-8 text-center text-gray-500">Seçilen filtrelere uyan proje bağlantılı hareket bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($hareketler->hasPages())
                <div class="mt-4 flex flex-col gap-3 border-t border-gray-200 pt-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between">
                    <div class="text-sm text-gray-500">{{ number_format($hareketler->firstItem(), 0, ',', '.') }}–{{ number_format($hareketler->lastItem(), 0, ',', '.') }} arası gösteriliyor</div>
                    <div>{{ $hareketler->links() }}</div>
                </div>
            @endif
        </x-filament::section>

        @php($aylik = $this->aylikOzeti())
        <x-filament::section>
            <x-slot name="heading">Aylık karşılaştırma</x-slot>
            <x-slot name="description">Seçilen aralıktaki aktif hareketlerin ay ve para birimi bazındaki özeti; net finans gelir eksi ödemedir.</x-slot>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[60rem] text-sm">
                    <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-3">Dönem</th><th class="px-4 py-3">Para birimi</th><th class="px-4 py-3 text-right">Masraf</th><th class="px-4 py-3 text-right">Gelir</th><th class="px-4 py-3 text-right">Ödeme</th><th class="px-4 py-3 text-right">Net</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($aylik as $satir)
                            <tr><td class="px-4 py-3">{{ $satir['donem'] }}</td><td class="px-4 py-3">{{ $satir['para_birimi'] }}</td><td class="px-4 py-3 text-right">{{ $para($satir['masraf'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right text-success-600">{{ $para($satir['gelir'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right">{{ $para($satir['odeme'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right font-semibold">{{ $para($satir['net'], $satir['para_birimi']) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Seçilen dönemde hareket bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        @php($cariler = $this->cariOzeti())
        <x-filament::section>
            <x-slot name="heading">Proje bazlı cari özeti</x-slot>
            <x-slot name="description">Yalnızca projeye bağlı aktif cari hareketleri gösterilir. Borç ve alacak para birimlerine göre ayrıdır.</x-slot>
            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                <table class="w-full min-w-[56rem] text-sm">
                    <thead class="bg-gray-50 text-left dark:bg-white/5"><tr><th class="px-4 py-3">Cari</th><th class="px-4 py-3">Proje</th><th class="px-4 py-3">Para birimi</th><th class="px-4 py-3 text-right">Borç</th><th class="px-4 py-3 text-right">Alacak</th><th class="px-4 py-3 text-right">Net</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($cariler as $satir)
                            <tr><td class="px-4 py-3 font-medium">{{ $satir['cari'] }}</td><td class="px-4 py-3">{{ $satir['proje'] }}</td><td class="px-4 py-3">{{ $satir['para_birimi'] }}</td><td class="px-4 py-3 text-right">{{ $para($satir['borc'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right text-success-600">{{ $para($satir['alacak'], $satir['para_birimi']) }}</td><td class="px-4 py-3 text-right font-semibold">{{ $para($satir['net'], $satir['para_birimi']) }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">Seçilen dönemde projeye bağlı cari hareket bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
