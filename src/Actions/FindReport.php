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

        if (!is_null($date)) {
            /** @var Report $report */
            $report = Report::where('metric', $metric)->whereDate('created_at', '=', $date)->first();
            if ($report) {
                return $report;
            }
            if (!$date->isSameDay(Carbon::now())) {
                return null;
            }
        }

        /* Date is null or date is today and no report was found */
        return $metric::make()->calculateAndStore($limit);
    }
}
