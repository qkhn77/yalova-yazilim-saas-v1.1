@php
    $action = $action ?? route('products.index');
    $filtreler = $filtreler ?? [];
    $offcanvasId = $offcanvasId ?? 'urunFiltreOffcanvas';
@endphp

<form method="GET" action="{{ $action }}" class="urun-filters-form js-urun-filter-form">
    <div class="urun-filters-grid">
        <div class="urun-filter-item">
            <label for="siralama" class="form-label">{{ __('front.product.sort_label') }}</label>
            <select id="siralama" name="siralama" class="form-select js-urun-sort">
                <option value="yeni" @selected(($filtreler['siralama'] ?? 'yeni') === 'yeni')>{{ __('front.product.sort_newest') }}</option>
                <option value="fiyat" @selected(($filtreler['siralama'] ?? '') === 'fiyat')>{{ __('front.product.sort_price_asc') }}</option>
                <option value="cok_satan" @selected(($filtreler['siralama'] ?? '') === 'cok_satan')>{{ __('front.product.sort_best_seller') }}</option>
            </select>
        </div>

        <div class="urun-filter-item urun-filter-toggle">
            <label class="form-check mb-0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    name="stokta_var"
                    value="1"
                    @checked(($filtreler['stokta_var'] ?? null) == '1' || ($filtreler['stokta_var'] ?? null) === 1 || ($filtreler['stokta_var'] ?? null) === true)
                >
                <span class="form-check-label">{{ __('front.product.in_stock_only') }}</span>
            </label>
        </div>

        <div class="urun-filter-item urun-view-switch-wrap">
            <label class="form-label">{{ __('front.product.view_label') }}</label>
            <div class="urun-view-switch js-urun-view-switch">
                <button type="button" class="btn btn-sm btn-outline-secondary" data-cols="2" aria-label="2" title="2">
                    <span class="view-btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><rect x="3" y="4" width="8" height="16" rx="1.5"/><rect x="13" y="4" width="8" height="16" rx="1.5"/></svg>
                    </span>
                    <span class="view-btn-label">2</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary is-active" data-cols="3" aria-label="3" title="3">
                    <span class="view-btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><rect x="2" y="4" width="6" height="16" rx="1.3"/><rect x="9" y="4" width="6" height="16" rx="1.3"/><rect x="16" y="4" width="6" height="16" rx="1.3"/></svg>
                    </span>
                    <span class="view-btn-label">3</span>
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-cols="4" aria-label="4" title="4">
                    <span class="view-btn-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" focusable="false"><rect x="1.5" y="4" width="4.5" height="16" rx="1"/><rect x="6.8" y="4" width="4.5" height="16" rx="1"/><rect x="12.2" y="4" width="4.5" height="16" rx="1"/><rect x="17.5" y="4" width="4.5" height="16" rx="1"/></svg>
                    </span>
                    <span class="view-btn-label">4</span>
                </button>
            </div>
        </div>
    </div>

    <div class="urun-filters-actions">
        <button
            type="button"
            class="btn btn-sm btn-outline-dark d-lg-none"
            data-bs-toggle="offcanvas"
            data-bs-target="#{{ $offcanvasId }}"
            aria-controls="{{ $offcanvasId }}"
        >
            {{ __('front.product.filters_button') }}
        </button>
    </div>
</form>
