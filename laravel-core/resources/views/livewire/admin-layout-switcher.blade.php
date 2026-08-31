<div
    class="saas-layout-switcher"
    x-on:saas-admin-layout-saved.window="window.location.reload()"
>
<style>
    .fi-dropdown-panel:has(.saas-layout-switcher__heading) {
        width: 15rem !important;
        max-width: min(15rem, calc(100vw - 1rem)) !important;
    }

    .fi-dropdown-panel:has(.saas-layout-switcher__heading) > .fi-dropdown-list {
        width: 100% !important;
        min-width: 0 !important;
    }
</style>

    <x-filament::dropdown placement="bottom-end" teleport width="xs" shift>
        <x-slot name="trigger">
            <x-filament::icon-button
                color="gray"
                icon="heroicon-o-swatch"
                icon-size="lg"
                label="Tema ve görünüm ayarları"
                class="saas-appearance-trigger"
            />
        </x-slot>

        <x-filament::dropdown.list>
            <div class="saas-layout-switcher__heading" role="presentation">
                <span>Menü düzeni</span>
            </div>

            @foreach($options as $value => $label)
                <x-filament::dropdown.list.item
                    :color="$layout === $value ? 'primary' : 'gray'"
                    :icon="$icons[$value]"
                    tag="button"
                    type="button"
                    wire:click="setLayout('{{ $value }}')"
                    wire:loading.attr="disabled"
                    wire:target="setLayout"
                    :aria-current="$layout === $value ? 'true' : null"
                >
                    {{ $label }}
                    @if($layout === $value)
                        <span class="sr-only">(seçili)</span>
                    @endif
                </x-filament::dropdown.list.item>
            @endforeach
        </x-filament::dropdown.list>

        @if (filament()->hasDarkMode() && (! filament()->hasDarkModeForced()))
            <x-filament::dropdown.list>
                <div class="saas-layout-switcher__section-label">Tema</div>
                <x-filament-panels::theme-switcher />
            </x-filament::dropdown.list>
        @endif
    </x-filament::dropdown>
</div>
