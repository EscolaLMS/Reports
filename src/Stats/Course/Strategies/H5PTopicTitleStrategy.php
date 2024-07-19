<?php

namespace EscolaLms\Reports\Stats\Course\Strategies;

use EscolaLms\Courses\Models\Topic;
use EscolaLms\HeadlessH5P\Models\H5PContent;

class H5PTopicTitleStrategy implements TopicTitleStrategy
{
    private Topic $topic;

    public function __construct(Topic $topic)
    {
        $this->topic = $topic;
    }

    public function makeTitle(): string
    {
        $h5pContent = H5PContent::find($this->topic->topicable->value);

        // @phpstan-ignore-next-line
        if (!$h5pContent || !$h5pContent->library) {
            // @phpstan-ignore-next-line
            return class_basename($this->topic->topicable_type) . ' # ' . ($this->topic->topic_title ?? $this->topic->title);
        }

        // @phpstan-ignore-next-line
        return $h5pContent->library->uberName . ' # ' . ($this->topic->topic_title ?? $this->topic->title);
    }
}
