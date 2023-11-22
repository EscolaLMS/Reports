<?php

namespace EscolaLms\Reports\Imports\Stats\Course\Sheets;

use EscolaLms\Auth\Models\User;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Topic;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Row;

abstract class FinishedTopicsSheet implements OnEachRow
{
    protected Course $course;
    protected array $headers = [];
    protected array $topics = [];

    public function __construct(Course $course)
    {
        $this->course = $course;
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
        $topicable_type = Str::contains($title, ' # ') ? Str::before($title, ' #') : null;
        $topic_title = Str::contains($title, ' # ') ? Str::after($title, '# ') : $title;
        return $this->course->topics->first(fn ($topic) => $topic->title === $topic_title);
    }
}
