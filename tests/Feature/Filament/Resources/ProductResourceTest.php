<?php

declare(strict_types=1);

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Product;
use App\Models\ProductVariant;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Livewire\livewire;

beforeEach(function () {
    ProductVariant::truncate();
    Product::truncate();

    $this->admin = App\Models\User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('can render index page', function () {
    livewire(ListProducts::class)
        ->assertOk();
});

it('can render create page', function () {
    livewire(CreateProduct::class)
        ->assertOk();
});

it('can render edit page', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    livewire(EditProduct::class, [
        'record' => $variant->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'sku' => $variant->sku,
            'name' => $variant->name,
        ]);
});

it('can render view page', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    livewire(ViewProduct::class, [
        'record' => $variant->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'sku' => $variant->sku,
            'name' => $variant->name,
        ]);
});

it('has column', function (string $column) {
    livewire(ListProducts::class)
        ->assertTableColumnExists($column);
})->with(['product.name', 'sku', 'barcode', 'name', 'base_unit_name', 'currentPrice.sale_price', 'reorder_point', 'is_active', 'created_at', 'updated_at']);

it('can sort column', function (string $column) {
    $product = Product::factory()->create();
    $records = ProductVariant::factory()->count(5)->for($product)->create();

    livewire(ListProducts::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($records->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($records->sortByDesc($column), inOrder: true);
})->with(['sku', 'name', 'reorder_point']);

it('can search table', function () {
    $product = Product::factory()->create();
    $sku1 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-001', 'name' => 'Variant One']);
    $sku2 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-002', 'name' => 'Variant Two']);
    ProductVariant::factory()->for($product)->create(['sku' => 'SKU-003', 'name' => 'Variant Three']);

    livewire(ListProducts::class)
        ->loadTable()
        ->searchTable('SKU-001')
        ->assertCanSeeTableRecords([$sku1])
        ->assertCanNotSeeTableRecords([$sku2]);
});

it('can search table by column', function () {
    $product = Product::factory()->create();
    $variant1 = ProductVariant::factory()->for($product)->create(['sku' => 'ABC-001', 'name' => 'First Variant']);
    $variant2 = ProductVariant::factory()->for($product)->create(['sku' => 'ABC-002', 'name' => 'Second Variant']);

    livewire(ListProducts::class)
        ->loadTable()
        ->searchTable('First')
        ->assertCanSeeTableRecords([$variant1])
        ->assertCanNotSeeTableRecords([$variant2]);
});

it('can filter table by is_active', function () {
    $product = Product::factory()->create();
    $active = ProductVariant::factory()->for($product)->create(['is_active' => true]);
    $inactive = ProductVariant::factory()->for($product)->create(['is_active' => false]);

    livewire(ListProducts::class)
        ->loadTable()
        ->filterTable('is_active', '1')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});

it('can filter table by product', function () {
    $product1 = Product::factory()->create(['name' => 'Product A']);
    $product2 = Product::factory()->create(['name' => 'Product B']);
    $variant1 = ProductVariant::factory()->for($product1)->create();
    $variant2 = ProductVariant::factory()->for($product2)->create();

    livewire(ListProducts::class)
        ->loadTable()
        ->filterTable('product_id', $product1->id)
        ->assertCanSeeTableRecords([$variant1])
        ->assertCanNotSeeTableRecords([$variant2]);
});

it('can render table column state', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'TEST-SKU', 'name' => 'Test Variant']);

    livewire(ListProducts::class)
        ->loadTable()
        ->assertTableColumnStateSet('sku', 'TEST-SKU', record: $variant)
        ->assertTableColumnStateSet('name', 'Test Variant', record: $variant);
});

it('can render table column formatted state', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create(['created_at' => now()->subDays(5)]);

    livewire(ListProducts::class)
        ->loadTable()
        ->assertTableColumnFormattedStateSet('created_at', $variant->created_at->format('M d, Y'), record: $variant);
});

it('can assert table column visibility', function () {
    livewire(ListProducts::class)
        ->loadTable()
        ->assertTableColumnVisible('sku')
        ->assertTableColumnVisible('name')
        ->assertTableColumnVisible('base_unit_name')
        ->assertTableColumnVisible('currentPrice.sale_price')
        ->assertTableColumnVisible('is_active');
});

it('can assert table column exists', function (string $column) {
    livewire(ListProducts::class)
        ->loadTable()
        ->assertTableColumnExists($column);
})->with(['product.name', 'sku', 'barcode', 'name', 'base_unit_name', 'currentPrice.sale_price', 'reorder_point', 'is_active', 'created_at', 'updated_at']);

it('renders empty state correctly', function () {
    ProductVariant::truncate();
    Product::truncate();

    livewire(ListProducts::class)
        ->loadTable()
        ->assertCountTableRecords(0);
});

it('has edit action on table row', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    livewire(ListProducts::class)
        ->loadTable()
        ->callAction(TestAction::make('edit')->table($variant))
        ->assertHasNoFormErrors();
});

it('has delete action on table row', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    livewire(ListProducts::class)
        ->loadTable()
        ->callAction(TestAction::make('delete')->table($variant))
        ->assertNotified();

    $variant->refresh();
    expect($variant->deleted_at)->not->toBeNull();
});

