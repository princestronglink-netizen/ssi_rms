<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OfficeSupplyItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfficeSupplyItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OfficeSupplyItem');
    }

    public function view(AuthUser $authUser, OfficeSupplyItem $officeSupplyItem): bool
    {
        return $authUser->can('View:OfficeSupplyItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OfficeSupplyItem');
    }

    public function update(AuthUser $authUser, OfficeSupplyItem $officeSupplyItem): bool
    {
        return $authUser->can('Update:OfficeSupplyItem');
    }

    public function delete(AuthUser $authUser, OfficeSupplyItem $officeSupplyItem): bool
    {
        return $authUser->can('Delete:OfficeSupplyItem');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OfficeSupplyItem');
    }

    public function restore(AuthUser $authUser, OfficeSupplyItem $officeSupplyItem): bool
    {
        return $authUser->can('Restore:OfficeSupplyItem');
    }

    public function forceDelete(AuthUser $authUser, OfficeSupplyItem $officeSupplyItem): bool
    {
        return $authUser->can('ForceDelete:OfficeSupplyItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OfficeSupplyItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OfficeSupplyItem');
    }

    public function replicate(AuthUser $authUser, OfficeSupplyItem $officeSupplyItem): bool
    {
        return $authUser->can('Replicate:OfficeSupplyItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OfficeSupplyItem');
    }

}