<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Cart\Enums\OrderStatus;
use EscolaLms\Cart\Enums\ProductType;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class MoneyEarned extends AbstractCourseStat
{
    public function calculate(): int
    {
        return $this->calculateSingleProductValue() + $this->calculateBundleProductValue();
    }

    private function calculateSingleProductValue(): int
    {
        return OrderItem::whereHas(
            'order',
            fn (Builder $query) => $query->where('status', OrderStatus::PAID)
        )
            ->where('buyable_type', Product::class)
            ->whereHas(
                'buyable',
                fn (Builder $query) => $query
                    ->where('type', ProductType::SINGLE)
                    ->whereHas(
                        'productables',
                        fn (Builder $subquery) => $subquery->where('productable_type', $this->course->getMorphClass())->where('productable_id', $this->course->getKey())
                    )
            )->sum(DB::raw('price * quantity')) ?? 0;
    }

    private function calculateBundleProductValue(): int
    {
        /** @var Collection $orderItems */
        $orderItems = OrderItem::whereHas(
            'order',
            fn (Builder $query) => $query->where('status', OrderStatus::PAID)
        )
            ->where('buyable_type', Product::class)
            ->whereHas(
                'buyable',
                fn (Builder $query) => $query
                    ->where('type', ProductType::BUNDLE)
                    ->whereHas(
                        'productables',
                        fn (Builder $subquery) => $subquery->where('productable_type', $this->course->getMorphClass())->where('productable_id', $this->course->getKey())
                    )
                    ->withCount('productables')
            )->with('buyable', 'buyable.productables')->get();

        // TODO: try to write this as (Raw) SQL Query
        return $orderItems->reduce(fn (int $sum, OrderItem $orderItem) => $sum + ($orderItem->buyable->productables_count > 0 ? ($orderItem->price * $orderItem->quantity / $orderItem->buyable->productables_count) : 0), 0);
    }
}
