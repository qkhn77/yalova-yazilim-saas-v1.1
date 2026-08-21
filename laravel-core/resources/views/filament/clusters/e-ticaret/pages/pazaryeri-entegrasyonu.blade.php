<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen ecommerce-cork-screen space-y-6">
        <x-filament::section>
            <div class="text-sm text-gray-600">
                Siparis cekme periyodu varsayilan olarak 30 dakikadir. Stok ve fiyat senkronu tek yon olarak
                (sistemden pazaryerine) calisir.
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
