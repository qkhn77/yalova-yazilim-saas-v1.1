<form action="{{ $action }}" method="GET" class="row g-2 mb-4">
    <div class="col-lg-4 col-md-6">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('front.product.search_placeholder_legacy') }}">
    </div>
    <div class="col-lg-3 col-md-6">
        <select name="category" class="form-control">
            <option value="">{{ __('front.product.all_categories_legacy') }}</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug)>{{ $cat->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-3 col-md-6">
        <select name="brand" class="form-control">
            <option value="">{{ __('front.product.all_brands_legacy') }}</option>
            @foreach($brands as $brand)
                <option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-lg-2 col-md-6 d-grid">
        <button class="btn-default">{{ __('front.product.filter_button_legacy') }}</button>
    </div>
</form>

