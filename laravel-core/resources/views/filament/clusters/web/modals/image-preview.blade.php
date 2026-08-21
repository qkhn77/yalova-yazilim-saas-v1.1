<div class="space-y-3">
    @if($imageUrl)
        <div class="flex justify-center">
            <img
                src="{{ $imageUrl }}"
                alt="{{ $title ?? 'Görsel' }}"
                class="max-w-full max-h-[70vh] object-contain rounded-lg shadow"
            />
        </div>
        @if($title ?? null)
            <p class="text-sm text-gray-500 dark:text-gray-400 text-center">{{ $title }}</p>
        @endif
    @else
        <p class="text-center text-gray-500 dark:text-gray-400">Bu yazı için görsel yok.</p>
    @endif
</div>
