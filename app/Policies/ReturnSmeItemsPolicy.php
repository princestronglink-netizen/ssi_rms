<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ReturnSmeItems;
use Illuminate\Auth\Access\HandlesAuthorization;

class ReturnSmeItemsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ReturnSmeItems');
    }

    public function view(AuthUser $authUser, ReturnSmeItems $returnSmeItems): bool
    {
        return $authUser->can('View:ReturnSmeItems');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ReturnSmeItems');
    }

    public function update(AuthUser $authUser, ReturnSmeItems $returnSmeItems): bool
    {
        return $authUser->can('Update:ReturnSmeItems');
    }

    public function delete(AuthUser $authUser, ReturnSmeItems $returnSmeItems): bool
    {
        return $authUser->can('Delete:ReturnSmeItems');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:ReturnSmeItems');
    }

    public function restore(AuthUser $authUser, ReturnSmeItems $returnSmeItems): bool
    {
        return $authUser->can('Restore:ReturnSmeItems');
    }

    public function forceDelete(AuthUser $authUser, ReturnSmeItems $returnSmeItems): bool
    {
        return $authUser->can('ForceDelete:ReturnSmeItems');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ReturnSmeItems');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ReturnSmeItems');
    }

    public function replicate(AuthUser $authUser, ReturnSmeItems $returnSmeItems): bool
    {
        return $authUser->can('Replicate:ReturnSmeItems');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ReturnSmeItems');
    }

}