it('can create product variant', function () {
    $product = Product::factory()->create();

    livewire(CreateProduct::class)
        ->fillForm([
            'product_id' => $product->id,
            'sku' => 'SKU-TEST-001',
            'barcode' => '1234567890123',
            'name' => 'Test Variant',
            'base_unit_name' => 'piece',
            'reorder_point' => 10,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified()
        ->assertRedirect();

    assertDatabaseHas(ProductVariant::class, [
        'sku' => 'SKU-TEST-001',
        'name' => 'Test Variant',
        'base_unit_name' => 'piece',
        'reorder_point' => 10,
        'is_active' => true,
    ]);
});

it('can update product variant', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'sku' => 'SKU-OLD',
        'name' => 'Old Name',
    ]);

    livewire(EditProduct::class, ['record' => $variant->id])
        ->fillForm([
            'sku' => 'SKU-NEW',
            'name' => 'Updated Name',
            'reorder_point' => 20,
        ])
        ->call('save')
        ->assertNotified()
        ->assertHasNoFormErrors();

    assertDatabaseHas(ProductVariant::class, [
        'id' => $variant->id,
        'sku' => 'SKU-NEW',
        'name' => 'Updated Name',
        'reorder_point' => 20,
    ]);
});

it('can delete product variant', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    livewire(ViewProduct::class, ['record' => $variant->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    $variant->refresh();
    expect($variant->deleted_at)->not->toBeNull();
    expect(ProductVariant::withTrashed()->where('id', $variant->id)->exists())->toBeTrue();
});

it('can restore product variant', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    livewire(ViewProduct::class, ['record' => $variant->id])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    livewire(ViewProduct::class, ['record' => $variant->id])
        ->callAction(RestoreAction::class)
        ->assertNotified();

    assertDatabaseHas(ProductVariant::class, ['id' => $variant->id]);
});

it('can validate unique sku', function (string $column) {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create(['sku' => 'UNIQUE-SKU-TEST']);

    livewire(CreateProduct::class)
        ->fillForm([
            'product_id' => $product->id,
            'sku' => 'UNIQUE-SKU-TEST',
            'name' => 'Test Variant',
            'base_unit_name' => 'piece',
            'reorder_point' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors([$column => ['unique']]);
})->with(['sku']);

it('can validate unique barcode', function (string $column) {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create(['barcode' => 'UNIQUE-BARCODE-TEST']);

    livewire(CreateProduct::class)
        ->fillForm([
            'product_id' => $product->id,
            'sku' => 'ANOTHER-SKU',
            'barcode' => 'UNIQUE-BARCODE-TEST',
            'name' => 'Test Variant',
            'base_unit_name' => 'piece',
            'reorder_point' => 10,
        ])
        ->call('create')
        ->assertHasFormErrors([$column => ['unique']]);
})->with(['barcode']);

it('validates form data on create', function (array $data, array $errors) {
    $product = Product::factory()->create();
    $newVariantData = ProductVariant::factory()->make(['product_id' => $product->id]);

    livewire(CreateProduct::class)
        ->fillForm([
            'product_id' => $product->id,
            'sku' => $newVariantData->sku,
            'name' => $newVariantData->name,
            'base_unit_name' => $newVariantData->base_unit_name,
            'reorder_point' => $newVariantData->reorder_point,
            ...$data,
        ])
        ->call('create')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`sku` required' => [['sku' => null], ['sku' => 'required']],
    '`sku` max 255 characters' => [['sku' => Str::random(256)], ['sku' => 'max']],
    '`name` required' => [['name' => null], ['name' => 'required']],
    '`base_unit_name` required' => [['base_unit_name' => null], ['base_unit_name' => 'required']],
    '`reorder_point` required' => [['reorder_point' => null], ['reorder_point' => 'required']],
    '`reorder_point` numeric' => [['reorder_point' => 'abc'], ['reorder_point' => 'numeric']],
    '`barcode` max 255 characters' => [['barcode' => Str::random(256)], ['barcode' => 'max']],
]);

it('validates form data on update', function (array $data, array $errors) {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();
    $newVariantData = ProductVariant::factory()->make(['product_id' => $product->id]);

    livewire(EditProduct::class, ['record' => $variant->id])
        ->fillForm([
            'product_id' => $product->id,
            'sku' => $newVariantData->sku,
            'name' => $newVariantData->name,
            'base_unit_name' => $newVariantData->base_unit_name,
            'reorder_point' => $newVariantData->reorder_point,
            ...$data,
        ])
        ->call('save')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`sku` required' => [['sku' => null], ['sku' => 'required']],
    '`sku` max 255 characters' => [['sku' => Str::random(256)], ['sku' => 'max']],
    '`name` required' => [['name' => null], ['name' => 'required']],
    '`base_unit_name` required' => [['base_unit_name' => null], ['base_unit_name' => 'required']],
    '`reorder_point` required' => [['reorder_point' => null], ['reorder_point' => 'required']],
    '`reorder_point` numeric' => [['reorder_point' => 'abc'], ['reorder_point' => 'numeric']],
    '`barcode` max 255 characters' => [['barcode' => Str::random(256)], ['barcode' => 'max']],
]);

it('renders infolist entries on view page', function () {
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->for($product)->create();

    livewire(ViewProduct::class, ['record' => $variant->id])
        ->assertSchemaComponentExists('product.name')
        ->assertSchemaComponentExists('sku')
        ->assertSchemaComponentExists('barcode')
        ->assertSchemaComponentExists('name')
        ->assertSchemaComponentExists('currentPrice.cost_price')
        ->assertSchemaComponentExists('currentPrice.sale_price')
        ->assertSchemaComponentExists('unitConversions');
});
