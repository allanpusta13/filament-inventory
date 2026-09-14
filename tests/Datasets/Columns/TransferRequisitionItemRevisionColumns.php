<?php

declare(strict_types=1);

namespace Tests\Datasets\Columns;

class TransferRequisitionItemRevisionColumns
{
    public static function index(): array
    {
        return [
            'transferRequisitionItem.transferRequisition.reference_code',
            'productVariant.sku',
            'substituteProductVariant.sku',
            'proposed_unit_name',
            'proposed_unit_ratio',
            'proposed_qty',
            'proposed_base_qty',
            'negotiation_reason',
            'side',
            'status',
            'created_at',
        ];
    }

    public static function sortable(): array
    {
        return [
            'transferRequisitionItem.transferRequisition.reference_code',
            'productVariant.sku',
            'side',
            'status',
            'created_at',
        ];
    }

    public static function filterable(): array
    {
        return ['status', 'side', 'transfer_requisition_item_id'];
    }

    public static function searchable(): array
    {
        return ['transferRequisitionItem.transferRequisition.reference_code', 'productVariant.sku'];
    }

    public static function view(): array
    {
        return [
            'transferRequisitionItem.transferRequisition.reference_code',
            'productVariant.sku',
            'substituteProductVariant.sku',
            'proposed_unit_name',
            'proposed_unit_ratio',
            'proposed_qty',
            'proposed_base_qty',
            'negotiation_reason',
            'side',
            'status',
            'respondsToRevision.id',
            'responded_at',
            'created_at',
        ];
    }
}