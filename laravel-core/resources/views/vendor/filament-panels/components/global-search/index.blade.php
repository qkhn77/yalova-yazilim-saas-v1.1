<div
    x-data="{ ykGlobalSearchOpen: false }"
    x-bind:class="{ 'yk-global-search-open': ykGlobalSearchOpen }"
    x-on:click="ykGlobalSearchOpen = true; $nextTick(() => $el.querySelector('input[type=search]')?.focus())"
    x-on:click.outside="if (! $el.querySelector('input[type=search]')?.value) ykGlobalSearchOpen = false"
    x-on:keydown.escape.window="if ($el.contains(document.activeElement) && ! $el.querySelector('input[type=search]')?.value) { ykGlobalSearchOpen = false; document.activeElement?.blur() }"
    x-on:focus-first-global-search-result.stop="$el.querySelector('.fi-global-search-result-link')?.focus()"
    class="fi-global-search flex items-center"
>
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_START) }}

    <div class="sm:relative">
        <x-filament-panels::global-search.field />

        @if ($results !== null)
            <x-filament-panels::global-search.results-container
                :results="$results"
            />
        @endif
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::GLOBAL_SEARCH_END) }}
</div>
