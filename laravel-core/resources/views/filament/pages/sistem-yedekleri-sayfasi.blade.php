<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen ecommerce-cork-screen ecommerce-cork-card overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-4 border-b border-gray-200 p-4 dark:border-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Alınan SQL yedekleri</h2>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Web kök dizini dışında saklanan veritabanı yedeklerini yönetin.</p>
            </div>
            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center">
                <x-filament::button
                    icon="heroicon-o-circle-stack"
                    wire:click="yedekAl"
                    wire:loading.attr="disabled"
                    wire:target="yedekAl"
                >
                    <span wire:loading.remove wire:target="yedekAl">Yedek Al</span>
                    <span wire:loading wire:target="yedekAl">Yedek alınıyor...</span>
                </x-filament::button>

                <div class="w-full sm:w-80">
                    <x-filament::input.wrapper>
                        <x-filament::input type="search" wire:model.live.debounce.300ms="arama" placeholder="Dosya ara..." aria-label="Yedek ara" />
                    </x-filament::input.wrapper>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="fi-ta-table w-full table-auto text-start">
                <thead>
                    <tr class="border-b border-gray-200 dark:border-gray-700">
                        <th class="whitespace-nowrap px-4 py-3 text-sm font-semibold">Dosya</th>
                        <th class="whitespace-nowrap px-4 py-3 text-sm font-semibold">Tarih</th>
                        <th class="whitespace-nowrap px-4 py-3 text-sm font-semibold">Boyut</th>
                        <th class="px-4 py-3 text-end text-sm font-semibold">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($this->yedekler as $yedek)
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $yedek['name'] }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ date('d.m.Y H:i:s', $yedek['modified_at']) }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $this->formatBoyut($yedek['size']) }}</td>
                            <td class="px-4 py-3 text-end">
                                <div class="flex justify-end gap-2">
                                    <x-filament::button
                                        tag="a"
                                        size="sm"
                                        color="gray"
                                        icon="heroicon-o-arrow-down-tray"
                                        href="{{ route('admin.sistem-yedekleri.download', ['yedek' => $yedek['name']]) }}"
                                        :spa-mode="false"
                                        download="{{ $yedek['name'] }}"
                                    >İndir</x-filament::button>
                                    <x-filament::button size="sm" color="danger" icon="heroicon-o-trash" wire:click="sil(@js($yedek['name']))" wire:confirm="Bu yedeği silmek istediğinize emin misiniz?">Sil</x-filament::button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-10 text-center text-sm text-gray-500">Gösterilecek yedek bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($this->yedekler->hasPages())
            <div class="border-t border-gray-200 p-4 dark:border-gray-700">
                {{ $this->yedekler->links() }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
