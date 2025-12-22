<?php

namespace Emoti\CommonResources\DTO\PostHog;

use Emoti\CommonResources\Services\PostHog\PostHogAbstractService;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;

class PostHogBreadcrumb extends Data
{
    public string $timestamp;

    public function __construct(
        public string $level,
        public string $type,
        public string $category,
        public string $message,
        public array $metadata = [],
    ) {
        $this->timestamp = Carbon::now()->toIso8601String();
    }

    public function push(PostHogAbstractService $postHogService): void
    {
        $postHogService->addBreadcrumb($this);
    }

    public function toArray(): array
    {
        return [
            'level' => $this->level,
            'type' => $this->type,
            'category' => $this->category,
            'message' => $this->message,
            'metadata' => $this->metadata,
            'timestamp' => $this->timestamp,
        ];
    }
}
