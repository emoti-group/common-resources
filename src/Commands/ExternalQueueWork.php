<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Commands;

use Emoti\CommonResources\Queue\Consumer\ConsumerInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;
use Throwable;

class ExternalQueueWork extends Command
{
    protected $signature = 'common-resources:queue-external:work';

    public function handle(): void
    {
        /** @var ConsumerInterface $consumer */
        $consumer = App::make(ConsumerInterface::class);

        $consumer->consume(
            captureException: fn(Throwable $e) => report($e),
        );
    }
}
