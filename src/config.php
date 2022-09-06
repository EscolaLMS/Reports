<?php

return [
    /**
     * By modyfing this list, you can add or remove available Metrics for which Reports can be calculated
     */
    'metrics' => [
        \EscolaLms\Reports\Metrics\CoursesMoneySpentMetric::class,
        \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
        \EscolaLms\Reports\Metrics\CoursesSecondsSpentMetric::class,
        \EscolaLms\Reports\Metrics\TutorsPopularityMetric::class,
        \EscolaLms\Reports\Metrics\CoursesBestRatedMetric::class,
        \EscolaLms\Reports\Metrics\CoursesTopSellingMetric::class,
    ],
    /**
     * For each Metric class you can specify settings:
     * @param bool history - should this metric be automatically measured (default: true)
     * @param int limit    - how many data points should be saved in database and/or retrieved in api call (default: 10)
     * @param string cron  - cron expression determining how often this metric will be measured and saved in DB (default: midnight every day)
     */
    'metric_configuration' => [
        \EscolaLms\Reports\Metrics\CoursesMoneySpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesSecondsSpentMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\TutorsPopularityMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesTopSellingMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ],
        \EscolaLms\Reports\Metrics\CoursesBestRatedMetric::class => [
            'limit' => 10,
            'history' => false,
            'cron' => '0 0 * * *',
        ]
    ],
    /**
     * By modyfing this associative array, you can add or remove available Stats which can be returned for single objects of given class
     */
    'stats' => [
        \EscolaLms\Courses\Models\Course::class => [
            \EscolaLms\Reports\Stats\Course\AverageTime::class,
            \EscolaLms\Reports\Stats\Course\AverageTimePerTopic::class,
            \EscolaLms\Reports\Stats\Course\MoneyEarned::class,
            \EscolaLms\Reports\Stats\Course\PeopleBought::class,
            \EscolaLms\Reports\Stats\Course\PeopleFinished::class,
            \EscolaLms\Reports\Stats\Course\PeopleStarted::class,
        ],
        \EscolaLms\Courses\Models\Topic::class => [
            \EscolaLms\Reports\Stats\Topic\AverageTime::class,
        ],
    ]
];
