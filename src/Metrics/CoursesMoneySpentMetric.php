<?php

namespace EscolaLms\Reports\Metrics;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Models\Course as CartCourse;
use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Metrics\Contracts\MetricContract;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CoursesMoneySpentMetric extends AbstractCourseMetric
{
    public static function make(): MetricContract
    {
        return new self(config());
    }

    public function calculate(?int $limit = null): Collection
    {
        $courseTable = (new Course())->getTable();
        $orderItemTable = (new OrderItem())->getTable();
        $orderTable = (new Order())->getTable();

        return Course::selectRaw($courseTable . '.id, ' . $courseTable . '.title as label, SUM(' . $orderItemTable . '.quantity * ' . $courseTable . '.base_price) as value')
            ->leftJoin($orderItemTable, fn (JoinClause $join) => $join->where($orderItemTable . '.buyable_id', '=', DB::raw($courseTable . '.id'))->whereIn($orderItemTable . '.buyable_type', [Course::class, CartCourse::class]))
            ->rightJoin($orderTable, fn (JoinClause $join) => $join->where($orderTable . '.id', '=', DB::raw($orderItemTable . '.order_id'))->where($orderTable . '.status', '=', OrderStatus::PAID))
            ->groupBy($courseTable . '.id')
            ->orderBy('value', 'DESC')
            ->take($limit ?? $this->defaultLimit())
            ->get(['id', 'label', 'value']);
    }
}
