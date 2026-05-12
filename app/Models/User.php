<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Spatie\Permission\Contracts\Permission as PermissionContract;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, HasPanelShield;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'department_id',
        'denied_permissions',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at'  => 'datetime',
            'password'           => 'hashed',
            'denied_permissions' => 'array',
        ];
    }

    /**
     * Check if a permission has been explicitly denied for this user.
     * Denied permissions override both role and direct permissions.
     */
    public function isDenied(string $permission): bool
    {
        return in_array($permission, $this->denied_permissions ?? []);
    }

    /**
     * Override hasPermissionTo so denied permissions are always blocked,
     * even if the role grants them.
     *
     * We cannot use parent:: — the method lives in the HasPermissions trait,
     * not in Authenticatable. We also cannot pass a raw string to
     * hasPermissionViaRole() — it requires a Permission model instance.
     * So we resolve the string first, then delegate to the trait methods.
     */
    public function hasPermissionTo($permission, $guardName = null): bool
    {
        // Resolve to a Permission model instance if a string was passed
        if (is_string($permission)) {
            $permissionName = $permission;
            $permission = app(PermissionContract::class)::findByName(
                $permissionName,
                $guardName ?? $this->getDefaultGuardName()
            );
        } else {
            $permissionName = $permission->name;
        }

        if ($this->isDenied($permissionName)) {
            return false;
        }

        return $this->hasDirectPermission($permission)
            || $this->hasPermissionViaRole($permission);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return match($panel->getId()) {
            'superadmin'  => $this->hasRole('super_admin'),
            'hr'          => $this->hasAnyRole(['hr_admin_specialist', 'hr_manager']),
            'operation'   => $this->hasAnyRole(['operation_specialist', 'operation_manager']),
            'payroll'     => $this->hasAnyRole(['payroll_specialist', 'payroll_manager']),
            'finance'     => $this->hasAnyRole(['finance_specialist', 'finance_manager']),
            'purchasing'  => $this->hasAnyRole(['purchasing_specialist']),
        };
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Departments::class);
    }

    public function assignedClients(): BelongsToMany
    {
        return $this->belongsToMany(
            Clients::class,
            'client_user',
            'user_id',
            'client_id'
        );
    }

    /**
     * True if this user is a manager / can see all records.
     */
    public function isManager(): bool
    {
        return $this->hasRole(['payroll_manager', 'super_admin']);
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = bcrypt($data['temporary_password'] ?? \Illuminate\Support\Str::random(12));
        unset($data['temporary_password']);
        return $data;
    }
}