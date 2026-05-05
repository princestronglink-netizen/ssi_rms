<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SmeRestocks;
use Illuminate\Auth\Access\HandlesAuthorization;

class SmeRestocksPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmeRestocks');
    }

    public function view(AuthUser $authUser, SmeRestocks $smeRestocks): bool
    {
        return $authUser->can('View:SmeRestocks');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmeRestocks');
    }

    public function update(AuthUser $authUser, SmeRestocks $smeRestocks): bool
    {
        return $authUser->can('Update:SmeRestocks');
    }

    public function delete(AuthUser $authUser, SmeRestocks $smeRestocks): bool
    {
        return $authUser->can('Delete:SmeRestocks');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmeRestocks');
    }

    public function restore(AuthUser $authUser, SmeRestocks $smeRestocks): bool
    {
        return $authUser->can('Restore:SmeRestocks');
    }

    public function forceDelete(AuthUser $authUser, SmeRestocks $smeRestocks): bool
    {
        return $authUser->can('ForceDelete:SmeRestocks');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmeRestocks');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmeRestocks');
    }

    public function replicate(AuthUser $authUser, SmeRestocks $smeRestocks): bool
    {
        return $authUser->can('Replicate:SmeRestocks');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmeRestocks');
    }

}