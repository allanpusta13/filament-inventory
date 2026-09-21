<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

final class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        /* TODO: Please implement your own logic here. */
        return true; // str_ends_with($this->email, '@larament.test');
    }

    public function isAdmin(): bool
    {
        return ($this->attributes['role'] ?? null) === UserRole::ADMIN->value;
    }

    public function canAccessWarehouse(Warehouse $warehouse): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return $this->warehouses->contains($warehouse);
    }

    /**
     * @return BelongsToMany<Warehouse, $this>
     */
    public function warehouses(): BelongsToMany
    {
        return $this->belongsToMany(Warehouse::class, 'user_warehouse');
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @phpstan-ignore-next-line */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        /** @phpstan-ignore-next-line */
        return $this->app_authentication_recovery_codes;
    }

    public function saveAppAuthenticationRecoveryCodes(?array $codes): void
    {
        /** @phpstan-ignore-next-line  */
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }

    public function isAuditor(): bool
    {
        return ($this->attributes['role'] ?? null) === UserRole::AUDITOR->value;
    }

    public function hasAccessToWarehouse(int $warehouseId): bool
    {
        return $this->isAdmin() || $this->isAuditor() || $this->warehouses()->where('id', $warehouseId)->exists();
    }

    /**
     * Check if the user is a branch manager.
     */
    public function isBranchManager(): bool
    {
        return ($this->attributes['role'] ?? null) === UserRole::BRANCH_MANAGER->value;
    }

    /**
     * Check if the user is a warehouse staff.
     */
    public function isWarehouseStaff(): bool
    {
        return ($this->attributes['role'] ?? null) === UserRole::WAREHOUSE_STAFF->value;
    }

    /**
     * Check if the user is a guest (no access).
     */
    public function isGuest(): bool
    {
        return ($this->attributes['role'] ?? null) === UserRole::GUEST->value;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
            'role' => UserRole::class,
        ];
    }
}
