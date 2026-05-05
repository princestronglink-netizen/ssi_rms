<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReturnUniformItems;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReturnUniformItemsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReturnUniformItems');
    }

    public function view(AuthUser $authUser, ReturnUniformItems $returnUniformItems): bool
    {
        return $authUser->can('View:ReturnUniformItems');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReturnUniformItems');
    }

    public function update(AuthUser $authUser, ReturnUniformItems $returnUniformItems): bool
    {
        return $authUser->can('Update:ReturnUniformItems');
    }

    public function delete(AuthUser $authUser, ReturnUniformItems $returnUniformItems): bool
    {
        return $authUser->can('Delete:ReturnUniformItems');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReturnUniformItems');
    }

    public function restore(AuthUser $authUser, ReturnUniformItems $returnUniformItems): bool
    {
        return $authUser->can('Restore:ReturnUniformItems');
    }

    public function forceDelete(AuthUser $authUser, ReturnUniformItems $returnUniformItems): bool
    {
        return $authUser->can('ForceDelete:ReturnUniformItems');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReturnUniformItems');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReturnUniformItems');
    }

    public function replicate(AuthUser $authUser, ReturnUniformItems $returnUniformItems): bool
    {
        return $authUser->can('Replicate:ReturnUniformItems');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReturnUniformItems');
    }

}