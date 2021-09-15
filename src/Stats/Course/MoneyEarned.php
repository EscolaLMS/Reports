<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Models\Order;

class MoneyEarned extends AbstractCourseStat
{
    public function calculate(): int
    {
        return Order::query()->where('status', OrderStatus::PAID)->whereHasCourse($this->course)->count() * $this->course->base_price;
    }
}
