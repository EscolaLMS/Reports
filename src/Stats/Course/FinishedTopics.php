<?php

namespace EscolaLms\Reports\Stats\Course;

use EscolaLms\Core\Models\User;
use EscolaLms\Courses\Models\Course;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\CourseUserPivot;
use EscolaLms\Courses\Models\Lesson;
use EscolaLms\Courses\Models\Topic;
use Illuminate\Database\Query\JoinClause;

class FinishedTopics extends AbstractCourseStat
{
    public function calculate(): array
    {
        $topicTable = (new Topic())->getTable();
        $lessonTable = (new Lesson())->getTable();
        $courseTable = (new Course())->getTable();
        $courseUserTable = (new CourseUserPivot())->getTable();
        $userTable = (new User())->getTable();
        $courseProgressTable = (new CourseProgress())->getTable();

        $result = Topic::query()
            ->select(
                $topicTable . '.id as topic_id',
                $topicTable . '.title as topic_title',
                $userTable . '.id as user_id',
                $userTable . '.email as user_email',
                $courseProgressTable . '.finished_at',
                $courseProgressTable . '.seconds',
                $courseProgressTable . '.started_at',
            )
            ->join($lessonTable, $topicTable . '.lesson_id', '=', $lessonTable . '.id')
            ->join($courseTable, $lessonTable . '.course_id', '=', $courseTable . '.id')
            ->join($courseUserTable, $courseTable . '.id', '=', $courseUserTable . '.course_id')
            ->join($userTable, $courseUserTable . '.user_id', '=', $userTable . '.id')
            ->leftJoin($courseProgressTable, fn(JoinClause $join) => $join
                ->on($courseProgressTable . '.user_id', '=', $userTable . '.id')
                ->on($courseProgressTable . '.topic_id', '=', $topicTable . '.id')
            )
            ->where($courseTable . '.id', '=', $this->course->getKey());

        return $result
            ->get()
            ->groupBy('user_email')
            ->map(fn($topics, $userEmail) => [
                'id' => $topics[0]->user_id,
                'email' => $userEmail,
                'topics' => collect($topics)->map(fn($topic) => [
                    'id' => $topic->topic_id,
                    'title' => $topic->topic_title,
                    'started_at' => $topic->started_at,
                    'seconds' => $topic->seconds,
                    'finished_at' => $topic->finished_at,
                ]),
                'seconds_total' => collect($topics)->sum('seconds'),
            ])
            ->values()
            ->toArray();
    }
}
