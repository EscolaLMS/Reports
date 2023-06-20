<?php

namespace EscolaLms\Reports\Exports\Stats\Course\Sheets;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;

abstract class FinishedTopicsSheet implements FromCollection, WithHeadings, ShouldAutoSize, WithTitle, WithStrictNullComparison
{
    protected Collection $stats;

    public function __construct(Collection $stats)
    {
        $this->stats = $stats;
    }

    public function headings(): array
    {
        $headings = [
            __('Email'),
        ];

        if ($this->stats->first()) {
            $topics = collect($this->stats->first())
                ->get('topics')
                ->map(fn (array $item) => class_basename(Arr::get($item, 'topicable_type')) . ' ' . Arr::get($item, 'title'))
                ->toArray();

            $headings = array_merge($headings, $topics);
        }

        return $headings;
    }
}
