<x-filament::dropdown.list>
    <x-filament::dropdown.list.item
        :href="\App\Filament\Clusters\Ayarlar\Pages\MesajMerkeziSayfasi::getUrl()"
        icon="heroicon-o-chat-bubble-left-right"
        tag="a"
        class="saas-message-center-link"
    >
        Mesaj Merkezi
    </x-filament::dropdown.list.item>
</x-filament::dropdown.list>

<div
    class="saas-layout-switcher"
    x-on:saas-admin-layout-saved.window="window.location.reload()"
>
    <x-filament::dropdown.list>
        <div class="saas-layout-switcher__heading" role="presentation">
            <span>Menü düzeni</span>
            <span class="saas-layout-switcher__current">{{ $options[$layout] }}</span>
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
</div>
