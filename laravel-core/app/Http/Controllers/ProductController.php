<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductCategory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;

class ProductController extends Controller
{
    /**
     * @deprecated Legacy controller. Ürün yayın akışı artık UrunController + stok_kartlari üstünden çalışır.
     */
    public function index(Request $request): View
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_categories')) {
            return view('front.products.index', [
                'products' => new LengthAwarePaginator([], 0, 12),
                'featuredProducts' => collect(),
                'categories' => collect(),
                'brands' => collect(),
            ]);
        }

        $query = Product::query()
            ->with('category')
            ->active()
            ->whereHas('category', fn ($q) => $q->active());

        if ($search = trim((string) $request->string('q'))) {
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%")
                    ->orWhere('brand', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($categorySlug = trim((string) $request->string('category'))) {
            $query->whereHas('category', fn ($q) => $q->where('slug', $categorySlug));
        }

        if ($brand = trim((string) $request->string('brand'))) {
            $query->where('brand', $brand);
        }

        if ($request->boolean('featured')) {
            $query->featured();
        }

        $products = $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(12)
            ->withQueryString();

        $featuredProducts = Product::query()
            ->with('category')
            ->active()
            ->featured()
            ->whereHas('category', fn ($q) => $q->active())
            ->orderBy('sort_order')
            ->take(4)
            ->get();

        $categories = ProductCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $brands = Product::query()
            ->active()
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return view('front.products.index', compact('products', 'featuredProducts', 'categories', 'brands'));
    }

    public function category(string $slug, Request $request): View
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_categories')) {
            abort(404);
        }

        $category = ProductCategory::query()
            ->active()
            ->where('slug', $slug)
            ->firstOrFail();

        $query = Product::query()
            ->with('category')
            ->active()
            ->where('category_id', $category->id);

        if ($search = trim((string) $request->string('q'))) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('short_description', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%"));
        }

        if ($brand = trim((string) $request->string('brand'))) {
            $query->where('brand', $brand);
        }

        $products = $query
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->paginate(12)
            ->withQueryString();

        $categories = ProductCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $brands = Product::query()
            ->active()
            ->where('category_id', $category->id)
            ->whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');

        return view('front.products.category', compact('products', 'category', 'categories', 'brands'));
    }

    public function show(string $slug): View
    {
        if (! Schema::hasTable('products') || ! Schema::hasTable('product_categories')) {
            abort(404);
        }

        $product = Product::query()
            ->with('category')
            ->active()
            ->where('slug', $slug)
            ->whereHas('category', fn ($q) => $q->active())
            ->firstOrFail();

        $similarProducts = Product::query()
            ->with('category')
            ->active()
            ->where('id', '!=', $product->id)
            ->where('category_id', $product->category_id)
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->take(4)
            ->get();

        return view('front.products.show', compact('product', 'similarProducts'));
    }
}
