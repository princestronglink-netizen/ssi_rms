<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReturnOfficeSupplyItems;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReturnOfficeSupplyItemsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReturnOfficeSupplyItems');
    }

    public function view(AuthUser $authUser, ReturnOfficeSupplyItems $returnOfficeSupplyItems): bool
    {
        return $authUser->can('View:ReturnOfficeSupplyItems');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReturnOfficeSupplyItems');
    }

    public function update(AuthUser $authUser, ReturnOfficeSupplyItems $returnOfficeSupplyItems): bool
    {
        return $authUser->can('Update:ReturnOfficeSupplyItems');
    }

    public function delete(AuthUser $authUser, ReturnOfficeSupplyItems $returnOfficeSupplyItems): bool
    {
        return $authUser->can('Delete:ReturnOfficeSupplyItems');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReturnOfficeSupplyItems');
    }

    public function restore(AuthUser $authUser, ReturnOfficeSupplyItems $returnOfficeSupplyItems): bool
    {
        return $authUser->can('Restore:ReturnOfficeSupplyItems');
    }

    public function forceDelete(AuthUser $authUser, ReturnOfficeSupplyItems $returnOfficeSupplyItems): bool
    {
        return $authUser->can('ForceDelete:ReturnOfficeSupplyItems');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReturnOfficeSupplyItems');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReturnOfficeSupplyItems');
    }

    public function replicate(AuthUser $authUser, ReturnOfficeSupplyItems $returnOfficeSupplyItems): bool
    {
        return $authUser->can('Replicate:ReturnOfficeSupplyItems');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReturnOfficeSupplyItems');
    }

}