<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SmeCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class SmeCategoryPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmeCategory');
    }

    public function view(AuthUser $authUser, SmeCategory $smeCategory): bool
    {
        return $authUser->can('View:SmeCategory');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmeCategory');
    }

    public function update(AuthUser $authUser, SmeCategory $smeCategory): bool
    {
        return $authUser->can('Update:SmeCategory');
    }

    public function delete(AuthUser $authUser, SmeCategory $smeCategory): bool
    {
        return $authUser->can('Delete:SmeCategory');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmeCategory');
    }

    public function restore(AuthUser $authUser, SmeCategory $smeCategory): bool
    {
        return $authUser->can('Restore:SmeCategory');
    }

    public function forceDelete(AuthUser $authUser, SmeCategory $smeCategory): bool
    {
        return $authUser->can('ForceDelete:SmeCategory');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmeCategory');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmeCategory');
    }

    public function replicate(AuthUser $authUser, SmeCategory $smeCategory): bool
    {
        return $authUser->can('Replicate:SmeCategory');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmeCategory');
    }

}