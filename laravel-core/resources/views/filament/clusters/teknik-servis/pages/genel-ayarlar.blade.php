<x-filament-panels::page>
    <div class="grid gap-3 md:grid-cols-3 xl:grid-cols-6">
        @foreach ($this->ayarOzeti() as $etiket => $deger)
            <div class="rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-950">
                <div class="text-xs font-medium text-gray-500 dark:text-gray-400">{{ $etiket }}</div>
                <div class="mt-1 truncate text-sm font-semibold text-gray-950 dark:text-white" title="{{ $deger }}">
                    {{ $deger }}
                </div>
            </div>
        @endforeach
    </div>

    <form wire:submit="kaydet" class="space-y-5">
        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="mb-4">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Kayıt varsayılanları</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Yeni servis kayıtlarında otomatik seçilecek ilk değerler.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Varsayılan servis durumu</span>
                    <select wire:model="data.teknik_servis_varsayilan_servis_durumu_id" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                        <option value="">Sistem varsayılanı</option>
                        @foreach ($this->servisDurumuSecenekleri() as $id => $etiket)
                            <option value="{{ $id }}">{{ $etiket }}</option>
                        @endforeach
                    </select>
                    @error('data.teknik_servis_varsayilan_servis_durumu_id') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Varsayılan öncelik</span>
                    <select wire:model="data.teknik_servis_varsayilan_oncelik" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                        @foreach ($this->oncelikSecenekleri() as $deger => $etiket)
                            <option value="{{ $deger }}">{{ $etiket }}</option>
                        @endforeach
                    </select>
                    @error('data.teknik_servis_varsayilan_oncelik') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Varsayılan servis kanalı</span>
                    <select wire:model="data.teknik_servis_varsayilan_servis_kanali" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                        @foreach ($this->servisKanaliSecenekleri() as $deger => $etiket)
                            <option value="{{ $deger }}">{{ $etiket }}</option>
                        @endforeach
                    </select>
                    @error('data.teknik_servis_varsayilan_servis_kanali') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Varsayılan müşteri onayı</span>
                    <select wire:model="data.teknik_servis_varsayilan_musteri_onay_durumu" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                        @foreach ($this->musteriOnaySecenekleri() as $deger => $etiket)
                            <option value="{{ $deger }}">{{ $etiket }}</option>
                        @endforeach
                    </select>
                    @error('data.teknik_servis_varsayilan_musteri_onay_durumu') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="mb-4">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Fiş numarası</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Fiş numarası yıl bazlı sayaçla üretilir; prefix değişirse yeni sayaç ayrı ilerler.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Fiş no prefix</span>
                    <input type="text" wire:model.defer="data.teknik_servis_fis_no_prefix" maxlength="24" class="mt-1 block w-full rounded-md border-gray-300 text-sm shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">Sadece harf, rakam, tire, alt çizgi ve nokta kullanın. Örn: YB-SER</span>
                    @error('data.teknik_servis_fis_no_prefix') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
                </label>

                <div>
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Format örneği</span>
                    <div class="mt-1 rounded-md border border-gray-200 bg-gray-50 px-3 py-2 font-mono text-sm font-semibold text-gray-900 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        {{ $fisNoOrnegi }}
                    </div>
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="mb-4">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Garanti ve bakım</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Bakım kayıtları ve garanti tarihleri için pratik varsayılanlar.</p>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Varsayılan bakım periyodu</span>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <input type="number" wire:model.defer="data.teknik_servis_varsayilan_bakim_periyot_ay" min="1" max="120" class="block w-full rounded-l-md border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                        <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300">ay</span>
                    </div>
                    @error('data.teknik_servis_varsayilan_bakim_periyot_ay') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
                </label>

                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-300">Varsayılan garanti süresi</span>
                    <div class="mt-1 flex rounded-md shadow-sm">
                        <input type="number" wire:model.defer="data.teknik_servis_varsayilan_garanti_ay" min="0" max="120" class="block w-full rounded-l-md border-gray-300 text-sm dark:border-white/10 dark:bg-gray-900 dark:text-white">
                        <span class="inline-flex items-center rounded-r-md border border-l-0 border-gray-300 bg-gray-50 px-3 text-sm text-gray-500 dark:border-white/10 dark:bg-gray-800 dark:text-gray-300">ay</span>
                    </div>
                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">0 yazılırsa garanti tarihleri otomatik doldurulmaz.</span>
                    @error('data.teknik_servis_varsayilan_garanti_ay') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
                </label>
            </div>
        </section>

        <section class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-950">
            <div class="mb-4">
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Muhasebe bağlantısı</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Teknik servis durum değişimlerinde fatura senkronunun nasıl çalışacağını belirler.</p>
            </div>

            <div class="grid gap-3">
                <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="data.teknik_servis_bekleyen_fatura_senkron_aktif_mi" class="mt-0.5 rounded border-gray-300 text-primary-600 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <span>Teslim bekleyen/teslim edilen durumlarında bekleyen fatura oluştur</span>
                </label>

                <label class="flex items-start gap-3 text-sm text-gray-700 dark:text-gray-200">
                    <input type="checkbox" wire:model="data.teknik_servis_teslimde_faturayi_onayla_mi" class="mt-0.5 rounded border-gray-300 text-primary-600 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <span>Teslim edildi durumunda faturayı onaylı satış faturasına çevir</span>
                </label>
            </div>
        </section>

        <div class="flex justify-start">
            <button type="submit" wire:loading.attr="disabled" wire:target="kaydet" class="rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 disabled:opacity-60">
                Kaydet
            </button>
        </div>
    </form>
</x-filament-panels::page>
