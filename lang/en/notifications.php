<?php

declare(strict_types=1);

return [
    // Common notifications
    'created' => 'Record created successfully.',
    'updated' => 'Record updated successfully.',
    'deleted' => 'Record deleted successfully.',
    'restored' => 'Record restored successfully.',
    'saved' => 'Changes saved successfully.',
    'error' => 'An error occurred. Please try again.',
    'permission_denied' => 'You do not have permission to perform this action.',

    // Direct Transfer notifications
    'transfer_executed' => 'Transfer Executed',
    'transfer_completed' => 'Direct transfer :code has been completed successfully.',
    'transfer_failed' => 'Transfer failed. Please check the details and try again.',
    'insufficient_stock' => 'Insufficient stock for :sku at origin warehouse. Available: :available, Requested: :requested.',

    // Transfer Requisition notifications
    'requisition_created' => 'Transfer requisition created.',
    'requisition_confirmed' => 'Transfer requisition confirmed.',
    'requisition_dispatched' => 'Transfer requisition dispatched.',
    'requisition_received' => 'Transfer requisition received.',
    'requisition_completed' => 'Transfer requisition completed.',
    'requisition_cancelled' => 'Transfer requisition cancelled.',
    'negotiation_started' => 'Negotiation started.',
    'negotiation_accepted' => 'Negotiation accepted.',
    'negotiation_rejected' => 'Negotiation rejected.',

    // Purchase Order notifications
    'po_created' => 'Purchase order created.',
    'po_confirmed' => 'Purchase order confirmed.',
    'po_received' => 'Purchase order received.',
    'po_cancelled' => 'Purchase order cancelled.',
    'cost_updated' => 'Cost price updated on receipt.',

    // Sales Order notifications
    'so_created' => 'Sales order created.',
    'so_confirmed' => 'Sales order confirmed.',
    'so_dispatched' => 'Sales order dispatched.',
    'return_recorded' => 'Return recorded.',

    // Inventory notifications
    'stock_adjusted' => 'Stock adjusted successfully.',
    'price_set' => 'Price set successfully.',

    // Scan notifications
    'scan_success' => 'Scan processed successfully.',
    'scan_duplicate' => 'Duplicate scan detected. No changes made.',
    'scan_error' => 'Scan failed. Please try again.',

    // Scan to receive
    'scan.signature_expired_title' => 'Signature Expired or Invalid',
    'scan.signature_expired_body' => 'The scanned Stock Transfer Note is older than 7 days or has been modified. Please generate a fresh manifest.',
    'scan.access_denied_title' => 'Access Denied',
    'scan.access_denied_body' => 'You are not assigned to the destination warehouse linked to this transfer requisition.',
    'scan.invalid_status_title' => 'Invalid Status',
    'scan.invalid_status_body' => 'This transfer requisition is not ready for receiving.',
    'scan.receive_complete_title' => 'Receiving Complete',
    'scan.receive_complete_body' => 'Transfer requisition :reference_code has been successfully received.',

    // Authentication
    'login_success' => 'Logged in successfully.',
    'logout_success' => 'Logged out successfully.',
    'unauthorized' => 'Unauthorized access.',

    // Validation
    'validation_failed' => 'Validation failed. Please check the form.',
];
