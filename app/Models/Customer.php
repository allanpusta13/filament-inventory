<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'contact_person', 'phone', 'email', 'address', 'is_active'];

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
