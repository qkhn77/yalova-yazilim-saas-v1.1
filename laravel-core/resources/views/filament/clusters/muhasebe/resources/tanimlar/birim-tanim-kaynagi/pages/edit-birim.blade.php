<x-filament-panels::page>
    @if (request()->boolean('detay'))
        <x-filament-panels::form
            id="form"
            :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
            wire:submit="save"
        >
            {{ $this->form }}

            <x-filament-panels::form.actions
                :actions="$this->getCachedFormActions()"
                :full-width="$this->hasFullWidthFormActions()"
            />
        </x-filament-panels::form>
    @else
        <form wire:submit="save" class="space-y-4">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Ad
                <input
                    type="text"
                    maxlength="191"
                    required
                    wire:model="data.ad"
                    class="mt-1 block w-full max-w-xl rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"
                >
            </label>

            <button
                type="submit"
                class="block w-fit rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900"
            >
                Kaydet
            </button>
        </form>
    @endif
</x-filament-panels::page>
