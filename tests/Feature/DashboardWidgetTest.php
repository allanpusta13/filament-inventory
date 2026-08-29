<?php

declare(strict_types=1);

use App\Enums\MovementType;
use App\Enums\UserRole;
use App\Filament\Widgets\CategoryStockChart;
use App\Filament\Widgets\FastMovingStockChart;
use App\Filament\Widgets\LowStockAlertWidget;
use App\Filament\Widgets\QuickActionsWidget;
use App\Filament\Widgets\RecentStockActivityWidget;
use App\Filament\Widgets\StatsOverviewWidget;
use App\Filament\Widgets\StockByWarehouseWidget;
use App\Filament\Widgets\StockMovementTrendChart;
use App\Filament\Widgets\WarehouseFilterWidget;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;

use function Pest\Livewire\livewire;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => UserRole::Admin->value]);
    $this->staff = User::factory()->create(['role' => UserRole::WarehouseStaff->value]);
    $this->warehouse1 = Warehouse::factory()->create(['name' => 'WH Alpha', 'is_active' => true]);
    $this->warehouse2 = Warehouse::factory()->create(['name' => 'WH Beta', 'is_active' => true]);
    $this->staff->warehouses()->attach([$this->warehouse1->id]);
});

it('renders dashboard page for admin', function (): void {
    $this->actingAs($this->admin);

    $this->get(route('filament.admin.pages.dashboard'))
        ->assertOk();
});

it('renders dashboard page for warehouse staff', function (): void {
    $this->actingAs($this->staff);

    $this->get(route('filament.admin.pages.dashboard'))
        ->assertOk();
});

describe('StatsOverviewWidget', function (): void {
    it('admin can view stats overview', function (): void {
        $this->actingAs($this->admin);

        livewire(StatsOverviewWidget::class)
            ->assertOk();
    });

    it('warehouse staff can view stats overview', function (): void {
        $this->actingAs($this->staff);

        livewire(StatsOverviewWidget::class)
            ->assertOk();
    });

    it('warehouse staff sees only assigned warehouse stats', function (): void {
        $this->actingAs($this->staff);

        $product1 = Product::factory()->create(['name' => 'Staff Product']);
        $product2 = Product::factory()->create(['name' => 'Other WH Product']);

        StockMovement::factory()->create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 50,
        ]);

        StockMovement::factory()->create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse2->id,
            'type' => MovementType::Receive,
            'quantity' => 100,
        ]);

        livewire(StatsOverviewWidget::class)
            ->assertSee('50')
            ->assertDontSee('150');
    });

    it('warehouse staff sees correct low stock count for assigned warehouse', function (): void {
        $this->actingAs($this->staff);

        $product1 = Product::factory()->create(['reorder_point' => 50, 'name' => 'Staff Low Item']);
        $product2 = Product::factory()->create(['reorder_point' => 50, 'name' => 'Other WH Low Item']);

        StockMovement::factory()->create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 20,
        ]);

        StockMovement::factory()->create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse2->id,
            'type' => MovementType::Receive,
            'quantity' => 20,
        ]);

        livewire(StatsOverviewWidget::class)
            ->assertSee('Items Below Reorder Point')
            ->assertSee('1');
    });

    it('admin sees correct total sku count', function (): void {
        $this->actingAs($this->admin);

        Product::factory()->count(5)->create();

        livewire(StatsOverviewWidget::class)
            ->assertSee('5');
    });

    it('admin sees correct total stock quantity', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 100,
        ]);

        livewire(StatsOverviewWidget::class)
            ->assertSee('100');
    });

    it('admin sees low stock warning when products below reorder', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['reorder_point' => 50]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 20,
        ]);

        livewire(StatsOverviewWidget::class)
            ->assertSee('Items Below Reorder Point')
            ->assertSee('1');
    });

    it('admin sees zero stock count for out of stock products', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 50,
        ]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Ship,
            'quantity' => -50,
        ]);

        livewire(StatsOverviewWidget::class)
            ->assertSee('Zero Stock SKUs')
            ->assertSee('1');
    });

    it('admin warehouse filter scopes stats', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 100,
        ]);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse2->id,
            'type' => MovementType::Receive,
            'quantity' => 200,
        ]);

        session(['admin_warehouse_filter' => $this->warehouse1->id]);

        livewire(StatsOverviewWidget::class)
            ->assertSee('100');
    });
});

describe('LowStockAlertWidget', function (): void {
    it('admin can view low stock alerts', function (): void {
        $this->actingAs($this->admin);

        livewire(LowStockAlertWidget::class)
            ->assertOk();
    });

    it('warehouse staff can view low stock alerts', function (): void {
        $this->actingAs($this->staff);

        livewire(LowStockAlertWidget::class)
            ->assertOk();
    });

    it('shows products at or below reorder point', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['reorder_point' => 50, 'name' => 'Low Widget Item']);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 30,
        ]);

        livewire(LowStockAlertWidget::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$product]);
    });

    it('does not show products above reorder point', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['reorder_point' => 10, 'name' => 'High Stock Item']);

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 100,
        ]);

        livewire(LowStockAlertWidget::class)
            ->loadTable()
            ->assertCanNotSeeTableRecords([$product]);
    });

    it('warehouse staff sees only assigned warehouse low stock', function (): void {
        $this->actingAs($this->staff);

        $product1 = Product::factory()->create(['reorder_point' => 50, 'name' => 'Staff Low Item']);
        $product2 = Product::factory()->create(['reorder_point' => 50, 'name' => 'Other WH Item']);

        StockMovement::factory()->create([
            'product_id' => $product1->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 20,
        ]);

        StockMovement::factory()->create([
            'product_id' => $product2->id,
            'warehouse_id' => $this->warehouse2->id,
            'type' => MovementType::Receive,
            'quantity' => 20,
        ]);

        livewire(LowStockAlertWidget::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$product1])
            ->assertCanNotSeeTableRecords([$product2]);
    });
});

