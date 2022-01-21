<?php

namespace EscolaLms\Reports\Tests\Feature;

use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Course;
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
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->saveMany([$course, $course2]);

        $this->progressUserInCourse($student, $course, 999999);

        $results = CoursesSecondsSpentMetric::make()->calculate();

        $this->assertEquals(999999, $results[0]['value']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals($course->id, $results[0]['id']);

        $this->progressUserInCourse($student, $course, 888888);
        $this->progressUserInCourse($student, $course2, 777777);

        $results = CoursesSecondsSpentMetric::make()->calculate();

        $this->assertEquals(888888, $results[0]['value']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals($course->id, $results[0]['id']);
        $this->assertEquals(777777, $results[1]['value']);
        $this->assertEquals($course2->title, $results[1]['label']);
        $this->assertEquals($course2->id, $results[1]['id']);

        $report = CoursesSecondsSpentMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', Course::class)->where('measurable_id', '=', $course->getKey())->first();

        $this->assertEquals(888888, $measurement->value);
        $this->assertEquals($course->title, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($course->getKey(), $measurement->measurable->getKey());

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), CoursesSecondsSpentMetric::make()->requiredPackageInstalled());
        $this->assertIsString(CoursesSecondsSpentMetric::make()->requiredPackageInstalled());
    }

    public function testCoursesPopularityMetric()
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        $students = User::factory()->count(200)->create();
        $course->users()->attach($students);
        $students = User::factory()->count(100)->create();
        $course2->users()->attach($students);

        $results = CoursesPopularityMetric::make()->calculate();

        $this->assertEquals($course->getKey(), $results[0]['id']);
        $this->assertEquals($course->title, $results[0]['label']);
        $this->assertEquals(200, $results[0]['value']);

        $this->assertEquals($course2->getKey(), $results[1]['id']);
        $this->assertEquals($course2->title, $results[1]['label']);
        $this->assertEquals(100, $results[1]['value']);

        $report = CoursesPopularityMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', Course::class)->where('measurable_id', '=', $course->getKey())->first();

        $this->assertEquals(200, $measurement->value);
        $this->assertEquals($course->title, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($course->getKey(), $measurement->measurable->getKey());

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), CoursesPopularityMetric::make()->requiredPackageInstalled());
        $this->assertIsString(CoursesPopularityMetric::make()->requiredPackageInstalled());
    }

    public function testTutorsPopularityMetric()
    {
        $tutor = $this->makeInstructor();
        $course = $this->createCourseWithLessonAndTopic();
        $course->authors()->sync($tutor);
        $course2 = $this->createCourseWithLessonAndTopic();
        $course2->authors()->sync($tutor);

        $tutor2 = $this->makeInstructor();
        $course3 = $this->createCourseWithLessonAndTopic();
        $course3->authors()->sync($tutor2);

        $students = User::factory()->count(100)->create();
        $course->users()->sync($students);
        $students = User::factory()->count(100)->create();
        $course2->users()->sync($students);

        $students = User::factory()->count(100)->create();
        $course3->users()->sync($students);

        $results = TutorsPopularityMetric::make()->calculate();

        $this->assertEquals($tutor->getKey(), $results[0]['id']);
        $this->assertEquals($tutor->email, $results[0]['label']);
        $this->assertEquals(200, $results[0]['value']);
        $this->assertEquals($tutor2->getKey(), $results[1]['id']);
        $this->assertEquals($tutor2->email, $results[1]['label']);
        $this->assertEquals(100, $results[1]['value']);

        $report = TutorsPopularityMetric::make()->calculateAndStore();

        $measurement = $report->measurements()->where('measurable_type', '=', User::class)->where('measurable_id', '=', $tutor->getKey())->first();

        $this->assertEquals(200, $measurement->value);
        $this->assertEquals($tutor->email, $measurement->label);
        $this->assertEquals($report->getKey(), $measurement->report->getKey());
        $this->assertEquals($tutor->getKey(), $measurement->measurable->getKey());

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), TutorsPopularityMetric::make()->requiredPackageInstalled());
        $this->assertIsString(TutorsPopularityMetric::make()->requiredPackageInstalled());
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

        $this->assertEquals(class_exists(\EscolaLms\Courses\EscolaLmsCourseServiceProvider::class), CoursesMoneySpentMetric::make()->requiredPackageInstalled());
        $this->assertIsString(CoursesMoneySpentMetric::make()->requiredPackageInstalled());
    }
}
