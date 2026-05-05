<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OfficeSupplyCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfficeSupplyCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OfficeSupplyCategory');
    }

    public function view(AuthUser $authUser, OfficeSupplyCategory $officeSupplyCategory): bool
    {
        return $authUser->can('View:OfficeSupplyCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OfficeSupplyCategory');
    }

    public function update(AuthUser $authUser, OfficeSupplyCategory $officeSupplyCategory): bool
    {
        return $authUser->can('Update:OfficeSupplyCategory');
    }

    public function delete(AuthUser $authUser, OfficeSupplyCategory $officeSupplyCategory): bool
    {
        return $authUser->can('Delete:OfficeSupplyCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OfficeSupplyCategory');
    }

    public function restore(AuthUser $authUser, OfficeSupplyCategory $officeSupplyCategory): bool
    {
        return $authUser->can('Restore:OfficeSupplyCategory');
    }

    public function forceDelete(AuthUser $authUser, OfficeSupplyCategory $officeSupplyCategory): bool
    {
        return $authUser->can('ForceDelete:OfficeSupplyCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OfficeSupplyCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OfficeSupplyCategory');
    }

    public function replicate(AuthUser $authUser, OfficeSupplyCategory $officeSupplyCategory): bool
    {
        return $authUser->can('Replicate:OfficeSupplyCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OfficeSupplyCategory');
    }

}