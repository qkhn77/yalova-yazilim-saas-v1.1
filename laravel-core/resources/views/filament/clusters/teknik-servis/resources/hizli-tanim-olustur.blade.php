<x-filament-panels::page>
    <form wire:submit="create" class="space-y-5">
        <div class="grid gap-4 md:grid-cols-2">
            @if ($this->cihazSecenekleri() !== [])
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                    Cihaz
                    <select wire:model="data.cihaz_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                        <option value="">Kategorisiz</option>
                        @foreach ($this->cihazSecenekleri() as $id => $ad)
                            <option value="{{ $id }}">{{ $ad }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Ad
                <input wire:model="data.ad" required maxlength="191" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                @error('data.ad') <span class="mt-1 block text-xs text-danger-600">{{ $message }}</span> @enderror
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Kod
                <input wire:model="data.kod" maxlength="64" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Sıralama
                <input type="number" wire:model="data.siralama" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
            </label>

            <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                <input type="checkbox" wire:model="data.aktif" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900">
                Aktif
            </label>

            <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                <input type="checkbox" wire:model="data.varsayilan_mi" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900">
                Varsayılan
            </label>
        </div>

        @if ($this->bayrakAlanlari() !== [])
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($this->bayrakAlanlari() as $alan => $etiket)
                    <label class="flex items-center gap-3 text-sm font-medium text-gray-700 dark:text-gray-200">
                        <input type="checkbox" wire:model="data.{{ $alan }}" class="rounded border-gray-300 text-primary-600 shadow-sm focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900">
                        {{ $etiket }}
                    </label>
                @endforeach
            </div>
        @endif

        <button type="submit" class="block w-fit rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
            Kaydet
        </button>
    </form>
</x-filament-panels::page>
