@php
    $record = $column->getRecord();
    $thumbUrl = $record?->image_thumb_url;
    $fullUrl = $record?->image_url;
@endphp
<div class="blog-listesi-cell-image flex justify-center items-center shrink-0 w-12 h-12 min-w-0 overflow-hidden rounded">
    @if($thumbUrl ?? $fullUrl)
        <button
            type="button"
            wire:click="$dispatch('open-image-preview', { url: @js($fullUrl), title: @js($record?->title) })"
            class="focus:outline-none rounded overflow-hidden hover:opacity-90 transition p-0 border-0 bg-transparent size-full flex items-center justify-center min-w-0"
        >
            <img
                src="{{ $thumbUrl ?? $fullUrl }}"
                alt=""
                class="max-w-full max-h-full w-auto h-auto object-contain rounded cursor-pointer min-w-0"
                loading="lazy"
                decoding="async"
                style="max-width: 48px; max-height: 48px;"
                onerror="this.style.display='none'; this.nextElementSibling?.classList.remove('hidden');"
            />
            <span class="hidden w-10 h-10 flex items-center justify-center rounded bg-gray-100 dark:bg-gray-700 text-gray-400 text-xs shrink-0">—</span>
        </button>
    @else
        <span class="text-gray-400 text-xs">—</span>
    @endif
</div>
