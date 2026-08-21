<x-filament-panels::page>
    <div class="ecommerce-web-cork-screen ecommerce-cork-screen space-y-6">
        <x-filament::section>
            <div class="grid gap-4 lg:grid-cols-3">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="text-sm font-semibold text-gray-900">Yurt İçi ve Yurt Dışı Kapsam</div>
                    <p class="mt-2 text-sm text-gray-600">
                        Her kargo yöntemi için ülke kapsamı, hariç ülkeler, şehir ve posta kodu kuralları tanımlayabilirsiniz.
                    </p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="text-sm font-semibold text-gray-900">Profesyonel Ücretlendirme</div>
                    <p class="mt-2 text-sm text-gray-600">
                        Sabit ücret, desi bazlı fiyat, sipariş tutarı aralığı, ücretsiz kargo eşiği ve min/max limitler birlikte çalışır.
                    </p>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="text-sm font-semibold text-gray-900">Operasyon ve Entegrasyon</div>
                    <p class="mt-2 text-sm text-gray-600">
                        Servis kodu, gönderici ülke, iade ayarı, müşteri numarası ve API bilgileri ile canlı operasyona hazırlanır.
                    </p>
                </div>
            </div>
            <div class="mt-4 text-sm text-gray-600">
                Üstteki <strong>Yeni Kargo Yöntemi</strong> butonuyla kayıt oluşturabilir, tablodaki satıra tıklayarak düzenleme formunu açabilirsiniz.
            </div>
        </x-filament::section>

        <x-filament::section>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Kargo Simülasyonu</div>
                    <div class="mt-1 text-sm text-gray-500">
                        Checkout'ta görünecek yöntemleri ülke, şehir, tutar ve desi bilgisiyle test edin.
                    </div>
                </div>

                <x-filament::button
                    type="button"
                    color="gray"
                    icon="{{ $simulasyonAcik ? 'heroicon-o-eye-slash' : 'heroicon-o-truck' }}"
                    wire:click="simulasyonAlaniniDegistir"
                    wire:loading.attr="disabled"
                    wire:target="simulasyonAlaniniDegistir"
                >
                    {{ $simulasyonAcik ? 'Simülasyonu Gizle' : 'Simülasyonu Aç' }}
                </x-filament::button>
            </div>

            @if ($simulasyonAcik)
                <form wire:submit="kargoSimulasyonuCalistir" class="mt-4 space-y-4">
                    {{ $this->simulasyonForm }}

                    <div class="flex flex-wrap items-center gap-3">
                        <x-filament::button type="submit" icon="heroicon-o-truck">
                            Simülasyonu Çalıştır
                        </x-filament::button>
                        <div class="text-sm text-gray-500">
                            Ülke, şehir, posta kodu, sipariş tutarı ve desi bilgisine göre checkout'ta görünecek yöntemleri test eder.
                        </div>
                    </div>
                </form>

                @if ($simulasyonCalistirildi)
                    <div class="mt-6">
                        @if ($simulasyonSonuclari !== [])
                            <div class="grid gap-4 lg:grid-cols-2">
                                @foreach ($simulasyonSonuclari as $sonuc)
                                    <div class="rounded-xl border border-gray-200 bg-white p-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <div class="text-base font-semibold text-gray-900">{{ $sonuc['ad'] }}</div>
                                                <div class="mt-1 text-sm text-gray-500">
                                                    Kod: {{ $sonuc['kod'] ?: '-' }} · Hizmet: {{ $sonuc['hizmet_tipi'] }}
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-base font-semibold text-primary-600">{{ $sonuc['ucret_formatli'] }}</div>
                                                <div class="mt-1 text-xs text-gray-500">{{ $sonuc['tahmini_teslim'] }}</div>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid gap-3 md:grid-cols-2">
                                            <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                                                <div class="font-medium text-gray-900">Kapsam</div>
                                                <div class="mt-1">{{ $sonuc['kapsam_ozeti'] }}</div>
                                            </div>
                                            <div class="rounded-lg bg-gray-50 p-3 text-sm text-gray-700">
                                                <div class="font-medium text-gray-900">Operasyon</div>
                                                <div class="mt-1">{{ $sonuc['entegrasyon'] }}</div>
                                                <div class="mt-1 text-xs text-gray-500">
                                                    {{ $sonuc['test_modu'] ? 'Test modunda' : 'Canlı / manuel kullanıma hazır' }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
                                Bu kriterlerle eşleşen aktif kargo yöntemi bulunamadı. Ülke kapsamı, hariç ülkeler, şehir, posta kodu ve min/max limit kurallarını kontrol edin.
                            </div>
                        @endif
                    </div>
                @endif
            @endif
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
