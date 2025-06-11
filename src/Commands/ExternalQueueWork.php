<?php

declare(strict_types=1);

namespace Emoti\CommonResources\Commands;

use Emoti\CommonResources\Queue\Consumer\ConsumerInterface;
use Exception;
use Illuminate\Console\Command;

class ExternalQueueWork extends Command
{
    protected $signature = 'common-resources:queue-external:work';

    public function __construct(private readonly ConsumerInterface $consumer)
    {
        parent::__construct();
    }

    public function handle(): void
    {
        $this->consumer->consume(
            captureException: fn(Exception $e) => report($e),
        );
    }
}
