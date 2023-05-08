<?php

namespace EscolaLms\Reports\Stats\Course;

class FinishedCourse extends AbstractCourseStat
{
    public function calculate()
    {
        $users = $this->course->users()->withPivot('updated_at', 'finished');
        return $users
            ->get()
            ->map(fn($user) => [
                'id' => $user->id,
                'email' => $user->email,
                'finished_at' => $user->pivot->finished ? $user->pivot->updated_at : null,
                'finished' => $user->pivot->finished,
            ])
            ->values()
            ->toArray();
    }
}
