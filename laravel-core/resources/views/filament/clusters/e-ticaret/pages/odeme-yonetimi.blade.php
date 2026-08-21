<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen ecommerce-cork-screen space-y-6">
        <x-filament::section>
            <div class="text-sm text-gray-600">
                Bu ekranda odeme yontemleri tenant bazli yonetilir. Saglayici entegrasyon anahtarlari,
                3D Secure, taksit, yeniden deneme ve iade API ayarlari yontem bazinda tanimlanabilir.
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
