<?php

declare(strict_types=1);

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Product;
use App\Models\ProductVariant;
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
})->with(['sku', 'barcode', 'name', 'base_unit_name', 'reorder_point', 'is_active']);

it('can sort column', function (string $column) {
    $product = Product::factory()->create();
    $variants = ProductVariant::factory()->count(5)->for($product)->create();

    livewire(ListProducts::class)
        ->loadTable()
        ->sortTable($column)
        ->assertCanSeeTableRecords($variants->sortBy($column), inOrder: true)
        ->sortTable($column, 'desc')
        ->assertCanSeeTableRecords($variants->sortByDesc($column), inOrder: true);
})->with(['sku', 'name', 'reorder_point']);

it('can search column', function (string $column) {
    $product = Product::factory()->create();
    $variant1 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-001', 'name' => 'Test Variant 1']);
    $variant2 = ProductVariant::factory()->for($product)->create(['sku' => 'SKU-002', 'name' => 'Test Variant 2']);

    livewire(ListProducts::class)
        ->loadTable()
        ->searchTable('SKU-001')
        ->assertCanSeeTableRecords([$variant1])
        ->assertCanNotSeeTableRecords([$variant2]);
})->with(['sku', 'name', 'barcode', 'base_unit_name']);

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
            'attributes' => ['color' => 'red'],
            'is_active' => true,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors();

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
    // With soft deletes, the record still exists but is soft deleted
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

    $variant->refresh();
    expect($variant->deleted_at)->toBeNull();
    assertDatabaseHas(ProductVariant::class, ['id' => $variant->id]);
});

it('can validate unique sku', function (string $column) {
    $product = Product::factory()->create();
    $record = ProductVariant::factory()->for($product)->create();

    $formData = [
        'product_id' => $product->id,
        'name' => 'Test',
        'base_unit_name' => 'piece',
        'reorder_point' => 0,
        'is_active' => true,
    ];

    if ($column === 'sku') {
        $formData['sku'] = $record->sku;
        $formData['barcode'] = 'UNIQUE-BARCODE-TEST';
    } else {
        $formData['barcode'] = $record->barcode;
        $formData['sku'] = 'UNIQUE-SKU-TEST';
    }

    livewire(CreateProduct::class)
        ->fillForm($formData)
        ->call('create')
        ->assertHasFormErrors([$column => ['unique']]);

})->with(['sku', 'barcode']);

it('validates form data', function (array $data, array $errors) {
    $product = Product::factory()->create();
    $newVariantData = ProductVariant::factory()->make();

    livewire(EditProduct::class, [
        'record' => ProductVariant::factory()->for($product)->create()->id,
    ])
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