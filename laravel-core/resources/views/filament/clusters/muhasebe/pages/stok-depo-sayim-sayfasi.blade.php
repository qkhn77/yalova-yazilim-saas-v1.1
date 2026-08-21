<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section
            heading="Depo stok sayımı"
            description="Basit stok ürünlerinin seçilen depodaki fiili miktarını sisteme işleyin."
        >
            <form wire:submit="sayimiUygula" class="space-y-6">
                {{ $this->form }}

                <x-filament::button type="submit">
                    Sayımı uygula
                </x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section
            heading="Nasıl çalışır?"
            description="Sayım sonucu mevcut depo bakiyesiyle karşılaştırılır; yalnızca fark kadar stok hareketi oluşturulur."
        >
            <p class="text-sm text-gray-600 dark:text-gray-400">
                Parti ve seri numarası takipli ürünler bu ekrandan sayılmaz. Bu ürünlerde parti veya seri bazlı miktarların korunması için ilgili özel giriş akışı kullanılmalıdır.
            </p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
