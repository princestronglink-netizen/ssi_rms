<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\SmePurchaseOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

class SmePurchaseOrderPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:SmePurchaseOrder');
    }

    public function view(AuthUser $authUser, SmePurchaseOrder $smePurchaseOrder): bool
    {
        return $authUser->can('View:SmePurchaseOrder');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:SmePurchaseOrder');
    }

    public function update(AuthUser $authUser, SmePurchaseOrder $smePurchaseOrder): bool
    {
        return $authUser->can('Update:SmePurchaseOrder');
    }

    public function delete(AuthUser $authUser, SmePurchaseOrder $smePurchaseOrder): bool
    {
        return $authUser->can('Delete:SmePurchaseOrder');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:SmePurchaseOrder');
    }

    public function restore(AuthUser $authUser, SmePurchaseOrder $smePurchaseOrder): bool
    {
        return $authUser->can('Restore:SmePurchaseOrder');
    }

    public function forceDelete(AuthUser $authUser, SmePurchaseOrder $smePurchaseOrder): bool
    {
        return $authUser->can('ForceDelete:SmePurchaseOrder');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:SmePurchaseOrder');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:SmePurchaseOrder');
    }

    public function replicate(AuthUser $authUser, SmePurchaseOrder $smePurchaseOrder): bool
    {
        return $authUser->can('Replicate:SmePurchaseOrder');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:SmePurchaseOrder');
    }

}