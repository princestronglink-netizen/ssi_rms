<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SmeItems;
use Illuminate\Auth\Access\HandlesAuthorization;

class SmeItemsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmeItems');
    }

    public function view(AuthUser $authUser, SmeItems $smeItems): bool
    {
        return $authUser->can('View:SmeItems');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmeItems');
    }

    public function update(AuthUser $authUser, SmeItems $smeItems): bool
    {
        return $authUser->can('Update:SmeItems');
    }

    public function delete(AuthUser $authUser, SmeItems $smeItems): bool
    {
        return $authUser->can('Delete:SmeItems');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmeItems');
    }

    public function restore(AuthUser $authUser, SmeItems $smeItems): bool
    {
        return $authUser->can('Restore:SmeItems');
    }

    public function forceDelete(AuthUser $authUser, SmeItems $smeItems): bool
    {
        return $authUser->can('ForceDelete:SmeItems');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmeItems');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmeItems');
    }

    public function replicate(AuthUser $authUser, SmeItems $smeItems): bool
    {
        return $authUser->can('Replicate:SmeItems');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmeItems');
    }

}