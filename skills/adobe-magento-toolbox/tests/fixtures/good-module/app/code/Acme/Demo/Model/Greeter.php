<?php
declare(strict_types=1);

namespace Acme\Demo\Model;

use Acme\Demo\Api\GreeterInterface;
use Psr\Log\LoggerInterface;

class Greeter implements GreeterInterface
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    public function greet(string $name): string
    {
        $this->logger->info('greet', ['name' => $name]);
        return 'Ola, ' . $name;
    }
}
