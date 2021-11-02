<?php

namespace EscolaLms\Reports\Metrics;

use ArrayObject;
use EscolaLms\Auth\Models\User;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Models\Report;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TutorsPopularityMetric extends AbstractMetric
{
    public function calculate(?int $limit = null): Collection
    {
        $courseTable = (new Course())->getTable();
        $usersTable = (new User())->getTable();
        $courseUserPivot = (new Course())->users()->getTable();

        return DB::table($usersTable)
            ->selectRaw($usersTable . '.id, ' . $usersTable . '.email as label, COUNT(' . $courseUserPivot . '.user_id) as value')
            ->rightJoin($courseTable,  $courseTable . '.author_id', '=', $usersTable . '.id')
            ->join($courseUserPivot, $courseTable . '.id', '=', $courseUserPivot . '.course_id')
            ->groupBy($usersTable . '.id', $usersTable . '.email')
            ->orderBy('value', 'DESC')
            ->take($limit ?? $this->defaultLimit())
            ->get(['id', 'label', 'value'])
            ->map(function ($item) {
                if (is_object($item)) {
                    $item = new ArrayObject($item);
                    $item['value'] = is_null($item['value']) ? 0 : $item['value'];
                }
                return $item;
            })
            ->sortByDesc('value');
    }

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
                'measurable_type' => User::class,
            ]);
        }

        return $report;
    }
}
