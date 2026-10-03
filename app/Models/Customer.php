<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Customer — external-party master data for the sales module.
 *
 * Blueprint §3.13. Symmetric to `Supplier` (§3.12): minimal master data
 * (A3), no `code` column, identified by `name` (owner decision, §2.15).
 * The operational logic (confirm, dispatch, return, over-dispatch and
 * over-return guards) lives entirely in `SalesService` (§6.5) and the
 * `SalesOrder` document.
 *
 * Soft deletes: the `deleted_at` column (§2.15) is handled by the
 * `SoftDeletes` trait. Deletion protection is policy-side
 * (`CustomerPolicy::delete()` §8.10, admin-only); there is no
 * model-level observer.
 *
 * A customer referenced by a `SalesOrder` cannot be deleted at the DB
 * layer because the §2.18 `sales_orders.customer_id` FK uses
 * `restrictOnDelete`. That is the ultimate integrity guard — the policy
 * is an additional, softer pre-check.
 *
 * Factory: `App\Database\Factories\CustomerFactory` (§5.8).
 * Policy:  `App\Policies\CustomerPolicy` (§8.10).
 */
class Customer extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Mass-assignable attributes (§2.15).
     *
     * @var array<int, string>
     */
    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    /**
     * Attribute casts (§3.13).
     *
     * @var array<string, string>
     */
    protected $casts = ['is_active' => 'boolean'];

    /**
     * The sales orders placed by this customer.
     */
    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }
}
