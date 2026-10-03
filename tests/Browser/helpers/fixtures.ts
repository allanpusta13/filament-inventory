import { tinker } from "./artisan";

/**
 * Per-test fixture helpers.
 *
 * Each helper runs a `tinker --execute` snippet that creates its
 * entities and prints the primary key to stdout. The caller parses the
 * id from the output.
 *
 * The snippet returns the model's `id` at the end so the console
 * prints it. `tinker --execute` prints the last expression's value.
 */

/** Parse the last numeric line from tinker output. */
function parseId(output: string): number {
    const match = output.match(/(\d+)\s*$/m);
    if (!match) {
        throw new Error(`Could not parse id from tinker output:\n${output}`);
    }
    return parseInt(match[1], 10);
}

export function makeWarehouse(name = "Test Warehouse"): { id: number } {
    const out = tinker(`
    \\App\\Models\\Warehouse::factory()->create(['name' => '${name}'])->id;
  `);
    return { id: parseId(out) };
}

export function makeVariant(
    sku = "SKU-TEST-AA",
    baseUnit = "pc",
): { id: number } {
    const out = tinker(`
    \\App\\Models\\ProductVariant::factory()->create(['sku' => '${sku}', 'base_unit_name' => '${baseUnit}'])->id;
  `);
    return { id: parseId(out) };
}

/**
 * Create a confirmed requisition with stock seeded and one item.
 * Returns the requisition id and item id.
 */
export function makeConfirmedRequisition(
    fromWarehouseId: number,
    toWarehouseId: number,
    variantId: number,
    qty = 10,
): { requisitionId: number; itemId: number } {
    const out = tinker(`
    $from = \\App\\Models\\Warehouse::find(${fromWarehouseId});
    $variant = \\App\\Models\\ProductVariant::find(${variantId});

    \\App\\Models\\StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => $from->id,
        'type' => \\App\\Enums\\StockMovementType::Adjustment,
        'quantity' => ${qty * 20},
    ]);

    $req = \\App\\Models\\TransferRequisition::factory()->requested()->create([
        'from_warehouse_id' => $from->id,
        'to_warehouse_id' => ${toWarehouseId},
    ]);

    $item = \\App\\Models\\TransferRequisitionItem::factory()->create([
        'transfer_requisition_id' => $req->id,
        'product_variant_id' => $variant->id,
        'requested_unit_name' => 'pc',
        'requested_unit_ratio' => 1,
        'requested_qty' => ${qty},
        'requested_base_qty' => ${qty},
    ]);

    \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'admin@example.test')->first());
    app(\\App\\Services\\TransferRequisitionService::class)->confirm($req);

    echo $req->id . '-' . $item->id;
  `);

    const match = out.match(/(\d+)-(\d+)/);
    if (!match) {
        throw new Error(`Could not parse requisition/item ids from:\n${out}`);
    }

    return {
        requisitionId: parseInt(match[1], 10),
        itemId: parseInt(match[2], 10),
    };
}

export function makeConfirmedSalesOrder(
    warehouseId: number,
    variantId: number,
    qty = 10,
): { orderId: number; itemId: number } {
    const out = tinker(`
    $variant = \\App\\Models\\ProductVariant::find(${variantId});

    \\App\\Models\\StockMovement::factory()->create([
        'product_variant_id' => $variant->id,
        'warehouse_id' => ${warehouseId},
        'type' => \\App\\Enums\\StockMovementType::Adjustment,
        'quantity' => ${qty * 10},
    ]);

    $so = \\App\\Models\\SalesOrder::factory()->create([
        'warehouse_id' => ${warehouseId},
        'status' => \\App\\Enums\\SalesOrderStatus::Draft,
    ]);

    $item = \\App\\Models\\SalesOrderItem::factory()->create([
        'sales_order_id' => $so->id,
        'product_variant_id' => $variant->id,
        'unit_name' => 'pc',
        'unit_ratio' => 1,
        'qty' => ${qty},
        'base_qty' => ${qty},
        'unit_sale_price_snapshot' => '10.0000',
    ]);

    \\Illuminate\\Support\\Facades\\Auth::login(\\App\\Models\\User::where('email', 'admin@example.test')->first());
    app(\\App\\Services\\SalesService::class)->confirmSalesOrder($so);

    echo $so->id . '-' . $item->id;
  `);

    const match = out.match(/(\d+)-(\d+)/);
    if (!match) {
        throw new Error(`Could not parse order/item ids from:\n${out}`);
    }

    return {
        orderId: parseInt(match[1], 10),
        itemId: parseInt(match[2], 10),
    };
}

export function makeOrderedPurchaseOrder(
    warehouseId: number,
    variantId: number,
    qty = 10,
): { orderId: number; itemId: number } {
    const out = tinker(`
    $supplier = \\App\\Models\\Supplier::first() ?: \\App\\Models\\Supplier::factory()->create();
    $admin = \\App\\Models\\User::where('email', 'admin@example.test')->first();

    $po = \\App\\Models\\PurchaseOrder::factory()->ordered()->create([
        'supplier_id' => $supplier->id,
        'warehouse_id' => ${warehouseId},
        'ordered_by' => $admin->id,
    ]);

    $item = \\App\\Models\\PurchaseOrderItem::factory()->create([
        'purchase_order_id' => $po->id,
        'product_variant_id' => ${variantId},
        'ordered_unit_name' => 'pc',
        'ordered_unit_ratio' => 1,
        'ordered_qty' => ${qty},
        'ordered_base_qty' => ${qty},
    ]);

    echo $po->id . '-' . $item->id;
  `);

    const match = out.match(/(\d+)-(\d+)/);
    if (!match) {
        throw new Error(`Could not parse order/item ids from:\n${out}`);
    }

    return {
        orderId: parseInt(match[1], 10),
        itemId: parseInt(match[2], 10),
    };
}
