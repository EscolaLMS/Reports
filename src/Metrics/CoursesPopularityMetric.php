<?php

namespace EscolaLms\Reports\Metrics;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Metrics\Contracts\MetricContract;
use Illuminate\Support\Collection;

class CoursesPopularityMetric extends AbstractCourseMetric
{
    public static function make(): MetricContract
    {
        return new self(config());
    }

    public function calculate(?int $limit = null): Collection
    {
        return Course::withCount(['users'])
            ->orderBy('users_count', 'DESC')
            ->take($limit ?? $this->defaultLimit())
            ->get(['id', 'title', 'users_count'])
            ->map(fn ($item) => [
                'id' => $item->id,
                'label' => $item->title,
                'value' => $item->users_count
            ]);
    }
}
