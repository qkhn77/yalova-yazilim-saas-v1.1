@php
    /** @var \App\Filament\Clusters\MasrafTakip\Pages\MasrafRaporlariSayfasi $this */
    $raporHazir = $this->raporHazir;
    $ozet = $raporHazir ? $this->ozet() : [];
    $kategoriOzeti = $raporHazir ? $this->kategoriOzeti() : [];
    $projeButceOzeti = $raporHazir ? $this->projeButceGerceklesenOzeti() : [];
    $kategoriButceOzeti = $raporHazir ? $this->kategoriButceGerceklesenOzeti() : [];
    $personelGiderleri = $raporHazir ? $this->personelGiderOzeti() : [];
    $teknikServisGiderleri = $raporHazir ? $this->teknikServisGiderOzeti() : [];
    $masrafHareketleri = $raporHazir ? $this->masrafHareketleri() : [];
    $para = static fn (mixed $tutar, string $paraBirimi): string => number_format((float) $tutar, 2, ',', '.').' '.strtoupper($paraBirimi);
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
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
                <div class="flex flex-wrap justify-end gap-3">
                    <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-path" wire:click="filtreleriSifirla">
                        Bu aya dön
                    </x-filament::button>
                    <x-filament::button type="submit" icon="heroicon-o-funnel" wire:loading.attr="disabled" wire:target="filtreleriUygula">
                        Raporu getir
                    </x-filament::button>
                    <x-filament::button type="button" color="gray" icon="heroicon-o-arrow-down-tray" wire:click="masrafCsvIndir" wire:loading.attr="disabled" wire:target="masrafCsvIndir">
                        CSV indir
                    </x-filament::button>
                    <x-filament::button type="button" color="success" icon="heroicon-o-document-chart-bar" wire:click="masrafExcelCsvIndir" wire:loading.attr="disabled" wire:target="masrafExcelCsvIndir">
                        Excel uyumlu CSV
                    </x-filament::button>
                </div>
            </form>
        </x-filament::section>

        <div wire:init="raporuYukle">
        @if (! $raporHazir)
            <x-filament::section>
                <p class="text-sm text-gray-500 dark:text-gray-400">Rapor verileri hazırlanıyor…</p>
            </x-filament::section>
        @else
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @forelse ($ozet as $satir)
                <x-filament::section>
                    <p class="text-sm text-gray-500 dark:text-gray-400">{{ $satir['para_birimi'] }} {{ ($this->filtreler['durum'] ?? 'aktif') === 'tumu' ? 'masraf' : (($this->filtreler['durum'] ?? 'aktif') === 'iptal' ? 'iptal masraf' : 'aktif masraf') }}</p>
                    <p class="mt-2 text-2xl font-semibold text-danger-700 dark:text-danger-400">{{ $para($satir['toplam'], $satir['para_birimi']) }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($satir['adet'], 0, ',', '.') }} kayıt</p>
                </x-filament::section>
            @empty
                <x-filament::section class="sm:col-span-2 xl:col-span-4">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Seçilen filtrelerle masraf bulunamadı.</p>
                </x-filament::section>
            @endforelse
        </div>

        @if ($kategoriOzeti !== [])
            <x-filament::section>
                <x-slot name="heading">Kategori bazlı masraf özeti</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[40rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left dark:bg-white/5">
                                <th class="px-4 py-3 font-medium">Ana kategori</th>
                                <th class="px-4 py-3 font-medium">Masraf türü</th>
                                <th class="px-4 py-3 text-right font-medium">Kayıt</th>
                                <th class="px-4 py-3 text-right font-medium">Toplam</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($kategoriOzeti as $satir)
                                <tr>
                                    <td class="px-4 py-3 text-gray-500">{{ $satir['ana_kategori'] ?: '—' }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $satir['kategori'] }} <span class="text-xs text-gray-500">({{ $satir['para_birimi'] }})</span></td>
                                    <td class="px-4 py-3 text-right">{{ number_format($satir['adet'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-danger-700 dark:text-danger-400">{{ $para($satir['toplam'], $satir['para_birimi']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if ($projeButceOzeti !== [])
            <x-filament::section>
                <x-slot name="heading">Proje bütçe / gerçekleşen</x-slot>
                <x-slot name="description">Gerçekleşen tutar, seçilen tarih aralığındaki aktif masraflardan hesaplanır ve proje para birimiyle eşleştirilir.</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[48rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left dark:bg-white/5">
                                <th class="px-4 py-3 font-medium">Kod</th>
                                <th class="px-4 py-3 font-medium">Proje</th>
                                <th class="px-4 py-3 text-right font-medium">Bütçe</th>
                                <th class="px-4 py-3 text-right font-medium">Gerçekleşen</th>
                                <th class="px-4 py-3 text-right font-medium">Kalan</th>
                                <th class="px-4 py-3 text-right font-medium">Kayıt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($projeButceOzeti as $satir)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $satir['kod'] }}</td>
                                    <td class="px-4 py-3">{{ $satir['proje'] }} <span class="text-xs text-gray-500">({{ $satir['para_birimi'] }})</span></td>
                                    <td class="px-4 py-3 text-right">{{ $satir['butce'] === null ? 'Belirtilmedi' : $para($satir['butce'], $satir['para_birimi']) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-danger-700 dark:text-danger-400">{{ $para($satir['gerceklesen'], $satir['para_birimi']) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ $satir['kalan'] !== null && (float) $satir['kalan'] < 0 ? 'text-danger-700 dark:text-danger-400' : 'text-success-700 dark:text-success-400' }}">{{ $satir['kalan'] === null ? '—' : $para($satir['kalan'], $satir['para_birimi']) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($satir['adet'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if ($kategoriButceOzeti !== [])
            <x-filament::section>
                <x-slot name="heading">Kategori bütçe / gerçekleşen</x-slot>
                <x-slot name="description">Seçilen tarih aralığıyla kesişen aktif ve kapalı kategori bütçeleri, aynı dönemdeki aktif masraflarla karşılaştırılır.</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[56rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left dark:bg-white/5">
                                <th class="px-4 py-3 font-medium">Ana kategori</th>
                                <th class="px-4 py-3 font-medium">Masraf türü</th>
                                <th class="px-4 py-3 font-medium">Dönem</th>
                                <th class="px-4 py-3 text-right font-medium">Bütçe</th>
                                <th class="px-4 py-3 text-right font-medium">Gerçekleşen</th>
                                <th class="px-4 py-3 text-right font-medium">Kalan</th>
                                <th class="px-4 py-3 text-right font-medium">Kayıt</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($kategoriButceOzeti as $satir)
                                <tr>
                                    <td class="px-4 py-3 text-gray-500">{{ $satir['ana_kategori'] ?: '—' }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $satir['kategori'] }} <span class="text-xs text-gray-500">({{ $satir['para_birimi'] }})</span></td>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ \Carbon\Carbon::parse($satir['baslangic'])->format('d.m.Y') }} - {{ \Carbon\Carbon::parse($satir['bitis'])->format('d.m.Y') }}</td>
                                    <td class="px-4 py-3 text-right">{{ $para($satir['butce'], $satir['para_birimi']) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-danger-700 dark:text-danger-400">{{ $para($satir['gerceklesen'], $satir['para_birimi']) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold {{ (float) $satir['kalan'] < 0 ? 'text-danger-700 dark:text-danger-400' : 'text-success-700 dark:text-success-400' }}">{{ $para($satir['kalan'], $satir['para_birimi']) }}</td>
                                    <td class="px-4 py-3 text-right">{{ number_format($satir['adet'], 0, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if ($personelGiderleri !== [])
            <x-filament::section>
                <x-slot name="heading">Personel giderleri (otomatik)</x-slot>
                <x-slot name="description">Personel Takip modülündeki maaş ve avans kayıtları masraf kaydı açılmadan bu rapora dahil edilir; böylece çift kayıt oluşmaz.</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[32rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left dark:bg-white/5">
                                <th class="px-4 py-3 font-medium">Kalem</th>
                                <th class="px-4 py-3 text-right font-medium">Kayıt</th>
                                <th class="px-4 py-3 text-right font-medium">Toplam</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($personelGiderleri as $satir)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $satir['kalem'] }} <span class="text-xs text-gray-500">({{ $satir['para_birimi'] }})</span></td>
                                    <td class="px-4 py-3 text-right">{{ number_format($satir['adet'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-danger-700 dark:text-danger-400">{{ $para($satir['toplam'], $satir['para_birimi']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        @if ($teknikServisGiderleri !== [])
            <x-filament::section>
                <x-slot name="heading">Teknik servis giderleri (otomatik)</x-slot>
                <x-slot name="description">Teknik Servis modülünde oluşturulan aktif gider faturaları masraf kaydı açılmadan rapora dahil edilir. Aynı servis için manuel masraf varsa otomatik satır mükerrerliği önlemek için hariç tutulur.</x-slot>
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[32rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left dark:bg-white/5">
                                <th class="px-4 py-3 font-medium">Kalem</th>
                                <th class="px-4 py-3 text-right font-medium">Kayıt</th>
                                <th class="px-4 py-3 text-right font-medium">Toplam</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($teknikServisGiderleri as $satir)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $satir['kalem'] }} <span class="text-xs text-gray-500">({{ $satir['para_birimi'] }})</span></td>
                                    <td class="px-4 py-3 text-right">{{ number_format($satir['adet'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-danger-700 dark:text-danger-400">{{ $para($satir['toplam'], $satir['para_birimi']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">Filtreye uyan masraf hareketleri</x-slot>
            <x-slot name="description">Seçtiğiniz tarih, proje, kategori ve kayıt durumu filtrelerine uyan masraf kayıtları sayfalı olarak listelenir.</x-slot>
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div class="w-full sm:max-w-md">
                    <label for="hareket-arama" class="sr-only">Masraf hareketlerinde ara</label>
                    <div class="flex gap-2">
                        <input
                            id="hareket-arama"
                            type="search"
                            wire:model.live.debounce.400ms="hareketArama"
                            placeholder="Açıklama, proje veya kategori ara…"
                            class="fi-input block min-w-0 flex-1 rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                        >
                        @if (trim($hareketArama) !== '')
                            <x-filament::button type="button" size="sm" color="gray" wire:click="$set('hareketArama', '')">
                                Temizle
                            </x-filament::button>
                        @endif
                    </div>
                </div>
                <div class="text-sm text-gray-500 dark:text-gray-400">
                    {{ number_format($masrafHareketleri->total(), 0, ',', '.') }} kayıt
                </div>
            </div>
            @if ($masrafHareketleri->isNotEmpty())
                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-white/10">
                    <table class="w-full min-w-[78rem] text-sm">
                        <thead>
                            <tr class="bg-gray-50 text-left dark:bg-white/5">
                                <th class="px-4 py-3 font-medium">Tarih</th>
                                <th class="px-4 py-3 font-medium">Proje</th>
                                <th class="px-4 py-3 font-medium">Ana kategori</th>
                                <th class="px-4 py-3 font-medium">Masraf türü</th>
                                <th class="px-4 py-3 font-medium">Açıklama</th>
                                <th class="px-4 py-3 text-right font-medium">Tutar</th>
                                <th class="px-4 py-3 font-medium">Durum</th>
                                <th class="px-4 py-3 text-right font-medium">İşlem</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($masrafHareketleri as $hareket)
                                <tr>
                                    <td class="px-4 py-3 whitespace-nowrap">{{ optional($hareket->tarih)->format('d.m.Y') }}</td>
                                    <td class="px-4 py-3">
                                        @if ($hareket->isletmeProjesi)
                                            <a
                                                href="{{ \App\Filament\Clusters\ProjeYonetimi\Pages\ProjeRaporlariSayfasi::getUrl(['proje_id' => $hareket->isletmeProjesi->id]) }}"
                                                class="font-medium text-primary-600 hover:underline"
                                            >{{ $hareket->isletmeProjesi->ad }}</a>
                                            <a
                                                href="{{ \App\Filament\Clusters\ProjeYonetimi\Pages\ProjeRaporlariSayfasi::getUrl(['proje_id' => $hareket->isletmeProjesi->id]) }}"
                                                class="block text-xs text-gray-500 hover:text-primary-600 hover:underline"
                                            >{{ $hareket->isletmeProjesi->kod }}</a>
                                        @else
                                            <span class="text-gray-500">Projesiz</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-gray-500">{{ $hareket->kategori?->ustKategori?->ad ?: '—' }}</td>
                                    <td class="px-4 py-3 font-medium">{{ $hareket->kategori?->ad ?: '—' }}</td>
                                    <td class="px-4 py-3">{{ $hareket->aciklama }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-danger-700 dark:text-danger-400">{{ $para($hareket->tutar, $hareket->para_birimi ?: 'TRY') }}</td>
                                    <td class="px-4 py-3">
                                        <x-filament::badge :color="$hareket->durum === \App\Models\Muhasebe\Masraf::DURUM_IPTAL ? 'danger' : 'success'">
                                            {{ $hareket->durum === \App\Models\Muhasebe\Masraf::DURUM_IPTAL ? 'İptal' : 'Aktif' }}
                                        </x-filament::badge>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a href="{{ \App\Filament\Clusters\MasrafTakip\Pages\MasrafDetaySayfasi::getUrl(['record' => $hareket->id]) }}" title="Görüntüle" aria-label="Görüntüle" class="inline-flex items-center justify-center rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-primary-600 dark:hover:bg-white/10">
                                            <x-heroicon-o-eye class="h-5 w-5" />
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-4">
                    {{ $masrafHareketleri->links() }}
                </div>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Seçilen filtrelerle eşleşen masraf hareketi bulunamadı.</p>
            @endif
        </x-filament::section>
        @endif
        </div>
    </div>
</x-filament-panels::page>
