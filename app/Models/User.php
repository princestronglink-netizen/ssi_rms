<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(Panel $panel): bool 
    {
        return match($panel->getId()) {
            'superadmin'        => $this->hasRole('super_admin'),
            'hr'                => $this->hasAnyRole(['hr_admin_specialist', 'hr_manager']),
            'operation'         => $this->hasAnyRole(['operation_specialist', 'operation_manager']),
            'payroll'           => $this->hasAnyRole(['payroll_specialist', 'payroll_manager']),
            'finance'           => $this->hasAnyRole(['finance_specialist'. 'finance_manager']),
            'purchasing'        => $this->hasAnyRole(['purchasing_specialist']),
        };
    }

    public function department() : BelongsTo
    {
        return $this->belongsTo(Departments::class);
    }

    // App\Models\User.php — add inside the class

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
     * Adjust the role check to match your auth setup.
     */
    public function isManager(): bool
    {
        // Example using Spatie roles:
        return $this->hasRole(['payroll_manager', 'super_admin']);
        
        // Example using a column:
        // return in_array($this->role, ['manager', 'admin', 'super_admin']);
    }


}
