<?php

namespace EscolaLms\Reports\Stats\Course;

class PeopleFinished extends AbstractCourseStat
{
    public function calculate(): int
    {
        return $this->course->users()->wherePivot('finished', '=', true)->count();
    }
}
