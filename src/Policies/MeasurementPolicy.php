<?php

namespace EscolaLms\Reports\Policies;

use EscolaLms\Reports\Enums\ReportsPermissionsEnum;
use EscolaLms\Reports\Models\Measurement;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class MeasurementPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(ReportsPermissionsEnum::DISPLAY_REPORTS);
    }

    public function view(User $user, Measurement $measurement): bool
    {
        return $user->can(ReportsPermissionsEnum::DISPLAY_REPORTS);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Measurement $measurement): bool
    {
        return false;
    }

    public function delete(User $user, Measurement $measurement): bool
    {
        return false;
    }

    public function restore(User $user, Measurement $measurement): bool
    {
        return false;
    }

    public function forceDelete(User $user, Measurement $measurement): bool
    {
        return false;
    }
}
