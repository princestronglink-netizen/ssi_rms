<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OfficeSupplyRestock;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfficeSupplyRestockPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OfficeSupplyRestock');
    }

    public function view(AuthUser $authUser, OfficeSupplyRestock $officeSupplyRestock): bool
    {
        return $authUser->can('View:OfficeSupplyRestock');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OfficeSupplyRestock');
    }

    public function update(AuthUser $authUser, OfficeSupplyRestock $officeSupplyRestock): bool
    {
        return $authUser->can('Update:OfficeSupplyRestock');
    }

    public function delete(AuthUser $authUser, OfficeSupplyRestock $officeSupplyRestock): bool
    {
        return $authUser->can('Delete:OfficeSupplyRestock');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OfficeSupplyRestock');
    }

    public function restore(AuthUser $authUser, OfficeSupplyRestock $officeSupplyRestock): bool
    {
        return $authUser->can('Restore:OfficeSupplyRestock');
    }

    public function forceDelete(AuthUser $authUser, OfficeSupplyRestock $officeSupplyRestock): bool
    {
        return $authUser->can('ForceDelete:OfficeSupplyRestock');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OfficeSupplyRestock');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OfficeSupplyRestock');
    }

    public function replicate(AuthUser $authUser, OfficeSupplyRestock $officeSupplyRestock): bool
    {
        return $authUser->can('Replicate:OfficeSupplyRestock');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OfficeSupplyRestock');
    }

}