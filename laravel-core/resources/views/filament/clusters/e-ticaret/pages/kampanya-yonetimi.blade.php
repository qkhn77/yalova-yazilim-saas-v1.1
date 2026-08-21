<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen ecommerce-cork-screen space-y-6">
        <x-filament::section>
            <div class="text-sm text-gray-600">
                Kampanyalar bu fazda yonetim tarafinda tanimlanir. Sepette en avantajli tek indirim kurali
                sonraki adimda hesaplama servisine baglanacaktir.
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
