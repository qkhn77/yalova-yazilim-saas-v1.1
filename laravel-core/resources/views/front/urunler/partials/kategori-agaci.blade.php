@php
    $nodes = $nodes ?? [];
    $selectedSlug = $selectedSlug ?? null;
@endphp

@if(!empty($nodes))
    <ul class="product-category-tree level-{{ $level ?? 1 }}">
        @foreach($nodes as $node)
            @php
                $isActive = $selectedSlug !== null && $selectedSlug === $node['slug'];
                $hasChildren = !empty($node['children']);
            @endphp
            <li class="{{ $isActive ? 'is-active' : '' }}">
                <a href="{{ route('products.category', $node['slug']) }}" class="{{ $isActive ? 'active' : '' }}">
                    {{ $node['ad'] }}
                </a>

                @if($hasChildren)
                    @include('front.urunler.partials.kategori-agaci', [
                        'nodes' => $node['children'],
                        'selectedSlug' => $selectedSlug,
                        'level' => ($level ?? 1) + 1,
                    ])
                @endif
            </li>
        @endforeach
    </ul>
@endif
