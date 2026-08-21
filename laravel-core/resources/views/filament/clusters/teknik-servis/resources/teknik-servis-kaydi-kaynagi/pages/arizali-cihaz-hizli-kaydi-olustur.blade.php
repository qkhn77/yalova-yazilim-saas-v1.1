<x-filament-panels::page>
    <form wire:submit="create" class="space-y-5">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Öncelik
                <select wire:model="data.oncelik" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                    <option value="dusuk">Düşük</option>
                    <option value="normal">Normal</option>
                    <option value="acil">Acil</option>
                </select>
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Servis kanalı
                <select wire:model="data.servis_kanali" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                    <option value="magaza">Mağaza</option>
                    <option value="telefon">Telefon</option>
                    <option value="whatsapp">WhatsApp</option>
                    <option value="web">Web</option>
                    <option value="saha">Saha</option>
                </select>
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Fiş no
                <input wire:model="data.fis_no" maxlength="64" placeholder="Otomatik" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Kabul tarihi
                <input type="datetime-local" wire:model="data.kabul_tarihi" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Servis durumu
                <select wire:model="data.servis_durumu_id" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                    @foreach ($servisDurumuSecenekleri as $id => $ad)
                        <option value="{{ $id }}">{{ $ad }}</option>
                    @endforeach
                </select>
            </label>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Cari
                <select wire:model="data.cari_id" required class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                    @foreach ($cariSecenekleri as $id => $ad)
                        <option value="{{ $id }}">{{ $ad }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Telefon
                <input wire:model="data.musteri_tel" maxlength="32" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Cihaz
                <select wire:model="data.cihaz_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">-</option>
                    @foreach ($cihazSecenekleri as $id => $ad)
                        <option value="{{ $id }}">{{ $ad }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Marka
                <select wire:model="data.marka_id" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                    <option value="">-</option>
                    @foreach ($markaSecenekleri as $id => $ad)
                        <option value="{{ $id }}">{{ $ad }}</option>
                    @endforeach
                </select>
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Model no
                <input wire:model="data.model_no" maxlength="128" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
            </label>

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Seri no
                <input wire:model="data.seri_no" required maxlength="128" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
            </label>
        </div>

        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
            Arıza tanımları
            <select multiple wire:model="data.arizalar" class="mt-1 block min-h-28 w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100">
                @foreach ($arizaSecenekleri as $id => $ad)
                    <option value="{{ $id }}">{{ $ad }}</option>
                @endforeach
            </select>
        </label>

        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
            Müşteri şikayeti
            <textarea wire:model="data.musteri_sikayeti" required rows="3" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"></textarea>
        </label>

        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
            Servis Notu
            <textarea wire:model="data.musteriye_gorunen_not" rows="2" class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-100"></textarea>
        </label>

        <button type="submit" class="block w-fit rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900">
            Kaydet
        </button>
    </form>
</x-filament-panels::page>
