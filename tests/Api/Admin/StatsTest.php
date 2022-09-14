<?php

namespace EscolaLms\Reports\Tests\Api\Admin;

use EscolaLms\Cart\Models\Cart;
use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\ProgressStatus;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Tests\TestCase;
use EscolaLms\Reports\Tests\Traits\CoursesTestingTrait;
use EscolaLms\Reports\ValueObject\DateRange;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Testing\TestResponse;

class StatsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;
    use CoursesTestingTrait;

    public function testAvailable()
    {
        $admin = $this->makeAdmin();
        $response = $this->actingAs($admin)->json('GET', '/api/admin/stats/available');
        $response->assertOk();
        $stats = config('reports.stats');
        $response->assertJsonFragment([
            'data' => $stats
        ]);
    }

    public function testCourse()
    {
        $admin = $this->makeAdmin();

        $course = $this->createCourseWithLessonAndTopic(3);
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $this->progressUserInCourse($student, $course);
        $this->progressUserInCourse($student2, $course, 30, ProgressStatus::COMPLETE);

        /** @var TestResponse $response */
        $response = $this->actingAs($admin)->json('GET', '/api/admin/stats/course/' . $course->getKey());
        $response->assertOk();
        $stats = config('reports.stats')[Course::class] ?? [];
        $response->assertJsonStructure([
            'data' => $stats
        ]);
    }

    public function testCoursePartial()
    {
        $admin = $this->makeAdmin();

        $course = $this->createCourseWithLessonAndTopic(3);
        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);
        $student2 = $this->makeStudent();
        $student2->courses()->save($course);

        $this->progressUserInCourse($student, $course);
        $this->progressUserInCourse($student2, $course, 30, ProgressStatus::COMPLETE);

        /** @var TestResponse $response */
        $response = $this->actingAs($admin)->json('GET', '/api/admin/stats/course/' . $course->getKey(), [
            'stats' => [\EscolaLms\Reports\Stats\Course\AverageTime::class]
        ]);
        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'data' => [
                \EscolaLms\Reports\Stats\Course\AverageTime::class => 135
            ]
        ]);
    }

    public function testCart(): void
    {
        $admin = $this->makeAdmin();

        $stats = config('reports.stats')[Cart::class] ?? [];

        $this->actingAs($admin)
            ->json('GET', '/api/admin/stats/cart')
            ->assertOk()
            ->assertJsonStructure(['data' => $stats]);
    }

    public function testDateRange(): void
    {
        $admin = $this->makeAdmin();

        $stats = config('reports.stats')[DateRange::class] ?? [];

        $this->actingAs($admin)
            ->json('GET', '/api/admin/stats/date-range')
            ->assertOk()
            ->assertJsonStructure(['data' => $stats]);
    }
}
