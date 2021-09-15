<?php

namespace EscolaLms\Reports\Stats\Course;

class PeopleStarted extends AbstractCourseStat
{
    public function calculate(): int
    {
        return $this->course->users()->wherePivot('finished', false)->count();
    }
}
