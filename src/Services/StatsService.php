<?php

namespace EscolaLms\Reports\Services;

use EscolaLms\Reports\Services\Contracts\StatsServiceContract;
use EscolaLms\Reports\Stats\StatsContract;
use Illuminate\Database\Eloquent\Model;

class StatsService implements StatsServiceContract
{
    private array $available_stats;

    public function __construct()
    {
        $this->available_stats = config('reports.stats');
    }

    public function calculate(Model $model, array $selected_stats = []): array
    {
        $stats_to_calculate = $this->getAvailableStats($model);
        if (!empty($selected_stats)) {
            $stats_to_calculate = array_filter($stats_to_calculate, fn ($stat) => in_array($stat, $selected_stats));
        }

        $results = [];
        foreach ($stats_to_calculate as $stat) {
            if (class_exists($stat)) {
                $stat_instance = new $stat($model);
                assert($stat_instance instanceof StatsContract);
                $results[$stat] = $stat_instance->calculate();
            }
        }
        return $results;
    }

    public function getAvailableStats(?Model $model = null): array
    {
        if (is_null($model)) {
            return $this->available_stats;
        }
        foreach ($this->available_stats as $class => $stats) {
            if (is_a($model, $class)) {
                return $stats;
            }
        }
        return [];
    }
}
