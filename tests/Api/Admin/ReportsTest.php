<?php

namespace EscolaLms\Reports\Tests\Api\Admin;

use EscolaLms\Core\Tests\ApiTestTrait;
use EscolaLms\Core\Tests\CreatesUsers;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Reports\Metrics\CoursesPopularityMetric;
use EscolaLms\Reports\Models\Report;
use EscolaLms\Reports\Tests\TestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Carbon;


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
        Topic::query()->delete();
        Lesson::query()->delete();
        Course::query()->delete();

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
}
