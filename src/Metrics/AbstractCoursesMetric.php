<?php

namespace EscolaLms\Reports\Metrics;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Models\Report;

abstract class AbstractCoursesMetric extends AbstractMetric
{

    public function calculateAndStore(?int $limit = null): Report
    {
        /** @var Report $report */
        $report = Report::create([
            'metric' => get_class($this)
        ]);

        $results = $this->calculate($limit);

        foreach ($results as $result) {
            $report->measurements()->create([
                'label' => $result['label'],
                'value' => $result['value'] ?? 0,
                'measurable_id' => $result['id'],
                'measurable_type' => Course::class,
            ]);
        }

        return $report;
    }
}
