<?php

namespace EscolaLms\Reports\Tests\Feature;

use EscolaLms\Auth\Models\User as AuthUser;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Reports\Metrics\CoursesMoneySpentMetric;
use EscolaLms\Reports\Metrics\CoursesPopularityMetric;
use EscolaLms\Reports\Metrics\CoursesSecondsSpentMetric;
use EscolaLms\Reports\Metrics\TutorsPopularityMetric;
use EscolaLms\Reports\Tests\Models\TestUser;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\Reports\Tests\Traits\CoursesTestingTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class MetricsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;
    use CoursesTestingTrait;

    public function testCoursesSecondsSpentMetric(): void
    {
        Course::truncate();
        CourseProgress::truncate();

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
        Course::truncate();

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
        User::truncate();
        Course::truncate();

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
