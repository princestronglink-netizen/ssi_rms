<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SmeBilling;
use Illuminate\Auth\Access\HandlesAuthorization;

class SmeBillingPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmeBilling');
    }

    public function view(AuthUser $authUser, SmeBilling $smeBilling): bool
    {
        return $authUser->can('View:SmeBilling');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmeBilling');
    }

    public function update(AuthUser $authUser, SmeBilling $smeBilling): bool
    {
        return $authUser->can('Update:SmeBilling');
    }

    public function delete(AuthUser $authUser, SmeBilling $smeBilling): bool
    {
        return $authUser->can('Delete:SmeBilling');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmeBilling');
    }

    public function restore(AuthUser $authUser, SmeBilling $smeBilling): bool
    {
        return $authUser->can('Restore:SmeBilling');
    }

    public function forceDelete(AuthUser $authUser, SmeBilling $smeBilling): bool
    {
        return $authUser->can('ForceDelete:SmeBilling');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmeBilling');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmeBilling');
    }

    public function replicate(AuthUser $authUser, SmeBilling $smeBilling): bool
    {
        return $authUser->can('Replicate:SmeBilling');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmeBilling');
    }

}