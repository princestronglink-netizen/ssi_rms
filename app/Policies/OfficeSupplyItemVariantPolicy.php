<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OfficeSupplyItemVariant;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfficeSupplyItemVariantPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OfficeSupplyItemVariant');
    }

    public function view(AuthUser $authUser, OfficeSupplyItemVariant $officeSupplyItemVariant): bool
    {
        return $authUser->can('View:OfficeSupplyItemVariant');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OfficeSupplyItemVariant');
    }

    public function update(AuthUser $authUser, OfficeSupplyItemVariant $officeSupplyItemVariant): bool
    {
        return $authUser->can('Update:OfficeSupplyItemVariant');
    }

    public function delete(AuthUser $authUser, OfficeSupplyItemVariant $officeSupplyItemVariant): bool
    {
        return $authUser->can('Delete:OfficeSupplyItemVariant');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OfficeSupplyItemVariant');
    }

    public function restore(AuthUser $authUser, OfficeSupplyItemVariant $officeSupplyItemVariant): bool
    {
        return $authUser->can('Restore:OfficeSupplyItemVariant');
    }

    public function forceDelete(AuthUser $authUser, OfficeSupplyItemVariant $officeSupplyItemVariant): bool
    {
        return $authUser->can('ForceDelete:OfficeSupplyItemVariant');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OfficeSupplyItemVariant');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OfficeSupplyItemVariant');
    }

    public function replicate(AuthUser $authUser, OfficeSupplyItemVariant $officeSupplyItemVariant): bool
    {
        return $authUser->can('Replicate:OfficeSupplyItemVariant');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OfficeSupplyItemVariant');
    }

}