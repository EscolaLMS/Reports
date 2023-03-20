<?php

namespace EscolaLms\Reports\Tests\Feature;

use Carbon\Carbon;
use EscolaLms\Cart\Events\CartOrderPaid;
use EscolaLms\Cart\Listeners\AttachOrderedCoursesToUser;
use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Cart\Services\Contracts\OrderServiceContract;
use EscolaLms\Cart\Models\User as CartUser;
use EscolaLms\Core\Models\User;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\ProgressStatus;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\CourseUserPivot;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Stats\Cart\NewCustomers;
use EscolaLms\Reports\Stats\Cart\ReturningCustomers;
use EscolaLms\Reports\Stats\Cart\SpendPerCustomer;
use EscolaLms\Reports\Stats\Course\AverageTime;
use EscolaLms\Reports\Stats\Course\AverageTimePerTopic;
use EscolaLms\Reports\Stats\Course\Finished;
use EscolaLms\Reports\Stats\Course\FinishedTopics;
use EscolaLms\Reports\Stats\Course\MoneyEarned;
use EscolaLms\Reports\Stats\Course\PeopleBought;
use EscolaLms\Reports\Stats\Course\PeopleFinished;
use EscolaLms\Reports\Stats\Course\PeopleStarted;
use EscolaLms\Reports\Stats\Course\Started;
use EscolaLms\Reports\Stats\User\ActiveUsers;
use EscolaLms\Reports\Stats\User\NewUsers;
use EscolaLms\Reports\Tests\Models\Course;
use EscolaLms\Reports\Tests\Models\TestUser;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\Reports\Tests\Traits\CoursesTestingTrait;
use EscolaLms\Reports\Tests\Traits\NotificationTestingTrait;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;

class StatsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;
    use CoursesTestingTrait, NotificationTestingTrait;

    protected function setUp(): void
    {
        parent::setUp();
        User::query()->delete();
    }

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

        $this->assertEquals(2000, $result);
    }

    public function testBundledCoursesMoneyEarned()
    {
        $course = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $this->makePaidOrder($student, $course);
        $this->makePaidOrder($student2, $course, $course2);

        $result = MoneyEarned::make($course)->calculate();
        $this->assertEquals(1500, $result);

        $result = MoneyEarned::make($course2)->calculate();
        $this->assertEquals(500, $result);
    }

    public function testCourseMoneyEarnedWithOrderItemThatIsNotRepresentingProductModel()
    {
        $course = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();

        $order = $this->makePaidOrder($student, $course);

        /** This order item will be ignored in stats calculation as it does not represent product as defined by Cart package */
        $order->items()->save(new OrderItem([
            'price' => 999,
            'quantity' => 1,
            'buyable_id' => $course->getKey(),
            'buyable_type' => get_class($course),
        ]));

        $result = MoneyEarned::make($course)->calculate();

        $this->assertEquals(1000, $result);
    }

    public function testPeopleBought()
    {
        $course = $this->createCourseWithLessonAndTopic();

        $student = $this->makeStudent();
        $student2 = $this->makeStudent();

        $order = $this->makePaidOrder($student, $course);
        $order2 = $this->makePaidOrder($student2, $course);

        app(OrderServiceContract::class)->processOrderItems($order);
        app(OrderServiceContract::class)->processOrderItems($order2);

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

        $result = PeopleFinished::make($course)->calculate();
        $this->assertEquals(1, $result);

        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::COMPLETE);

        $result = PeopleFinished::make($course)->calculate();
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

        $result = PeopleStarted::make($course)->calculate();
        $this->assertEquals(1, $result);

        $this->progressUserInCourse($student2, $course, 60, ProgressStatus::COMPLETE);

        $result = PeopleStarted::make($course)->calculate();
        $this->assertEquals(0, $result);
    }

    public function testNewCustomers(): void
    {
        CartUser::factory()
            ->count(10)
            ->has(Order::factory())
            ->create();

        CartUser::factory()
            ->count(15)
            ->has(Order::factory())
            ->create(['created_at' => Carbon::now()->subDay()]);

        $result = NewCustomers::make()->calculate();

        $this->assertEquals(10, $result);
    }

    public function testSpendPerCustomer(): void
    {
        $users = CartUser::factory()
            ->count(20)
            ->has(Order::factory()->state(['total' => rand(10, 1000)]))
            ->create();

        $orders = Order::whereIn('user_id', $users->pluck('id'));

        $result = SpendPerCustomer::make()->calculate();

        $this->assertEquals(round($orders->sum('total') / $users->count()), $result);
    }

    public function testReturningCustomers(): void
    {
        $users = CartUser::factory()
            ->count(20)
            ->has(Order::factory()->state(['created_at' => Carbon::now()->subYear()->subDay()]))
            ->create();

        $users
            ->slice(0, 10)
            ->each(fn($user) => Order::factory()->create(['user_id' => $user->getKey(), 'created_at' => Carbon::now()->subMonth()]));
        $users
            ->slice(10, 5)
            ->each(fn($user) => Order::factory()->create(['user_id' => $user->getKey(), 'created_at' => Carbon::today()]));

        $result = ReturningCustomers::make()->calculate();

        $this->assertEquals(5, $result);
    }

    public function testCourseStarted(): void
    {
        $start = Carbon::now()->subDays(3)->format('Y-m-d');
        $end = Carbon::now()->addDays(10)->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $course1 = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course3 = $this->createCourseWithLessonAndTopic();
        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course1->getKey()]);
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course2->getKey()]);
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course3->getKey()]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course1->getKey()]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course2->getKey()]);

        $result = Started::make()->calculate();
        $this->assertEquals(5, $result[$today]);

        $student3 = $this->makeStudent();
        $student4 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course1->getKey(), 'created_at' => Carbon::now()->subDays(3)]);
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course2->getKey(), 'created_at' => Carbon::now()->subDays(3)]);
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course3->getKey(), 'created_at' => Carbon::now()->subDays(3)]);
        CourseUserPivot::create(['user_id' => $student4->getKey(), 'course_id' => $course1->getKey(), 'created_at' => Carbon::now()->subDays(3)]);
        CourseUserPivot::create(['user_id' => $student4->getKey(), 'course_id' => $course2->getKey(), 'created_at' => Carbon::now()->subDays(3)]);

        $result = Started::make(Carbon::now()->subDays(4))->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(5, $result[$today]);
        $this->assertEquals(5, $result[$start]);

        $result = Started::make(Carbon::now()->subDays(4), Carbon::now()->subDays(2))->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(5, $result[$start]);

        $student5 = $this->makeStudent();
        $student6 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student5->getKey(), 'course_id' => $course1->getKey(), 'created_at' => Carbon::now()->addDays(10)]);
        CourseUserPivot::create(['user_id' => $student5->getKey(), 'course_id' => $course2->getKey(), 'created_at' => Carbon::now()->addDays(10)]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course3->getKey(), 'created_at' => Carbon::now()->addDays(10)]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course1->getKey(), 'created_at' => Carbon::now()->addDays(10)]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course2->getKey(), 'created_at' => Carbon::now()->addDays(10)]);

        $result = Started::make(Carbon::now()->addDays(9), Carbon::now()->addDays(10))->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(5, $result[$end]);

        $result = Started::make(Carbon::now()->subMonth(), Carbon::now()->addMonth())->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(5, $result[$today]);
        $this->assertEquals(5, $result[$start]);
        $this->assertEquals(5, $result[$end]);
    }

    public function testCoursesFinished(): void
    {
        $start = Carbon::now()->subDays(3)->format('Y-m-d');
        $end = Carbon::now()->addDays(10)->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $course1 = $this->createCourseWithLessonAndTopic();
        $course2 = $this->createCourseWithLessonAndTopic();
        $course3 = $this->createCourseWithLessonAndTopic();
        $student1 = $this->makeStudent();
        $student2 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::today(), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::today(), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student1->getKey(), 'course_id' => $course3->getKey(), 'updated_at' => Carbon::today(), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::today(), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student2->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::today(), 'finished' => false]);

        $result = Finished::make()->calculate();
        $this->assertEquals(3, $result[$today]);

        $student3 = $this->makeStudent();
        $student4 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student3->getKey(), 'course_id' => $course3->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student4->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student4->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::now()->subDays(3), 'finished' => true]);

        $result = Finished::make(Carbon::now()->subDays(4))->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(3, $result[$today]);
        $this->assertEquals(2, $result[$start]);

        $result = Finished::make(Carbon::now()->subDays(4), Carbon::now()->subDays(2))->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(2, $result[$start]);

        $student5 = $this->makeStudent();
        $student6 = $this->makeStudent();
        CourseUserPivot::create(['user_id' => $student5->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => true]);
        CourseUserPivot::create(['user_id' => $student5->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course3->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course1->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => false]);
        CourseUserPivot::create(['user_id' => $student6->getKey(), 'course_id' => $course2->getKey(), 'updated_at' => Carbon::now()->addDays(10), 'finished' => false]);

        $result = Finished::make(Carbon::now()->addDays(9), Carbon::now()->addDays(10))->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(1, $result[$end]);

        $result = Finished::make(Carbon::now()->subMonth(), Carbon::now()->addMonth())->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(3, $result[$today]);
        $this->assertEquals(2, $result[$start]);
        $this->assertEquals(1, $result[$end]);
    }

    public function testActiveUsers(): void
    {
        $start = Carbon::now()->subMonth()->format('Y-m-d');
        $end = Carbon::now()->addMonth()->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $this->createNotification($user1);
        $this->createNotification($user2);
        $this->createNotification($user1, Carbon::now()->addMonth());
        $this->createNotification($user1, Carbon::now()->subMonth());

        $result = ActiveUsers::make()->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(2, $result[$today]);

        $result = ActiveUsers::make(Carbon::now()->subMonth(), Carbon::now()->subDay())->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(1, $result[$start]);
    }

    public function testNewUsers(): void
    {
        $start = Carbon::now()->subMonth()->format('Y-m-d');
        $end = Carbon::now()->addDays(5)->format('Y-m-d');
        $today = Carbon::today()->format('Y-m-d');

        User::factory()->count(10)->create(['created_at' => Carbon::today()]);
        User::factory()->count(5)->create(['created_at' => Carbon::now()->addDays(5)]);
        User::factory()->count(20)->create(['created_at' => Carbon::now()->subMonth()]);

        $result = NewUsers::make()->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(10, $result[$today]);

        $result = NewUsers::make(Carbon::now()->subMonth())->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayNotHasKey($end, $result);
        $this->assertEquals(10, $result[$today]);
        $this->assertEquals(20, $result[$start]);

        $result = NewUsers::make(Carbon::now()->addDay(), Carbon::now()->addMonth())->calculate();
        $this->assertArrayNotHasKey($today, $result);
        $this->assertArrayNotHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(5, $result[$end]);

        $result = NewUsers::make(Carbon::now()->subMonth(), Carbon::now()->addMonth())->calculate();
        $this->assertArrayHasKey($today, $result);
        $this->assertArrayHasKey($start, $result);
        $this->assertArrayHasKey($end, $result);
        $this->assertEquals(10, $result[$today]);
        $this->assertEquals(20, $result[$start]);
        $this->assertEquals(5, $result[$end]);
    }

    public function testFinishedTopics(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();
        $course = Course::factory()->create();
        $lesson = Lesson::factory()->state(['course_id' => $course->getKey()])->create();
        $topic1 = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();
        $topic2 = Topic::factory()->state(['lesson_id' => $lesson->getKey()])->create();

        $course->users()->attach($user1);
        $course->users()->attach($user2);
        $course->users()->attach($user3);

        CourseProgress::create(['topic_id' => $topic1->getKey(), 'user_id' => $user1->getKey(), 'finished_at' => Carbon::now()]);
        CourseProgress::create(['topic_id' => $topic2->getKey(), 'user_id' => $user1->getKey(), 'finished_at' => Carbon::now()]);
        CourseProgress::create(['topic_id' => $topic1->getKey(), 'user_id' => $user2->getKey(), 'finished_at' => Carbon::now()]);

        $result = FinishedTopics::make($course)->calculate();

        // user1
        $this->assertCount(3, $result);
        $this->assertEquals($user1->id, $result[0]['id']);
        $this->assertEquals($user1->email, $result[0]['email']);
        $this->assertCount(2, $result[0]['topics']);
        $this->assertCount(2, $result[0]['topics']->filter(fn($topic) => $topic['finished_at']));

        // user2
        $this->assertEquals($user2->email, $result[1]['email']);
        $this->assertEquals($user2->id, $result[1]['id']);
        $this->assertEquals($user2->email, $result[1]['email']);
        $this->assertCount(2, $result[1]['topics']);
        $this->assertCount(1, $result[1]['topics']->filter(fn($topic) => $topic['finished_at']));

        // user3
        $this->assertEquals($user3->email, $result[2]['email']);
        $this->assertEquals($user3->id, $result[2]['id']);
        $this->assertEquals($user3->email, $result[2]['email']);
        $this->assertCount(2, $result[2]['topics']);
        $this->assertCount(0, $result[2]['topics']->filter(fn($topic) => $topic['finished_at']));
    }
}
