<?php

namespace EscolaLms\Reports\Services\Contracts;

use Illuminate\Database\Eloquent\Model;

interface StatsServiceContract
{
    public function calculate($model, array $selected_stats = []): array;
    public function getAvailableStats($model = null): array;
}
