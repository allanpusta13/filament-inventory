<?php

declare(strict_types=1);

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ProductVariantPrice;
use App\Models\ProductVariantUnitConversion;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;
use function Pest\Livewire\livewire;

beforeEach(function () {
    ProductVariant::truncate();
    Product::truncate();
    ProductVariantPrice::truncate();
    ProductVariantUnitConversion::truncate();

    $this->admin = App\Models\User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

describe('ProductResource edge cases', function () {
    it('validates unique sku on create', function () {
        $product = Product::factory()->create();
        $existingVariant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-EXISTING']);

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-EXISTING',
                'name' => 'New Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['sku' => 'unique'])
            ->assertNotNotified();
    });

    it('validates unique sku on edit', function () {
        $product = Product::factory()->create();
        $existingVariant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-EXISTING']);
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-OTHER']);

        livewire(EditProduct::class, ['record' => $variant->id])
            ->fillForm([
                'sku' => 'SKU-EXISTING',
            ])
            ->call('save')
            ->assertHasFormErrors(['sku' => 'unique'])
            ->assertNotNotified();
    });

    it('allows same sku when editing same record', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-SAME', 'name' => 'Original Name']);

        livewire(EditProduct::class, ['record' => $variant->id])
            ->fillForm([
                'sku' => 'SKU-SAME',
                'name' => 'Updated Name',
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(ProductVariant::class, [
            'id' => $variant->id,
            'sku' => 'SKU-SAME',
            'name' => 'Updated Name',
        ]);
    });

    it('validates unique barcode on create', function () {
        $product = Product::factory()->create();
        $existingVariant = ProductVariant::factory()->for($product)->create(['barcode' => '1234567890123']);

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'barcode' => '1234567890123',
                'name' => 'New Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['barcode' => 'unique'])
            ->assertNotNotified();
    });

    it('allows nullable barcode', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'barcode' => null,
                'name' => 'New Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(ProductVariant::class, [
            'sku' => 'SKU-NEW',
            'barcode' => null,
        ]);
    });

    it('requires sku on create', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['sku' => 'required'])
            ->assertNotNotified();
    });

    it('requires name on create', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required'])
            ->assertNotNotified();
    });

    it('requires base_unit_name on create', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'name' => 'Test Variant',
                'reorder_point' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['base_unit_name' => 'required'])
            ->assertNotNotified();
    });

    it('defaults reorder_point to 0 on create', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $variant = ProductVariant::where('sku', 'SKU-NEW')->first();
        expect($variant->reorder_point)->toBe(0);
    });

    it('requires reorder_point to be numeric', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 'abc',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['reorder_point' => 'numeric'])
            ->assertNotNotified();
    });

    it('defaults reorder_point to 0', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $variant = ProductVariant::where('sku', 'SKU-NEW')->first();
        expect($variant->reorder_point)->toBe(0);
    });

    it('defaults is_active to true', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $variant = ProductVariant::where('sku', 'SKU-NEW')->first();
        expect($variant->is_active)->toBeTrue();
    });

    it('allows deactivating variant', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['is_active' => true]);

        livewire(EditProduct::class, ['record' => $variant->id])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $variant->refresh();
        expect($variant->is_active)->toBeFalse();
    });

    it('allows attributes as json', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-ATTR',
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'attributes' => ['color' => 'red', 'size' => 'large'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $variant = ProductVariant::where('sku', 'SKU-ATTR')->first();
        expect($variant->attributes)->toBe(['color' => 'red', 'size' => 'large']);
    });

    it('allows images as json', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-IMG',
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'images' => ['img1.jpg', 'img2.jpg'],
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        $variant = ProductVariant::where('sku', 'SKU-IMG')->first();
        expect($variant->images)->toEqual(['img1.jpg', 'img2.jpg']);
    });

    it('validates sku max length', function () {
        $product = Product::factory()->create();
        $longSku = Str::random(256);

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => $longSku,
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['sku' => 'max'])
            ->assertNotNotified();
    });

    it('validates barcode max length', function () {
        $product = Product::factory()->create();
        $longBarcode = Str::random(256);

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-NEW',
                'barcode' => $longBarcode,
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 0,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['barcode' => 'max'])
            ->assertNotNotified();
    });

    it('can search by sku', function () {
        ProductVariant::truncate();
        $product = Product::factory()->create();
        $v1 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-001', 'name' => 'Variant 1']);
        $v2 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-002', 'name' => 'Variant 2']);

        $results = ProductVariant::where('sku', 'like', '%SKU-001%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($v1->id);
    });

    it('can search by name', function () {
        ProductVariant::truncate();
        $product = Product::factory()->create();
        $v1 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-001', 'name' => 'Coffee Beans']);
        $v2 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-002', 'name' => 'Tea Leaves']);

        $results = ProductVariant::where('name', 'like', '%Coffee%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($v1->id);
    });

    it('can search by barcode', function () {
        ProductVariant::truncate();
        $product = Product::factory()->create();
        $v1 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-001', 'barcode' => '1234567890123']);
        $v2 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-002', 'barcode' => '9876543210987']);

        $results = ProductVariant::where('barcode', 'like', '%123456%')->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($v1->id);
    });

    it('can filter by product', function () {
        ProductVariant::truncate();
        $product1 = Product::factory()->create(['name' => 'Coffee']);
        $product2 = Product::factory()->create(['name' => 'Tea']);
        $v1 = ProductVariant::factory()->for($product1)->create();
        $v2 = ProductVariant::factory()->for($product2)->create();

        $results = ProductVariant::where('product_id', $product1->id)->get();
        expect($results)->toHaveCount(1);
        expect($results->first()->id)->toBe($v1->id);
    });

    it('can filter by is_active', function () {
        ProductVariant::truncate();
        $product = Product::factory()->create();
        $active = ProductVariant::factory()->for($product)->create(['is_active' => true]);
        $inactive = ProductVariant::factory()->for($product)->create(['is_active' => false]);

        $activeResults = ProductVariant::where('is_active', true)->get();
        $inactiveResults = ProductVariant::where('is_active', false)->get();

        expect($activeResults)->toHaveCount(1);
        expect($activeResults->first()->id)->toBe($active->id);

        expect($inactiveResults)->toHaveCount(1);
        expect($inactiveResults->first()->id)->toBe($inactive->id);
    });

    it('can sort by sku', function () {
        ProductVariant::truncate();
        $product = Product::factory()->create();
        $v1 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-C', 'created_at' => now()->subDays(2)]);
        $v2 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-A', 'created_at' => now()->subDay()]);
        $v3 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-B', 'created_at' => now()]);

        $results = ProductVariant::orderBy('sku', 'asc')->get();
        expect($results->pluck('id')->toArray())
            ->toBe([$v2->id, $v3->id, $v1->id]);
    });

    it('can sort by name', function () {
        ProductVariant::truncate();
        $product = Product::factory()->create();
        $v1 = ProductVariant::factory()->for($product)->create(['name' => 'Coffee C', 'created_at' => now()->subDays(2)]);
        $v2 = ProductVariant::factory()->for($product)->create(['name' => 'Coffee A', 'created_at' => now()->subDay()]);
        $v3 = ProductVariant::factory()->for($product)->create(['name' => 'Coffee B', 'created_at' => now()]);

        $results = ProductVariant::orderBy('name', 'asc')->get();
        expect($results->pluck('id')->toArray())
            ->toBe([$v2->id, $v3->id, $v1->id]);
    });

    it('can sort by created_at', function () {
        ProductVariant::truncate();
        $product = Product::factory()->create();
        $v1 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-C', 'created_at' => now()->subDays(2)]);
        $v2 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-A', 'created_at' => now()->subDay()]);
        $v3 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-B', 'created_at' => now()]);

        $results = ProductVariant::orderBy('created_at', 'asc')->get();
        expect($results->pluck('id')->toArray())
            ->toBe([$v1->id, $v2->id, $v3->id]);
    });

    it('renders create page with defaults', function () {
        livewire(CreateProduct::class)
            ->assertOk();
    });

    it('renders edit page with correct data', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-TEST', 'name' => 'Test Variant', 'base_unit_name' => 'piece']);

        livewire(EditProduct::class, ['record' => $variant->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'sku' => 'SKU-TEST',
                'name' => 'Test Variant',
                'base_unit_name' => 'piece',
            ]);
    });

    it('renders view page with correct data', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-VIEW', 'name' => 'View Variant']);

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->assertOk()
            ->assertSchemaStateSet([
                'sku' => 'SKU-VIEW',
                'name' => 'View Variant',
            ]);
    });

    it('creates variant via form with product relationship', function () {
        $product = Product::factory()->create();

        livewire(CreateProduct::class)
            ->fillForm([
                'product_id' => $product->id,
                'sku' => 'SKU-CREATE',
                'name' => 'Created Variant',
                'base_unit_name' => 'piece',
                'reorder_point' => 5,
                'is_active' => true,
            ])
            ->call('create')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(ProductVariant::class, [
            'sku' => 'SKU-CREATE',
            'name' => 'Created Variant',
            'product_id' => $product->id,
            'base_unit_name' => 'piece',
            'reorder_point' => 5,
            'is_active' => true,
        ]);
    });

    it('updates variant via form', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-ORIG', 'name' => 'Original Name']);

        livewire(EditProduct::class, ['record' => $variant->id])
            ->fillForm([
                'sku' => 'SKU-UPDATED',
                'name' => 'Updated Name',
                'reorder_point' => 15,
            ])
            ->call('save')
            ->assertNotified()
            ->assertHasNoFormErrors();

        assertDatabaseHas(ProductVariant::class, [
            'id' => $variant->id,
            'sku' => 'SKU-UPDATED',
            'name' => 'Updated Name',
            'reorder_point' => 15,
        ]);
    });

    it('deletes variant via view page action', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        $variant->refresh();
        expect($variant->deleted_at)->not->toBeNull();
    });

    it('restores variant via view page action', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->callAction(RestoreAction::class)
            ->assertNotified();

        $variant->refresh();
        expect($variant->deleted_at)->toBeNull();
        assertDatabaseHas(ProductVariant::class, ['id' => $variant->id]);
    });

    it('handles soft delete gracefully', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->callAction(DeleteAction::class)
            ->assertNotified()
            ->assertRedirect();

        $variant->refresh();
        expect($variant->deleted_at)->not->toBeNull();
    });

    it('handles variant with no price', function () {
        ProductVariantPrice::truncate();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->assertOk();
    });

    it('handles variant with no unit conversions', function () {
        ProductVariantUnitConversion::truncate();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create();

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->assertOk();
    });

    it('handles variant with null barcode', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['barcode' => null]);

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->assertOk();
    });

    it('handles variant with null attributes', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['attributes' => null]);

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->assertOk();
    });

    it('handles variant with null images', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['images' => null]);

        livewire(ViewProduct::class, ['record' => $variant->id])
            ->assertOk();
    });

    it('handles variant with reorder_point integration', function () {
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->for($product)->create(['reorder_point' => 30]);

        expect($variant->isBelowReorderPoint(30))->toBeTrue();
        expect($variant->isBelowReorderPoint(35))->toBeFalse();
        expect($variant->isBelowReorderPoint(20))->toBeTrue();
    });
});