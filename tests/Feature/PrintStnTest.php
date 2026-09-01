<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\TransferRequisition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use stdClass;
use Tests\TestCase;

final class PrintStnTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_generate_pdf_stn(): void
    {
        // Check that the Pdf facade exists
        $this->assertTrue(class_exists('\\Barryvdh\\DomPDF\\Facade\\Pdf'));

        // Check that the view exists
        $this->assertTrue(View::exists('pdf.stn-manifest'));

        // Create a dummy requisition with necessary relations as stdClass objects
        $requisition = new TransferRequisition;
        $requisition->reference_code = 'STN-TEST-001';
        $requisition->dispatched_at = now();
        $requisition->requestedBy = (object) ['name' => 'Test User'];
        $requisition->fromWarehouse = (object) ['name' => 'From Warehouse', 'code' => 'FW'];
        $requisition->toWarehouse = (object) ['name' => 'To Warehouse', 'code' => 'TW'];

        $item = new stdClass;
        $item->variant = (object) [
            'sku' => 'SKU-TEST',
            'name' => 'Test Variant',
            'base_unit_name' => 'unit',
        ];
        $item->requested_base_qty = 10;
        $item->approved_base_qty = 10;

        $requisition->items = new Collection([$item]);

        $qrCode = '<svg width="140" height="140"><circle cx="70" cy="70" r="60" stroke="black" stroke-width="4" fill="white"/></svg>';

        // Render the view
        $html = View::make('pdf.stn-manifest', [
            'requisition' => $requisition,
            'qrCode' => $qrCode,
        ])->render();

        // Assert that the HTML contains expected strings
        $this->assertStringContainsString('Stock Transfer Note', $html);
        $this->assertStringContainsString('STN-TEST-001', $html);
        $this->assertStringContainsString('From Warehouse', $html);
        $this->assertStringContainsString('To Warehouse', $html);
        $this->assertStringContainsString('SKU-TEST', $html);
        $this->assertStringContainsString('Test Variant', $html);
        $this->assertStringContainsString('unit', $html);
        $this->assertStringContainsString('10', $html);
    }
}
