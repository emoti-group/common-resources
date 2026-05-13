<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use Emoti\CommonResources\Queue\Events\Product\ProductAddedToUpsellGroup;
use Emoti\CommonResources\Queue\Events\Product\ProductUpdated;
use Tests\TestCase;

final class MultiQueueBindingTest extends TestCase
{
    public function test_bindings_for_named_queue_are_accessible_via_dot_notation(): void
    {
        config([
            'common-resources.bindings' => [
                'critical' => [
                    ProductUpdated::class => 'App\\Listeners\\ProductUpdatedListener',
                ],
                'background_tasks' => [
                    ProductAddedToUpsellGroup::class => 'App\\Listeners\\ProductAddedListener',
                ],
            ],
        ]);

        $critical = config('common-resources.bindings.critical');
        $background = config('common-resources.bindings.background_tasks');

        $this->assertArrayHasKey(ProductUpdated::class, $critical);
        $this->assertArrayHasKey(ProductAddedToUpsellGroup::class, $background);
        $this->assertArrayNotHasKey(ProductAddedToUpsellGroup::class, $critical);
        $this->assertArrayNotHasKey(ProductUpdated::class, $background);
    }

    public function test_unknown_queue_name_returns_empty_array(): void
    {
        config(['common-resources.bindings' => []]);

        $this->assertSame([], config('common-resources.bindings.nonexistent', []));
    }

    public function test_each_queue_only_receives_its_own_routing_keys(): void
    {
        config([
            'common-resources.bindings' => [
                'critical' => [
                    ProductUpdated::class => 'App\\Listeners\\ProductUpdatedListener',
                ],
                'background_tasks' => [
                    ProductAddedToUpsellGroup::class => 'App\\Listeners\\ProductAddedListener',
                ],
            ],
        ]);

        $criticalKeys = array_keys(config('common-resources.bindings.critical'));
        $backgroundKeys = array_keys(config('common-resources.bindings.background_tasks'));

        $this->assertNotEmpty(array_diff($criticalKeys, $backgroundKeys));
        $this->assertEmpty(array_intersect($criticalKeys, $backgroundKeys));
    }
}
