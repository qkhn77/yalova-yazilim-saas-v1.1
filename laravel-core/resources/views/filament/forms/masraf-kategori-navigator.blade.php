@php
    $statePath = $getStatePath();
@endphp

<div
    x-data="{
        categories: @js(array_values($kategoriler)),
        levels: {{ (int) $seviyeSayisi }},
        selected: [],
        init() {
            const leaf = Number($refs.hidden.value || 0);
            if (! leaf) return;

            let current = this.categories.find((category) => category.id === leaf);
            const path = [];
            while (current) {
                path.unshift(current.id);
                current = this.categories.find((category) => category.id === current.ust_kategori_id);
            }
            this.selected = path;
        },
        optionsFor(level) {
            const parentId = level === 0 ? null : Number(this.selected[level - 1] || 0);
            return this.categories.filter((category) => (category.ust_kategori_id || null) === (parentId || null));
        },
        categoryFor(id) {
            return this.categories.find((category) => Number(category.id) === Number(id)) || null;
        },
        hasChildren(id) {
            return this.categories.some((category) => Number(category.ust_kategori_id || 0) === Number(id));
        },
        isSelectable(id) {
            const category = this.categoryFor(id);

            return Boolean(category?.secilir_mi) && ! this.hasChildren(id);
        },
        isVisible(level) {
            return level === 0 || this.optionsFor(level).length > 0;
        },
        selectLevel(level, value) {
            const id = Number(value || 0);
            this.selected = this.selected.slice(0, level);
            if (id) this.selected[level] = id;

            const leaf = id && this.isSelectable(id) ? id : '';
            $refs.hidden.value = leaf;
            $refs.hidden.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }"
    class="space-y-2"
>
    <input type="hidden" x-ref="hidden" wire:model="{{ $statePath }}" value="{{ $getState() }}">

    <template x-for="level in levels" :key="level">
        <div x-show="isVisible(level - 1)" x-cloak>
            <select
                class="fi-select-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500"
                :value="selected[level - 1] || ''"
                @change="selectLevel(level - 1, $event.target.value)"
            >
                <option value="" x-text="level === 1 ? 'Bir seçenek seçin' : 'Alt kategori seçin'"></option>
                <template x-for="option in optionsFor(level - 1)" :key="option.id">
                    <option
                        :value="option.id"
                        x-text="hasChildren(option.id) ? `${option.ad} (alt kategori seçin)` : option.ad"
                    ></option>
                </template>
            </select>
        </div>
    </template>
</div>
