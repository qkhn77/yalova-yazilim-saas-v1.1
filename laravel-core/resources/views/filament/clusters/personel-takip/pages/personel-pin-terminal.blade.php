<x-filament-panels::page>
    <div class="personel-cork-screen">
    <div class="personel-cork-terminal-layout grid gap-4 lg:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="personel-cork-terminal-card">
        <x-filament::section>
            <x-slot name="heading">PIN ile giriş-çıkış</x-slot>
            <x-slot name="description">Kasa, tablet veya yönetici panelinden hızlı personel puantajı.</x-slot>

            <form wire:submit.prevent="pinIslemiYap" class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit" icon="heroicon-o-key" color="warning">
                    İşlemi kaydet
                </x-filament::button>
            </form>
        </x-filament::section>
        </div>

        <div class="personel-cork-terminal-card">
        <x-filament::section>
            <x-slot name="heading">Son işlem</x-slot>

            @if($sonIslem)
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">İşlem</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">
                            {{ ($sonIslem['tip'] ?? null) === 'cikis' ? 'Çıkış' : 'Giriş' }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Personel</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $sonIslem['personel'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Şube</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $sonIslem['sube'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Giriş</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $sonIslem['giris_at'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Çıkış</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $sonIslem['cikis_at'] ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Onay</dt>
                        <dd class="font-semibold text-gray-950 dark:text-white">{{ $sonIslem['onay_durumu'] ?? '-' }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">Henüz işlem yapılmadı.</p>
            @endif
        </x-filament::section>
        </div>
    </div>
    </div>
</x-filament-panels::page>