describe('QuickActionsWidget', function (): void {
    it('admin can view quick actions', function (): void {
        $this->actingAs($this->admin);

        livewire(QuickActionsWidget::class)
            ->assertOk();
    });

    it('warehouse staff can view quick actions', function (): void {
        $this->actingAs($this->staff);

        livewire(QuickActionsWidget::class)
            ->assertOk();
    });
});

describe('StockByWarehouseWidget', function (): void {
    it('admin can view stock by warehouse', function (): void {
        $this->actingAs($this->admin);

        livewire(StockByWarehouseWidget::class)
            ->assertOk();
    });

    it('warehouse staff cannot view stock by warehouse', function (): void {
        $this->actingAs($this->staff);

        $isVisible = StockByWarehouseWidget::canView();

        expect($isVisible)->toBeFalse();
    });

    it('admin sees all active warehouses', function (): void {
        $this->actingAs($this->admin);

        livewire(StockByWarehouseWidget::class)
            ->assertSee('WH Alpha')
            ->assertSee('WH Beta');
    });

    it('admin warehouse filter scopes warehouse list', function (): void {
        $this->actingAs($this->admin);

        session(['admin_warehouse_filter' => $this->warehouse1->id]);

        livewire(StockByWarehouseWidget::class)
            ->assertSee('WH Alpha')
            ->assertDontSee('WH Beta');
    });
});

describe('StockMovementTrendChart', function (): void {
    it('admin can view stock movement trend chart', function (): void {
        $this->actingAs($this->admin);

        livewire(StockMovementTrendChart::class)
            ->assertOk();
    });

    it('warehouse staff cannot view stock movement trend chart', function (): void {
        $this->actingAs($this->staff);

        $isVisible = StockMovementTrendChart::canView();

        expect($isVisible)->toBeFalse();
    });

    it('chart renders with data', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create();

        StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 50,
            'created_at' => now()->subDays(5),
        ]);

        livewire(StockMovementTrendChart::class)
            ->assertOk();
    });
});

describe('CategoryStockChart', function (): void {
    it('admin can view category stock chart', function (): void {
        $this->actingAs($this->admin);

        livewire(CategoryStockChart::class)
            ->assertOk();
    });

    it('warehouse staff cannot view category stock chart', function (): void {
        $this->actingAs($this->staff);

        $isVisible = CategoryStockChart::canView();

        expect($isVisible)->toBeFalse();
    });
});

describe('FastMovingStockChart', function (): void {
    it('admin can view fast moving stock chart', function (): void {
        $this->actingAs($this->admin);

        livewire(FastMovingStockChart::class)
            ->assertOk();
    });

    it('warehouse staff can view fast moving stock chart', function (): void {
        $this->actingAs($this->staff);

        livewire(FastMovingStockChart::class)
            ->assertOk();
    });

    it('chart shows top products by movement frequency', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['name' => 'Frequent Mover']);

        for ($i = 0; $i < 10; $i++) {
            StockMovement::factory()->create([
                'product_id' => $product->id,
                'warehouse_id' => $this->warehouse1->id,
                'type' => MovementType::Receive,
                'quantity' => 10,
            ]);
        }

        livewire(FastMovingStockChart::class)
            ->assertOk();
    });
});

describe('RecentStockActivityWidget', function (): void {
    it('admin can view recent stock activity', function (): void {
        $this->actingAs($this->admin);

        livewire(RecentStockActivityWidget::class)
            ->assertOk();
    });

    it('warehouse staff can view recent stock activity', function (): void {
        $this->actingAs($this->staff);

        livewire(RecentStockActivityWidget::class)
            ->assertOk();
    });

    it('shows recent movements in table', function (): void {
        $this->actingAs($this->admin);

        $product = Product::factory()->create(['name' => 'Recent Widget Product']);
        $movement = StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 75,
        ]);

        livewire(RecentStockActivityWidget::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$movement]);
    });

    it('warehouse staff sees only assigned warehouse movements', function (): void {
        $this->actingAs($this->staff);

        $product = Product::factory()->create();
        $movement1 = StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse1->id,
            'type' => MovementType::Receive,
            'quantity' => 25,
        ]);

        $movement2 = StockMovement::factory()->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse2->id,
            'type' => MovementType::Receive,
            'quantity' => 50,
        ]);

        livewire(RecentStockActivityWidget::class)
            ->loadTable()
            ->assertCanSeeTableRecords([$movement1])
            ->assertCanNotSeeTableRecords([$movement2]);
    });
});

describe('WarehouseFilterWidget', function (): void {
    it('admin can view warehouse filter', function (): void {
        $this->actingAs($this->admin);

        $isVisible = WarehouseFilterWidget::canView();

        expect($isVisible)->toBeTrue();
    });

    it('warehouse staff cannot view warehouse filter', function (): void {
        $this->actingAs($this->staff);

        $isVisible = WarehouseFilterWidget::canView();

        expect($isVisible)->toBeFalse();
    });

    it('stores selected warehouse in session', function (): void {
        $this->actingAs($this->admin);

        session(['admin_warehouse_filter' => $this->warehouse1->id]);

        $selected = WarehouseFilterWidget::getSelectedWarehouseId();

        expect($selected)->toBe($this->warehouse1->id);
    });

    it('returns null when no warehouse selected', function (): void {
        $this->actingAs($this->admin);

        session()->forget('admin_warehouse_filter');

        $selected = WarehouseFilterWidget::getSelectedWarehouseId();

        expect($selected)->toBeNull();
    });
});
