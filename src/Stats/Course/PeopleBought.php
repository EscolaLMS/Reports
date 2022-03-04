<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use Illuminate\Database\Eloquent\Builder;

class PeopleBought extends AbstractCourseStat
{
    public function calculate(): int
    {
        return $this->course->users()->count();
    }

    // this will not include people that got Course assigned manually using Course Access Admin API endpoints
    public function calculateReallyBought(): int
    {
        return OrderItem::whereHas(
            'order',
            fn (Builder $query) => $query->where('status', OrderStatus::PAID)
        )
            ->where('buyable_type', Product::class)
            ->whereHas(
                'buyable',
                fn (Builder $query) => $query
                    ->whereHas(
                        'productables',
                        fn (Builder $subquery) => $subquery->where('productable_type', $this->course->getMorphClass())->where('productable_id', $this->course->getKey())
                    )
            )->count();
    }
}
