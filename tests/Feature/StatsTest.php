<?php

namespace EscolaLms\Reports\Tests\Feature;

use EscolaLms\Cart\Events\OrderPaid;
use EscolaLms\Cart\Listeners\AttachOrderedCoursesToUser;
use EscolaLms\Cart\Services\OrderProcessingService;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\ProgressStatus;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Group;
use EscolaLms\Reports\Stats\Course\AverageTime;
use EscolaLms\Reports\Stats\Course\AverageTimePerTopic;
use EscolaLms\Reports\Stats\Course\MoneyEarned;
use EscolaLms\Reports\Stats\Course\PeopleBought;
use EscolaLms\Reports\Stats\Course\PeopleFinished;
use EscolaLms\Reports\Stats\Course\PeopleStarted;
use EscolaLms\Reports\Tests\Models\TestUser;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\Reports\Tests\Traits\CoursesTestingTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class StatsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;
    use CoursesTestingTrait;

    public function testCourseAverageTime()
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course3 = $this->createCourseWithLessonAndTopic();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->saveMany([$course, $course2, $course3]);

        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2, $course3]);

        $this->progressUserInCourse($student, $course, 60);
        $this->progressUserInCourse($student, $course2, 60);
        $this->progressUserInCourse($student, $course3, 60);
        $this->progressUserInCourse($student2, $course, 30);
        $this->progressUserInCourse($student2, $course3, 0);

        $result1 = AverageTime::make($course)->calculate();
        $result2 = AverageTime::make($course2)->calculate();
        $result3 = AverageTime::make($course3)->calculate();

        $this->assertEquals(45, $result1);
        $this->assertEquals(60, $result2); // there is user that has no progress entry about this topic, but in reality this is impossible as giving access to course automatically generates progresses for all topics
        $this->assertEquals(30, $result3);
    }

    public function testCourseAverageTimePerTopic()
    {
        $course = $this->createCourseWithLessonAndTopic(3);
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $this->progressUserInTopic($student, $course->topics->get(0), 30);
        $this->progressUserInTopic($student, $course->topics->get(1), 50);
        $this->progressUserInTopic($student, $course->topics->get(2), 100);
        $this->progressUserInTopic($student2, $course->topics->get(0), 60);
        $this->progressUserInTopic($student2, $course->topics->get(1), 0);
        $this->progressUserInTopic($student2, $course->topics->get(2), 50);

        $results = AverageTimePerTopic::make($course)->calculate();

        $this->assertEquals(45, $results->get($course->topics->get(0)->id));
        $this->assertEquals(25, $results->get($course->topics->get(1)->id));
        $this->assertEquals(75, $results->get($course->topics->get(2)->id));
    }

    public function testCourseMoneyEarned()
    {
        $course = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $this->makePaidOrder($student, $course);
        $this->makePaidOrder($student2, $course);

        $result = MoneyEarned::make($course)->calculate();

        $this->assertEquals($course->base_price * 2, $result);
    }

    public function testPeopleBought()
    {
        $course = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $order = $this->makePaidOrder($student, $course);
        $order2 = $this->makePaidOrder($student2, $course);

        $orderProcessingService = new OrderProcessingService();
        (new AttachOrderedCoursesToUser($orderProcessingService))->handle(new OrderPaid($order, $student));
        (new AttachOrderedCoursesToUser($orderProcessingService))->handle(new OrderPaid($order2, $student2));

        $result = PeopleBought::make($course)->calculate();
        $this->assertEquals(2, $result);

        $result = PeopleBought::make($course)->calculateReallyBought();
        $this->assertEquals(2, $result);
    }

    public function testPeopleFinished()
    {
        $course = $this->createCourseWithLessonAndTopic();
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $result = PeopleFinished::make($course)->calculate();
        $this->assertEquals(0, $result);

        $this->progressUserInCourse($student, $course, 60, ProgressStatus::COMPLETE);
        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::INCOMPLETE);

        $result = PeopleFinished::make($course->refresh())->calculate();
        $this->assertEquals(1, $result);

        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::COMPLETE);

        $result = PeopleFinished::make($course->refresh())->calculate();
        $this->assertEquals(2, $result);
    }

    public function testPeopleStarted()
    {
        $course = $this->createCourseWithLessonAndTopic();
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $result = PeopleStarted::make($course)->calculate();
        $this->assertEquals(2, $result);

        $this->progressUserInCourse($student, $course, 60, ProgressStatus::COMPLETE);
        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::INCOMPLETE);

        $result = PeopleStarted::make($course->refresh())->calculate();
        $this->assertEquals(1, $result);

        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::COMPLETE);

        $result = PeopleStarted::make($course->refresh())->calculate();
        $this->assertEquals(0, $result);
    }
}
