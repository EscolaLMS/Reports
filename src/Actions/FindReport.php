<?php

namespace EscolaLms\Reports\Actions;

use EscolaLms\Reports\Metrics\Contracts\MetricContract;
use EscolaLms\Reports\Models\Report;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class FindReport
{
    public function handle(string $metric, ?Carbon $date = null, ?int $limit = null): ?Report
    {
        if (!class_exists($metric) || !is_a($metric, MetricContract::class, true)) {
            throw new InvalidArgumentException(__('Metric must be insance of :contract . :metric given.', [
                'contract' => MetricContract::class,
                'metric' => $metric
            ]));
        }

        /** @var Report $report */
        $report = Report::where('metric', $metric)->whereDate('created_at', '=', $date ?? Carbon::now())->orderBy('created_at', 'DESC')->first();

        if ($this->isHistorical($date) || $this->hasEnoughDataPoints($report, $limit)) {
            return $report;
        }

        return $metric::make()->calculateAndStore($limit);
    }

    private function isHistorical(?Carbon $date): bool
    {
        return !is_null($date) && !$date->isSameDay(Carbon::now());
    }

    private function hasEnoughDataPoints(?Report $report, ?int $limit)
    {
        return !is_null($report) && $report->measurements()->count() >= (int) $limit;
    }
}
