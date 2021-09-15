<?php

namespace EscolaLms\Reports\Services\Contracts;

use Illuminate\Database\Eloquent\Model;

interface StatsServiceContract
{
    public function calculate(Model $model, array $selected_stats = []): array;
    public function getAvailableStats(?Model $model = null): array;
}
