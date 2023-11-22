<?php

namespace EscolaLms\Reports\Imports\Stats\Course\Sheets;

use EscolaLms\Auth\Models\User;
use EscolaLms\Courses\Models\CourseProgress;
use EscolaLms\Courses\Models\Topic;

class FinishedTopicsSecondsSheet extends FinishedTopicsSheet
{
    protected function prepareUpdateData($value, CourseProgress $courseProgress = null): array
    {
        return [
            'seconds' => $value,
        ];
    }
}
