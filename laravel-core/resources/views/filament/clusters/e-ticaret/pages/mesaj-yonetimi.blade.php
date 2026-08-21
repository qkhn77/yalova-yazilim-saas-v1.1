<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen ecommerce-cork-screen space-y-6">
        <x-filament::section>
            <div class="text-sm text-gray-600">
                Mesaj durumlari otomatik islenir. SLA suresi 12 saattir; sure gecerse konu SLA ihlal olarak isaretlenir.
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
