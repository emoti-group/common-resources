<?php

namespace Emoti\CommonResources\DTO\PostHog;

use Carbon\Carbon;
use Emoti\CommonResources\Enums\PostHogEvent;
use Spatie\LaravelData\Data;

final class CaptureData extends Data
{
    public string $timestamp;

    public function __construct(
        public string $distinctId,
        public PostHogEvent|string $event,
        public PropertiesData $properties,
    ) {
        $this->timestamp = Carbon::now()->toIso8601String();
    }

    public function toArray(): array
    {
        return [
            'distinctId' => $this->distinctId,
            'event' => is_string($this->event) ? $this->event : $this->event->value,
            'properties' => $this->properties->toArray(),
            'timestamp' => $this->timestamp,
            ...$this->getAdditionalData()
        ];
    }
}
