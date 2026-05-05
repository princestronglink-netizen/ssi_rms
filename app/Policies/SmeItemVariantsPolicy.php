<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SmeItemVariants;
use Illuminate\Auth\Access\HandlesAuthorization;

class SmeItemVariantsPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmeItemVariants');
    }

    public function view(AuthUser $authUser, SmeItemVariants $smeItemVariants): bool
    {
        return $authUser->can('View:SmeItemVariants');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmeItemVariants');
    }

    public function update(AuthUser $authUser, SmeItemVariants $smeItemVariants): bool
    {
        return $authUser->can('Update:SmeItemVariants');
    }

    public function delete(AuthUser $authUser, SmeItemVariants $smeItemVariants): bool
    {
        return $authUser->can('Delete:SmeItemVariants');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmeItemVariants');
    }

    public function restore(AuthUser $authUser, SmeItemVariants $smeItemVariants): bool
    {
        return $authUser->can('Restore:SmeItemVariants');
    }

    public function forceDelete(AuthUser $authUser, SmeItemVariants $smeItemVariants): bool
    {
        return $authUser->can('ForceDelete:SmeItemVariants');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmeItemVariants');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmeItemVariants');
    }

    public function replicate(AuthUser $authUser, SmeItemVariants $smeItemVariants): bool
    {
        return $authUser->can('Replicate:SmeItemVariants');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmeItemVariants');
    }

}