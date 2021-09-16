<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Models\Order;

class PeopleBought extends AbstractCourseStat
{
    public function calculate(): int
    {
        return $this->course->users()->count();
    }

    // this will not include people that got Course assigned manually using Course Access Admin API endpoints
    public function calculateReallyBought(): int
    {
        return Order::query()->where('status', OrderStatus::PAID)->whereHasCourse($this->course)->count();
    }
}
