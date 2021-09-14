<?php

namespace EscolaLms\Reports\Metrics;

use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Metrics\Contracts\MetricContract;
use Illuminate\Support\Collection;

class CoursesSecondsSpentMetric extends AbstractCourseMetric
{
    public static function make(): MetricContract
    {
        return new self(config());
    }

    public function calculate(?int $limit = null): Collection
    {
        $courseTable = (new Course())->getTable();
        $lessonTable = (new Lesson())->getTable();
        $topicTable = (new Topic())->getTable();
        $courseProgressTable = (new CourseProgress())->getTable();

        return Course::selectRaw($courseTable . '.id, ' . $courseTable . '.title as label, SUM(' . $courseProgressTable . '.seconds) as value')
            ->join($lessonTable, $courseTable . '.id', '=', $lessonTable . '.course_id')
            ->join($topicTable, $lessonTable . '.id', '=', $topicTable . '.lesson_id')
            ->join($courseProgressTable, $topicTable . '.id', '=', $courseProgressTable . '.topic_id')
            ->groupBy($courseTable . '.id')
            ->orderBy('value', 'DESC')
            ->take($limit ?? $this->defaultLimit())
            ->get(['id', 'label', 'value']);
    }
}
