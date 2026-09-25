<?php

declare(strict_types=1);

return [
    // Common notifications
    'created' => 'Nalikha ng matagumpay ang record.',
    'updated' => 'Na-update ng matagumpay ang record.',
    'deleted' => 'Binura ng matagumpay ang record.',
    'restored' => 'Naibalik ng matagumpay ang record.',
    'saved' => 'Na-save ng matagumpay ang changes.',
    'error' => 'May error. Subukan muli.',
    'permission_denied' => 'Walang permission para gawin ito.',

    // Direct Transfer notifications
    'transfer_executed' => 'Na-execute ang Transfer',
    'transfer_completed' => 'Natapos ng matagumpay ang direct transfer :code.',
    'transfer_failed' => 'Nabigo ang transfer. Suriin ang details at subukan muli.',
    'insufficient_stock' => 'Kulang ang stock para sa :sku sa origin warehouse. Available: :available, Hiningi: :requested.',

    // Transfer Requisition notifications
    'requisition_created' => 'Nalikha ang transfer requisition.',
    'requisition_confirmed' => 'Kinumpirma ang transfer requisition.',
    'requisition_dispatched' => 'Ipinadala ang transfer requisition.',
    'requisition_received' => 'Natanggap ang transfer requisition.',
    'requisition_completed' => 'Natapos ang transfer requisition.',
    'requisition_cancelled' => 'Kanselado ang transfer requisition.',
    'negotiation_started' => 'Nagsimula ang negotiation.',
    'negotiation_accepted' => 'Tinanggap ang negotiation.',
    'negotiation_rejected' => 'Tinanggihan ang negotiation.',

    // Purchase Order notifications
    'po_created' => 'Nalikha ang purchase order.',
    'po_confirmed' => 'Kinumpirma ang purchase order.',
    'po_received' => 'Natanggap ang purchase order.',
    'po_cancelled' => 'Kanselado ang purchase order.',
    'cost_updated' => 'Na-update ang cost price sa pagtanggap.',

    // Sales Order notifications
    'so_created' => 'Nalikha ang sales order.',
    'so_confirmed' => 'Kinumpirma ang sales order.',
    'so_dispatched' => 'Ipinadala ang sales order.',
    'return_recorded' => 'Naitala ang return.',

    // Inventory notifications
    'stock_adjusted' => 'Na-adjust ng matagumpay ang stock.',
    'price_set' => 'Naitakda ng matagumpay ang presyo.',

    // Scan notifications
    'scan_success' => 'Na-process ng matagumpay ang scan.',
    'scan_duplicate' => 'Nadetect ang duplicate scan. Walang changes.',
    'scan_error' => 'Nabigo ang scan. Subukan muli.',

    // Authentication
    'login_success' => 'Matagumpay na login.',
    'logout_success' => 'Matagumpay na logout.',
    'unauthorized' => 'Hindi awtorisadong access.',

    // Validation
    'validation_failed' => 'Nabigo ang validation. Suriin ang form.',
];
