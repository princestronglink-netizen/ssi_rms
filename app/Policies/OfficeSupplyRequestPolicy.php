<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\OfficeSupplyRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class OfficeSupplyRequestPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:OfficeSupplyRequest');
    }

    public function view(AuthUser $authUser, OfficeSupplyRequest $officeSupplyRequest): bool
    {
        return $authUser->can('View:OfficeSupplyRequest');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:OfficeSupplyRequest');
    }

    public function update(AuthUser $authUser, OfficeSupplyRequest $officeSupplyRequest): bool
    {
        return $authUser->can('Update:OfficeSupplyRequest');
    }

    public function delete(AuthUser $authUser, OfficeSupplyRequest $officeSupplyRequest): bool
    {
        return $authUser->can('Delete:OfficeSupplyRequest');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:OfficeSupplyRequest');
    }

    public function restore(AuthUser $authUser, OfficeSupplyRequest $officeSupplyRequest): bool
    {
        return $authUser->can('Restore:OfficeSupplyRequest');
    }

    public function forceDelete(AuthUser $authUser, OfficeSupplyRequest $officeSupplyRequest): bool
    {
        return $authUser->can('ForceDelete:OfficeSupplyRequest');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:OfficeSupplyRequest');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:OfficeSupplyRequest');
    }

    public function replicate(AuthUser $authUser, OfficeSupplyRequest $officeSupplyRequest): bool
    {
        return $authUser->can('Replicate:OfficeSupplyRequest');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:OfficeSupplyRequest');
    }

}