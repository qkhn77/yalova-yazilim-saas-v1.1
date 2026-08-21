<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->constrained('product_categories')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('short_description')->nullable();
            $table->longText('description')->nullable();
            $table->string('sku')->nullable()->index();
            $table->string('brand')->nullable()->index();
            $table->decimal('price', 12, 2)->nullable();
            $table->decimal('discounted_price', 12, 2)->nullable();
            $table->string('stock_status', 20)->nullable()->index();
            $table->string('image')->nullable();
            $table->json('gallery')->nullable();
            $table->json('technical_specs')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();

            $table->index(['category_id', 'is_active']);
            $table->index(['category_id', 'is_featured']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};

