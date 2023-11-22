<?php

namespace EscolaLms\Reports\Imports\Stats\Course\Sheets;

use EscolaLms\Auth\Models\User;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Topic;
use EscolaLms\Reports\Stats\Course\Strategies\TopicTitleStrategyContext;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;

abstract class FinishedTopicsSheet implements OnEachRow
{
    protected Course $course;
    protected array $headers = [];
    protected array $topics = [];
    protected array $courseTopics = [];

    public function __construct(Course $course)
    {
        $this->course = $course;
        $this->prepareTopics();
    }

    public function onRow(Row $row)
    {
        if ($row->getIndex() === 1) {
            $this->headers = array_filter($row->toArray(), fn ($item) => $item !== null);
            for ($i = 1; $i < count($this->headers); $i++) {
                $this->topics[$i] = $this->findTopic($this->headers[$i]);
            }
        } else {
            $user = User::query()->where('email', '=', $row[0])->first();
            if ($user) {
                for ($i = 1; $i < count($this->headers); $i++) {
                    $this->processRow($user, $this->topics[$i], $row[$i]);
                }
            }
        }
    }

    protected function processRow(User $user, Topic $topic, $value): CourseProgress
    {
        $coursesProgress = CourseProgress::query()
            ->where('user_id', '=', $user->getKey())
            ->where('topic_id', '=', $topic->getKey())
            ->first();
        if ($coursesProgress) {
            $coursesProgress->update($this->prepareUpdateData($value, $coursesProgress));
        } else {
            $coursesProgress = CourseProgress::create(array_merge([
                'user_id' => $user->getKey(),
                'topic_id' => $topic->getKey(),
            ], $this->prepareUpdateData($value)));
        }

        return $coursesProgress;
    }

    protected abstract function prepareUpdateData($value, CourseProgress $courseProgress = null): array;

    protected function findTopic(string $title): Topic
    {
        return $this->topics[$title];
    }

    protected function prepareTopics()
    {
        $this->course->topics->each(function ($topic) {
            $this->topics[(new TopicTitleStrategyContext($topic))->getStrategy()->makeTitle()] = $topic;
        });
    }
}
