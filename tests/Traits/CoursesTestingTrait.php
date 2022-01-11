<?php

namespace EscolaLms\Reports\Tests\Traits;

use EscolaLms\Cart\Models\Order;
use EscolaLms\Cart\Models\OrderItem;
use EscolaLms\Core\Models\User;
use EscolaLms\Courses\Enum\ProgressStatus;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Courses\Repositories\Contracts\CourseProgressRepositoryContract;
use EscolaLms\Courses\Repositories\CourseProgressRepository;
use EscolaLms\Courses\Services\Contracts\ProgressServiceContract;
use EscolaLms\Courses\Services\ProgressService;
use EscolaLms\Payments\Models\Payment;

trait CoursesTestingTrait
{
    private function createCourseWithLessonAndTopic(int $topic_count = 1): Course
    {
        return Course::factory()
            ->has(
                Lesson::factory(['active' => true])
                    ->has(
                        Topic::factory(['active' => true])
                            ->count($topic_count)
                    )
            )->create([
                'active' => true,
                'base_price' => 1000,
            ]);
    }

    private function progressUserInCourse(User $user, Course $course, int $seconds = 60, string $status = ProgressStatus::IN_PROGRESS)
    {
        /** @var ProgressService $progressService */
        $progressService = app(ProgressServiceContract::class);

        $progresses = $progressService->getByUser($user);

        /** @var Course $course */
        foreach ($course->topics as $topic) {
            $this->progressUserInTopic($user, $topic, $seconds, $status);
        }

        $progressService->update($course, $user, []);
    }

    private function progressUserInTopic(User $user, Topic $topic, int $seconds = 60, string $status = ProgressStatus::IN_PROGRESS): void
    {
        /** @var CourseProgressRepository $progressRepository */
        $progressRepository = app(CourseProgressRepositoryContract::class);

        $progressRepository->updateInTopic($topic, $user, $status, $seconds);
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
                'buyable_type' => get_class($course),
            ]))
        )->create([
            'user_id' => $user->getKey(),
            'total' => $course->base_price,
            'subtotal' => $course->base_price,
        ]);
    }
}
