<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Kullanım kuralı</x-slot>
            <x-slot name="description">Pasifleştirilen türler yeni kayıtlarda görünmez; geçmiş masraflar ve raporları korunur.</x-slot>
            <p class="text-sm text-gray-600 dark:text-gray-300">
                Personel, elektrik, araç ve benzeri ana türler varsayılan olarak hazırlanır. İhtiyaç halinde işletmenize özel tür ekleyebilirsiniz.
            </p>
        </x-filament::section>

        <x-filament::section>
            {{ $this->table }}
        </x-filament::section>
    </div>
</x-filament-panels::page>
