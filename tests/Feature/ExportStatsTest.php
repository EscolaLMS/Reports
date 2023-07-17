<?php

namespace EscolaLms\Reports\Tests\Feature;

use Carbon\Carbon;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\HeadlessH5P\Models\H5PContent;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsAttemptsSheet;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsSecondsSheet;
use EscolaLms\Reports\Exports\Stats\Course\Sheets\FinishedTopicsStatusesSheet;
use EscolaLms\Reports\Stats\Course\FinishedTopics;
use EscolaLms\Reports\Tests\Models\Course;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\TopicTypes\Models\TopicContent\H5P;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ExportStatsTest extends TestCase
{
    use DatabaseTransactions, CreatesUsers;

    /**
     * @dataProvider finishedTopicsResult
     */
    public function testFinishedTopicsSheets(string $sheet, string $title, array $result): void
    {
        $user1 = User::factory()
            ->state(['email' => 'abc@example.com'])
            ->create();

        $user2 = User::factory()
            ->state(['email' => 'def@example.com'])
            ->create();

        $user3 = User::factory()
            ->state(['email' => 'ghi@example.com'])
            ->create();

        $course = Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();

        $topic1 = Topic::factory()->state([
            'lesson_id' => $lesson->getKey(),
            'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\PDF',
        ])
            ->create();

        $topic2 = Topic::factory()->state([
            'lesson_id' => $lesson->getKey(),
            'topicable_type' => 'EscolaLms\TopicTypes\Models\TopicContent\Audio',
        ])
            ->create();

        $topic3 = Topic::factory()->state([
            'lesson_id' => $lesson->getKey(),
        ])
            ->create();
        $topicable_h5p = H5P::factory()->create();
        $topic3->topicable()->associate($topicable_h5p)->save();

        $course->users()->attach([$user1->getKey(), $user2->getKey(), $user3->getKey()]);

        CourseProgress::create(['topic_id' => $topic1->getKey(), 'user_id' => $user1->getKey(), 'seconds' => 100, 'started_at' => Carbon::now(), 'finished_at' => Carbon::now(), 'attempt' => 0]);
        CourseProgress::create(['topic_id' => $topic2->getKey(), 'user_id' => $user1->getKey(), 'seconds' => 0, 'started_at' => Carbon::now(), 'finished_at' => null, 'attempt' => 2]);
        CourseProgress::create(['topic_id' => $topic1->getKey(), 'user_id' => $user2->getKey(), 'seconds' => 150, 'started_at' => null, 'finished_at' => null, 'attempt' => 1]);

        $stats = FinishedTopics::make($course)->calculate();
        $export = new $sheet(collect($stats));

        $this->assertEquals(__($title), $export->title());

        $this->assertEquals([
            __('Email'),
            'PDF ' . $topic1->title,
            'Audio ' . $topic2->title,
            H5PContent::find($topicable_h5p->value)->library->uberName . ' ' . $topic3->title
        ], $export->headings());

        $this->assertEquals(collect($result), $export->collection());
    }

    public function finishedTopicsResult(): array
    {
        return [
            [
                'sheet' => FinishedTopicsAttemptsSheet::class,
                'title' => 'Attempts',
                [
                    collect(['abc@example.com', 1, 3, 1]),
                    collect(['def@example.com', 2, 1, 1]),
                    collect(['ghi@example.com', 1, 1, 1]),
                ],
            ],
            [
                'sheet' => FinishedTopicsSecondsSheet::class,
                'title' => 'Seconds',
                [
                    collect(['abc@example.com', 100, 0, 0]),
                    collect(['def@example.com', 150, 0, 0]),
                    collect(['ghi@example.com', 0, 0, 0]),
                ],
            ],
            [
                'sheet' => FinishedTopicsStatusesSheet::class,
                'title' => 'Statuses',
                [
                    collect(['abc@example.com', 2, 1, 0]),
                    collect(['def@example.com', 0, 0, 0]),
                    collect(['ghi@example.com', 0, 0, 0]),
                ],
            ]
        ];
    }
}
