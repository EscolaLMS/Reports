<?php

namespace EscolaLms\Reports\Tests\Api\Admin;

use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Enum\CoursesPermissionsEnum;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Enums\ReportsPermissionsEnum;
use EscolaLms\Reports\Metrics\CoursesPopularityMetric;
use EscolaLms\Reports\Models\Report;
use EscolaLms\Reports\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Permission;

class ReportsTest extends TestCase
{
    use CreatesUsers, ApiTestTrait, WithoutMiddleware, DatabaseTransactions;

    public function testMetrics()
    {
        $admin = $this->makeAdmin();

        $metrics = [
            \EscolaLms\Reports\Metrics\CoursesMoneySpentMetric::class,
            \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            \EscolaLms\Reports\Metrics\CoursesSecondsSpentMetric::class,
            \EscolaLms\Reports\Metrics\TutorsPopularityMetric::class,
            \EscolaLms\Reports\Metrics\CoursesBestRatedMetric::class,
            \EscolaLms\Reports\Metrics\CoursesTopSellingMetric::class,
            \EscolaLms\Reports\Metrics\CoursesAuthoredPopularityMetric::class,
            \EscolaLms\Reports\Metrics\CoursesAuthoredMoneySpentMetric::class,
            \EscolaLms\Reports\Metrics\CoursesAuthoredSecondsSpentMetric::class,
        ];

        config('reports.metrics', $metrics);

        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/metrics');

        $response->assertJsonFragment(['data' => $metrics]);
    }

    public function testReportCurrent()
    {
        $admin = $this->makeAdmin();

        $course = Course::factory()->create();
        $course2 = Course::factory()->create();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);

        /** @var TestUser $student2 */
        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2]);

        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
        ]);

        $response->assertOk();

        $response->assertJsonFragment([
            'label' => $course->title,
            'value' => 2
        ]);
        $response->assertJsonFragment([
            'label' => $course2->title,
            'value' => 1
        ]);
    }

    public function testReportNotFound()
    {
        $admin = $this->makeAdmin();

        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            'date' => Carbon::yesterday()
        ]);

        $response->assertNotFound();
    }

    public function testReportToday()
    {
        $admin = $this->makeAdmin();

        $course = Course::factory()->create();
        $course2 = Course::factory()->create();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);

        /** @var TestUser $student2 */
        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2]);

        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            'date' => Carbon::today(),
        ]);

        $response->assertOk();

        $response->assertJsonFragment([
            'label' => $course->title,
            'value' => 2
        ]);
        $response->assertJsonFragment([
            'label' => $course2->title,
            'value' => 1
        ]);
    }

    public function testReportTodayMultipleTimes()
    {
        $admin = $this->makeAdmin();

        $course = Course::factory()->create();
        $course2 = Course::factory()->create();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);

        /** @var TestUser $student2 */
        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2]);

        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            'date' => Carbon::today(),
        ]);

        $response->assertOk();

        $response->assertJsonFragment([
            'label' => $course->title,
            'value' => 2
        ]);
        $response->assertJsonFragment([
            'label' => $course2->title,
            'value' => 1
        ]);

        $this->assertEquals(1, Report::count());

        $student->courses()->save($course2);

        // Will return same report that first call
        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            'date' => Carbon::today(),
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'label' => $course->title,
            'value' => 2
        ]);
        $response->assertJsonFragment([
            'label' => $course2->title,
            'value' => 1
        ]);

        $this->assertEquals(1, Report::count());

        // Will calculate fresh report that has different value for course 2
        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
        ]);

        $response->assertOk();

        $response->assertJsonFragment([
            'label' => $course->title,
            'value' => 2
        ]);
        $response->assertJsonFragment([
            'label' => $course2->title,
            'value' => 2
        ]);

        $this->assertEquals(2, Report::count());

        // Will again calculate fresh report because we are trying to fetch more data points that were available in last saved report
        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            'limit' => 11,
            'date' => Carbon::today(),
        ]);

        $response->assertOk();

        $response->assertJsonFragment([
            'label' => $course->title,
            'value' => 2
        ]);
        $response->assertJsonFragment([
            'label' => $course2->title,
            'value' => 2
        ]);

        $this->assertEquals(3, Report::count());
    }

    public function testReportLimit()
    {
        $admin = $this->makeAdmin();

        $course = Course::factory()->create();
        $course2 = Course::factory()->create();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);

        /** @var TestUser $student2 */
        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2]);

        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            'limit' => 1,
        ]);

        $response->assertOk();

        $response->assertJsonFragment([
            'label' => $course->title,
            'value' => 2
        ]);
        $response->assertJsonMissing([
            'label' => $course2->title,
            'value' => 1
        ]);
    }

    public function testReportHistorical()
    {
        $admin = $this->makeAdmin();

        $course = Course::factory()->create();
        $course2 = Course::factory()->create();

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);

        /** @var TestUser $student2 */
        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2]);

        $report = CoursesPopularityMetric::make()->calculateAndStore();
        $report->created_at = Carbon::yesterday();
        $report->save();

        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            'date' => Carbon::yesterday()
        ]);

        $response->assertOk();

        $response->assertJsonFragment([
            'label' => $course->title,
            'value' => 2
        ]);
        $response->assertJsonFragment([
            'label' => $course2->title,
            'value' => 1
        ]);

        $response = $this->actingAs($admin)->json('GET', '/api/admin/reports/report', [
            'metric' => \EscolaLms\Reports\Metrics\CoursesPopularityMetric::class,
            'date' => Carbon::yesterday()->subDay()
        ]);

        $response->assertNotFound();
    }

    public function testAvailableForUserUnauthorized(): void
    {
        $this
            ->json('GET', '/api/admin/reports/available-for-user')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function testAvailableForAdmin(): void
    {
        Permission::findOrCreate(CoursesPermissionsEnum::COURSE_LIST, 'api');
        Permission::findOrCreate(CoursesPermissionsEnum::COURSE_LIST_OWNED, 'api');
        $admin = $this->makeAdmin();
        $admin->givePermissionTo([CoursesPermissionsEnum::COURSE_LIST, CoursesPermissionsEnum::COURSE_LIST_OWNED]);
        $stats = config('reports.metrics');
        $this
            ->actingAs($admin)
            ->json('GET', '/api/admin/reports/available-for-user')
            ->assertOk()
            ->assertJsonFragment([
                'data' => $stats
            ]);
    }

    public function testReportCourseAuthoredPopularity()
    {
        Permission::findOrCreate(CoursesPermissionsEnum::COURSE_LIST_OWNED, 'api');
        $course = Course::factory()->create();
        $course2 = Course::factory()->create();

        $tutor1 = $this->makeInstructor();
        $tutor2 = $this->makeInstructor();
        $tutor1->givePermissionTo([ReportsPermissionsEnum::DISPLAY_REPORTS, CoursesPermissionsEnum::COURSE_LIST_OWNED]);

        $tutor1->authoredCourses()->save($course);
        $tutor2->authoredCourses()->save($course2);

        /** @var TestUser $student */
        $student = $this->makeStudent();
        $student->courses()->save($course);

        /** @var TestUser $student2 */
        $student2 = $this->makeStudent();
        $student2->courses()->saveMany([$course, $course2]);

        $this
            ->actingAs($tutor1)
            ->json('GET', '/api/admin/reports/report', [
                'metric' => \EscolaLms\Reports\Metrics\CoursesAuthoredPopularityMetric::class,
            ])
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'label' => $course->title,
                'value' => 2,
            ])
            ->assertJsonMissing([
                'label' => $course2->title,
                'value' => 1,
            ]);
    }
}
