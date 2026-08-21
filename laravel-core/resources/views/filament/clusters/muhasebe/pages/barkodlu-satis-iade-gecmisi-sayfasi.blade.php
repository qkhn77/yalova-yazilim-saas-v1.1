<x-filament-panels::page>
    <div class="muhasebe-cork-screen cork-sales-operations space-y-6">
        <x-filament::section heading="Hizli Iade (F10 Modu)">
            <div class="mb-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200">
                Akis: Satis no yaz -> Enter -> Kalem sec -> Miktar/Neden -> Hizli Iade Kaydet.
                Kisayol: <strong>F2</strong> satis no odak, <strong>F9</strong> hizli iade kaydet.
            </div>
            @if($sonOtomatikIadeId)
                <div class="mb-3 rounded-lg border border-warning-300 bg-warning-50 px-3 py-2 text-xs text-warning-900 dark:border-warning-700 dark:bg-warning-900/20 dark:text-warning-100">
                    Son otomatik iade: <strong>{{ $sonOtomatikIadeNo ?: ('#'.$sonOtomatikIadeId) }}</strong>.
                    {{ (int) ($otomatikIadeGeriAlmaSuresiSaniye ?? 5) }} saniye icinde geri alabilirsiniz.
                    <x-filament::button size="xs" color="warning" class="ml-2" wire:click="sonOtomatikIadeyiGeriAl">Geri Al</x-filament::button>
                </div>
            @endif
            {{ $this->form }}
            <div class="mt-3 flex flex-wrap gap-2">
                <x-filament::button color="info" icon="heroicon-o-magnifying-glass" wire:click="hizliIadeSatisiniYukle">Satisi Yukle</x-filament::button>
                <x-filament::button color="success" icon="heroicon-o-arrow-uturn-left" wire:click="hizliIadeKaydet">Hizli Iade Kaydet (F9)</x-filament::button>
            </div>
        </x-filament::section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>

<script>
    (() => {
        const focusSatisNo = () => {
            const input = document.getElementById('hizli-iade-satis-no');
            if (!input) return;
            setTimeout(() => input.focus(), 50);
        };
        const focusMiktar = () => {
            const input = document.getElementById('hizli-iade-miktar');
            if (!input) return;
            setTimeout(() => {
                input.focus();
                if (typeof input.select === 'function') {
                    input.select();
                }
            }, 50);
        };
        const livewireBileseni = () => {
            const satisNo = document.getElementById('hizli-iade-satis-no');
            const root = satisNo ? satisNo.closest('[wire\\:id]') : document.querySelector('[wire\\:id]');
            if (!root || !window.Livewire || typeof window.Livewire.find !== 'function') return null;
            const id = root.getAttribute('wire:id');
            return id ? window.Livewire.find(id) : null;
        };
        const call = (method) => {
            const cmp = livewireBileseni();
            if (!cmp || typeof cmp.call !== 'function') return;
            cmp.call(method);
        };

        document.addEventListener('livewire:initialized', () => {
            focusSatisNo();
            if (window.Livewire && typeof window.Livewire.on === 'function') {
                window.Livewire.on('hizli-iade-miktar-odakla', () => focusMiktar());
                window.Livewire.on('hizli-iade-undo-penceresi-ac', (payload = {}) => {
                    const saniye = Number(payload?.saniye ?? {{ (int) ($otomatikIadeGeriAlmaSuresiSaniye ?? 5) }});
                    const ms = Number.isFinite(saniye) && saniye > 0 ? saniye * 1000 : 5000;
                    setTimeout(() => call('otomatikIadeGeriAlFirsatiniKapat'), ms);
                });
                window.Livewire.on('hizli-iade-geri-al-tiklandi', () => {
                    call('sonOtomatikIadeyiGeriAl');
                });
            }
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'F2') {
                event.preventDefault();
                focusSatisNo();
                return;
            }
            if (event.key === 'F9') {
                event.preventDefault();
                call('hizliIadeKaydet');
            }
        });
    })();
</script>
