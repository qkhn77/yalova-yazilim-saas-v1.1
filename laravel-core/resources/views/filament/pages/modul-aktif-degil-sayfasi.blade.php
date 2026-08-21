<x-filament-panels::page>
    <style>
        .modul-disabled-card { position: relative; overflow: hidden; border-radius: 1.5rem; border: 1px solid #dbe3ef; background: #fff; box-shadow: 0 18px 45px rgba(51, 65, 85, .12); }
        .modul-disabled-banner { position: relative; z-index: 2; display: flex; align-items: center; justify-content: space-between; gap: .75rem; padding: .75rem 2.5rem; color: #fff; background: linear-gradient(100deg, #1d4ed8, #2563eb 52%, #4f46e5); }
        .modul-disabled-banner-label { display: inline-flex; align-items: center; gap: .5rem; font-size: .75rem; font-weight: 700; letter-spacing: .02em; }
        .modul-disabled-banner-status { border: 1px solid rgba(255,255,255,.35); border-radius: 999px; padding: .25rem .7rem; background: rgba(255,255,255,.16); font-size: .68rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .modul-disabled-content { position: relative; z-index: 2; padding: 2.75rem 3rem 3rem; text-align: center; }
        .modul-disabled-icon { display: flex; width: 7rem; height: 7rem; margin: 0 auto 1.5rem; align-items: center; justify-content: center; border-radius: 999px; color: #fff; background: linear-gradient(140deg, #2563eb, #4f46e5 55%, #6366f1); box-shadow: 0 15px 30px rgba(37,99,235,.3); }
        .modul-disabled-icon-inner { display: flex; width: 4rem; height: 4rem; align-items: center; justify-content: center; border-radius: 1rem; background: rgba(255,255,255,.18); }
        .modul-disabled-title { margin: 0; color: #111827; font-size: 1.875rem; font-weight: 800; line-height: 1.2; }
        .modul-disabled-text { max-width: 42rem; margin: .75rem auto 0; color: #374151; font-size: .875rem; line-height: 1.75; }
        .modul-disabled-info { max-width: 36rem; margin: 1.5rem auto 0; padding: 1rem; border: 1px solid #dbe3ef; border-radius: 1rem; background: #f8fafc; text-align: left; }
        .modul-disabled-info-title { color: #111827; font-size: .875rem; font-weight: 700; }
        .modul-disabled-info-text { margin-top: .25rem; color: #4b5563; font-size: .75rem; line-height: 1.5; }
        .modul-disabled-actions { margin-top: 1.5rem; }
        @media (max-width: 640px) { .modul-disabled-banner { padding-inline: 1rem; } .modul-disabled-content { padding: 2rem 1rem 2.25rem; } .modul-disabled-title { font-size: 1.5rem; } }
        .dark .modul-disabled-card { border-color: #374151; background: #111827; }
        .dark .modul-disabled-title, .dark .modul-disabled-info-title { color: #f9fafb; }
        .dark .modul-disabled-text, .dark .modul-disabled-info-text { color: #d1d5db; }
        .dark .modul-disabled-info { border-color: #374151; background: #1f2937; }
        .modul-disabled-primary.fi-btn { border-color: #1d4ed8 !important; background: #2563eb !important; color: #fff !important; }
        .modul-disabled-primary.fi-btn:hover { background: #1d4ed8 !important; }
    </style>
    <div class="modul-disabled-card mx-auto w-full max-w-4xl">
        <div class="modul-disabled-banner">
            <div class="modul-disabled-banner-label">
                <x-filament::icon icon="heroicon-o-sparkles" class="h-4 w-4" />
                Modül kataloğu
            </div>
            <span class="modul-disabled-banner-status">Erişim kapalı</span>
        </div>
        <div class="modul-disabled-content">
            <div class="modul-disabled-icon">
                <div class="modul-disabled-icon-inner">
                    <x-filament::icon icon="heroicon-o-lock-closed" class="h-8 w-8" />
                </div>
            </div>

            <div>
                <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-indigo-50 px-3 py-1 text-xs font-semibold text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>
                    Modül erişimi
                </div>
                <h2 class="modul-disabled-title">{{ $modul?->ad ?? 'Bu modül' }} modülü etkin değil</h2>
                <p class="modul-disabled-text">Bu modül firma hesabınız için etkinleştirilmemiştir. Kullanabilmek için firma yöneticinizden erişim aktivasyonu talep edebilirsiniz.</p>

                <div class="mt-5 flex flex-wrap justify-center gap-2">
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">Güvenli erişim</span>
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">Firma bazlı aktivasyon</span>
                    <span class="rounded-full bg-gray-100 px-3 py-1.5 text-xs font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">Tek panel deneyimi</span>
                </div>

                <div class="modul-disabled-info">
                    <div class="flex items-start gap-3">
                        <x-filament::icon icon="heroicon-o-information-circle" class="mt-0.5 h-5 w-5 shrink-0 text-indigo-500" />
                        <div>
                            <p class="modul-disabled-info-title">Bu modülü kullanmak mı istiyorsunuz?</p>
                            <p class="modul-disabled-info-text">Firma yöneticinizle iletişime geçerek modül aktivasyonu hakkında bilgi alabilirsiniz.</p>
                        </div>
                    </div>
                </div>

                <div class="modul-disabled-actions flex flex-wrap justify-center gap-3">
                    <x-filament::button class="modul-disabled-primary" tag="a" :href="url(\App\Providers\Filament\AdminPanelProvider::adminPath())" icon="heroicon-m-arrow-left" color="primary">Yönetim paneline dön</x-filament::button>
                    <x-filament::button type="button" wire:click="basvuruFormunuAc" icon="heroicon-m-paper-airplane" color="gray">Başvuru formunu doldur</x-filament::button>
                </div>

                @if($basvuruFormuAcik)
                    <form wire:submit="basvuruGonder" class="mx-auto mt-6 max-w-xl rounded-2xl border border-indigo-200 bg-indigo-50/70 p-5 text-left dark:border-indigo-900 dark:bg-indigo-950/30">
                        <div class="mb-4">
                            <h3 class="text-sm font-bold text-gray-900 dark:text-white">Modül aktivasyon başvurusu</h3>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">Mesajınız firma yöneticilerinin Mesaj Merkezi ekranına iletilecek.</p>
                        </div>
                        <div class="space-y-4">
                            <div>
                                <label for="basvuru-mesaji" class="mb-1 block text-xs font-semibold text-gray-800 dark:text-gray-200">Başvuru mesajı</label>
                                <textarea id="basvuru-mesaji" wire:model="basvuruMesaji" rows="4" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Hangi modülü ve neden kullanmak istediğinizi yazın..."></textarea>
                                @error('basvuruMesaji') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <label for="basvuru-iletisim" class="mb-1 block text-xs font-semibold text-gray-800 dark:text-gray-200">İletişim bilgisi <span class="font-normal text-gray-500">(isteğe bağlı)</span></label>
                                <input id="basvuru-iletisim" wire:model="basvuruIletisim" type="text" class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white" placeholder="Telefon veya e-posta">
                                @error('basvuruIletisim') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            <x-filament::button type="button" wire:click="$set('basvuruFormuAcik', false)" color="gray">Vazgeç</x-filament::button>
                            <x-filament::button class="modul-disabled-primary" type="submit" icon="heroicon-m-paper-airplane">Başvuruyu gönder</x-filament::button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
