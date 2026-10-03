<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Supplier — external-party master data for the purchasing module.
 *
 * Blueprint §3.12. Suppliers are minimal master data (A3): they carry
 * no code column and are identified by `name` (owner decision, §2.14).
 * The model is a thin persistence contract — the operational logic
 * (order lifecycle, receive, over-receive guard) lives entirely in
 * `PurchaseService` (§6.4) and the `PurchaseOrder` document.
 *
 * Soft deletes: the `deleted_at` column (§2.14) is handled by the
 * `SoftDeletes` trait. Deletion protection is policy-side
 * (`SupplierPolicy::delete()` §8.9, admin-only); there is no
 * model-level observer.
 *
 * A supplier referenced by a `PurchaseOrder` cannot be deleted at the
 * DB layer because the §2.16 `purchase_orders.supplier_id` FK uses
 * `restrictOnDelete`. That is the ultimate integrity guard — the policy
 * is an additional, softer pre-check.
 *
 * Factory: `App\Database\Factories\SupplierFactory` (§5.7).
 * Policy:  `App\Policies\SupplierPolicy` (§8.9).
 */
class Supplier extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Mass-assignable attributes (§2.14).
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    /**
     * Attribute casts (§3.12).
     *
     * @var array<string, string>
     */
    protected $casts = ['is_active' => 'boolean'];

    /**
     * The purchase orders placed with this supplier.
     */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
