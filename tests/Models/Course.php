<?php

namespace EscolaLms\Reports\Tests\Models;

use EscolaLms\Cart\Contracts\Productable;
use EscolaLms\Cart\Contracts\ProductableTrait;
use EscolaLms\Core\Models\User;
use EscolaLms\Courses\Models\Course as BaseCourse;

class Course extends BaseCourse implements Productable
{
    use ProductableTrait;

    public function attachToUser(User $user): void
    {
        $this->users()->syncWithoutDetaching($user->getKey());
    }
}
