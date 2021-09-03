<?php

namespace EscolaLms\Reports\Policies;

use EscolaLms\Reports\Enums\ReportsPermissionsEnum;
use EscolaLms\Reports\Models\Report;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User;

class ReportPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can(ReportsPermissionsEnum::DISPLAY_REPORTS);
    }

    public function view(User $user, Report $report): bool
    {
        return $user->can(ReportsPermissionsEnum::DISPLAY_REPORTS);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Report $report): bool
    {
        return false;
    }

    public function delete(User $user, Report $report): bool
    {
        return false;
    }

    public function restore(User $user, Report $report): bool
    {
        return false;
    }

    public function forceDelete(User $user, Report $report): bool
    {
        return false;
    }
}
