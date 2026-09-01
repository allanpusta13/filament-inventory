<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Str;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertSoftDeleted;
use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
    $this->staff = User::factory()->create(['role' => 'warehouse_staff']);
});

it('can render the index page', function (): void {
    livewire(ListProducts::class)
        ->assertOk();
});

it('can render the create page', function (): void {
    $this->actingAs($this->admin);

    livewire(CreateProduct::class)
        ->assertOk();
});

it('can render the edit page', function (): void {
    $this->actingAs($this->admin);

    $product = Product::factory()->create();

    livewire(EditProduct::class, [
        'record' => $product->id,
    ])
        ->assertOk()
        ->assertSchemaStateSet([
            'sku' => $product->sku,
            'name' => $product->name,
        ]);
});

it('has column', function (string $column): void {
    livewire(ListProducts::class)
        ->assertTableColumnExists($column);
})->with(['sku', 'name', 'category', 'created_at', 'updated_at']);

it('can render column', function (string $column): void {
    livewire(ListProducts::class)
        ->assertCanRenderTableColumn($column);
})->with(['sku', 'name', 'category']);

it('can create a product', function (): void {
    $this->actingAs($this->admin);

    $product = Product::factory()->make();

    livewire(CreateProduct::class)
        ->fillForm([
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category,
            'unit' => $product->unit,
            'reorder_point' => $product->reorder_point,
        ])
        ->call('create')
        ->assertNotified();

    assertDatabaseHas(Product::class, [
        'sku' => $product->sku,
        'name' => $product->name,
    ]);
});

it('can update a product', function (): void {
    $this->actingAs($this->admin);

    $product = Product::factory()->create();
    $newData = Product::factory()->make();

    livewire(EditProduct::class, [
        'record' => $product->id,
    ])
        ->fillForm([
            'name' => $newData->name,
        ])
        ->call('save')
        ->assertNotified();

    assertDatabaseHas(Product::class, [
        'id' => $product->id,
        'name' => $newData->name,
    ]);
});

it('can delete a product', function (): void {
    $this->actingAs($this->admin);

    $product = Product::factory()->create();

    livewire(EditProduct::class, [
        'record' => $product->id,
    ])
        ->callAction(DeleteAction::class)
        ->assertNotified()
        ->assertRedirect();

    assertSoftDeleted($product);
});

it('can validate unique sku', function (): void {
    $this->actingAs($this->admin);

    $product = Product::factory()->create();

    livewire(CreateProduct::class)
        ->fillForm(['sku' => $product->sku])
        ->call('create')
        ->assertHasFormErrors(['sku' => ['unique']]);
});

it('validates the form data', function (array $data, array $errors): void {
    $this->actingAs($this->admin);

    $product = Product::factory()->create();
    $newData = Product::factory()->make();

    livewire(EditProduct::class, [
        'record' => $product->id,
    ])
        ->fillForm([
            'name' => $newData->name,
            'sku' => $newData->sku,
            ...$data,
        ])
        ->call('save')
        ->assertHasFormErrors($errors)
        ->assertNotNotified();
})->with([
    '`name` is required' => [['name' => null], ['name' => 'required']],
    '`sku` is required' => [['sku' => null], ['sku' => 'required']],
    '`sku` is max 255 characters' => [['sku' => Str::random(256)], ['sku' => 'max']],
]);

it('shows total stock column', function (): void {
    $product = Product::factory()->create();
    $variant = App\Models\ProductVariant::factory()->create(['product_id' => $product->id]);
    $warehouse = Warehouse::factory()->create();

    $variant->stockMovements()->create([
        'warehouse_id' => $warehouse->id,
        'type' => MovementType::Receive,
        'quantity' => 100,
    ]);

    livewire(ListProducts::class)
        ->assertOk();
});

it('admin can create products', function (): void {
    $this->actingAs($this->admin);

    livewire(CreateProduct::class)
        ->assertOk();
});

it('warehouse staff cannot create products', function (): void {
    $this->actingAs($this->staff);

    livewire(CreateProduct::class)
        ->assertForbidden();
});
