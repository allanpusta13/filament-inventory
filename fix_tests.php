<?php

declare(strict_types=1);
$content = file_get_contents('tests/Unit/Services/SalesServiceTest.php');

// Fix all missing => arrows
$content = str_replace(
    "'product_variant_id' \$variant->id",
    "'product_variant_id' => \$variant->id",
    $content
);

$content = str_replace(
    "'approved_base_qty' 50,",
    "'approved_base_qty' => 50,",
    $content
);

$content = str_replace(
    "'warehouse_id' => \$warehouse->id,\n            'type' StockMovementType::Purchase,\n            'quantity' 100,",
    "'warehouse_id' => \$warehouse->id,\n            'type' => StockMovementType::Purchase,\n            'quantity' => 100,",
    $content
);

$content = str_replace(
    "'product_variant_id' \$variant->id,\n            'qty' => 5,\n            'unit_ratio' 10,\n            'base_qty' 50,",
    "'product_variant_id' => \$variant->id,\n            'qty' => 5,\n            'unit_ratio' => 10,\n            'base_qty' => 50,",
    $content
);

$content = str_replace(
    "])->create(['sales_order_id' \$order->id]);",
    "])->create(['sales_order_id' => \$order->id]);",
    $content
);

$content = str_replace(
    "'item_id' \$item->id,\n                'dispatched_base_qty' 50,\n                'unit_name' \$item->unit_name,\n                'unit_ratio' \$item->unit_ratio,",
    "'item_id' => \$item->id,\n                'dispatched_base_qty' => 50,\n                'unit_name' => \$item->unit_name,\n                'unit_ratio' => \$item->unit_ratio,",
    $content
);

$content = str_replace(
    "'product_variant_id' \$variant->id,\n            'sale_price'",
    "'product_variant_id' => \$variant->id,\n            'sale_price'",
    $content
);

$content = str_replace(
    'expect(fn $this->service->dispatchSale',
    'expect(fn () => $this->service->dispatchSale',
    $content
);

file_put_contents('tests/Unit/Services/SalesServiceTest.php', $content);
echo "Done\n";
