<?php

namespace EscolaLms\Reports\Tests\Feature;

use EscolaLms\Auth\Models\User as AuthUser;
use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\ProgressStatus;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Models\TopicContent\RichText;
use EscolaLms\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use EscolaLms\Courses\Repositories\CourseProgressRepository;
use EscolaLms\Courses\Services\Contracts\ProgressServiceContract;
use EscolaLms\Courses\Services\ProgressService;
use EscolaLms\Payments\Models\Payment;
use EscolaLms\Reports\Metrics\CoursesMoneySpentMetric;
use EscolaLms\Reports\Metrics\CoursesPopularityMetric;
use EscolaLms\Reports\Metrics\CoursesSecondsSpentMetric;
use EscolaLms\Reports\Metrics\TutorsPopularityMetric;
use EscolaLms\Reports\Tests\Models\TestUser;
use EscolaLms\Reports\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class MetricsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    private function createCourseWithLessonAndTopic(): Course
    {
        return Course::factory()
            ->has(
                Lesson::factory()
                    ->has(
                        Topic::factory()
                            ->afterCreating(
                                function (Topic $topic) {
                                    $topic->topicable()->associate(RichText::factory()->create())->save();
                                }
                            )
                    )
            )->create([
                'base_price' => 1000
            ]);
    }

    private function progressUserInCourse(User $user, Course $course, int $seconds = 60)
    {
        /** @var ProgressService $progressService */
        $progressService = app(ProgressServiceContract::class);
        /** @var CourseProgressRepository $progressRepository */
        $progressRepository = app(CourseProgressRepositoryContract::class);

        $progresses = $progressService->getByUser($user);

        /** @var Course $course */
        foreach ($course->topic as $topic) {
            $progressRepository->updateInTopic($topic, $user, ProgressStatus::IN_PROGRESS, $seconds);
        }
        $progressService->update($course, $user, []);
    }

    private function makePaidOrder(User $user, Course $course): Order
    {
        return Order::factory()->has(Payment::factory()->state([
            'amount' => $course->base_price,
            'billable_id' => $user->getKey(),
            'billable_type' => get_class($user),
        ]))->afterCreating(
            fn (Order $order) => $order->items()->save(new OrderItem([
                'quantity' => 1,
                'buyable_id' => $course->getKey(),
                'buyable_type' => get_class($course)
            ]))
        )->create([
            'user_id' => $user->getKey(),
            'total' =>  $course->base_price,
            'subtotal' =>  $course->base_price,
        ]);
    }

    public function testCoursesSecondsSpentMetric(): void
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->saveMany([$course, $course2]);

        $this->progressUserInCourse($student, $course, 60);

        $results = CoursesSecondsSpentMetric::make()->calculate();

        $this->assertEquals(60, $results[0]['value']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals($course->id, $results[0]['id']);

        $this->progressUserInCourse($student, $course, 120);
        $this->progressUserInCourse($student, $course2, 60);

        $results = CoursesSecondsSpentMetric::make()->calculate();

        $this->assertEquals(120, $results[0]['value']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals($course->id, $results[0]['id']);
        $this->assertEquals(60, $results[1]['value']);
        $this->assertEquals($course2->title, $results[1]['label']);
        $this->assertEquals($course2->id, $results[1]['id']);

        $report = CoursesSecondsSpentMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', Course::class)->where('measurable_id', '=', $course->getKey())->first();

        $this->assertEquals(120, $measurement->value);
        $this->assertEquals($course->title, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($course->getKey(), $measurement->measurable->getKey());
    }

    public function testCoursesPopularityMetric()
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);

        /** @var TestUser $student2 */
        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2]);

        $results = CoursesPopularityMetric::make()->calculate();

        $this->assertEquals($course->getKey(), $results[0]['id']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals(2, $results[0]['value']);
        $this->assertEquals($course2->getKey(), $results[1]['id']);
        $this->assertEquals($course2->title, $results[1]['label']);
        $this->assertEquals(1, $results[1]['value']);

        $report = CoursesPopularityMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', Course::class)->where('measurable_id', '=', $course->getKey())->first();

        $this->assertEquals(2, $measurement->value);
        $this->assertEquals($course->title, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($course->getKey(), $measurement->measurable->getKey());
    }

    public function testTutorsPopularityMetric()
    {
        $tutor = $this->makeInstructor();
        $course = $this->createCourseWithLessonAndTopic();
        $course->author()->associate($tutor)->save();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course2->author()->associate($tutor)->save();

        $tutor2 = $this->makeInstructor();
        $course3 = $this->createCourseWithLessonAndTopic();
        $course3->author()->associate($tutor2)->save();

        $students = User::factory()->count(5)->create();
        $course->users()->attach($students);
        $students = User::factory()->count(5)->create();
        $course2->users()->attach($students);
        $students = User::factory()->count(5)->create();
        $course3->users()->attach($students);

        $results = TutorsPopularityMetric::make()->calculate();

        $this->assertEquals($tutor->getKey(), $results[0]['id']);
        $this->assertEquals($tutor->email, $results[0]['label']);
        $this->assertEquals(10, $results[0]['value']);
        $this->assertEquals($tutor2->getKey(), $results[1]['id']);
        $this->assertEquals($tutor2->email, $results[1]['label']);
        $this->assertEquals(5, $results[1]['value']);

        $report = TutorsPopularityMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', AuthUser::class)->where('measurable_id', '=', $tutor->getKey())->first();

        $this->assertEquals(10, $measurement->value);
        $this->assertEquals($tutor->email, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($tutor->getKey(), $measurement->measurable->getKey());
    }

    public function testCourseMoneySpentMetric()
    {
        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        $order = $this->makePaidOrder($student, $course);
        $order2 = $this->makePaidOrder($student, $course2);
        $order3 = $this->makePaidOrder($student2, $course);

        $results = CoursesMoneySpentMetric::make()->calculate();

        $this->assertEquals($course->getKey(), $results[0]['id']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals(2000, $results[0]['value']);
        $this->assertEquals($course2->getKey(), $results[1]['id']);
        $this->assertEquals($course2->title, $results[1]['label']);
        $this->assertEquals(1000, $results[1]['value']);

        $report = CoursesMoneySpentMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', Course::class)->where('measurable_id', '=', $course->getKey())->first();

        $this->assertEquals(2000, $measurement->value);
        $this->assertEquals($course->title, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($course->getKey(), $measurement->measurable->getKey());
    }
}
