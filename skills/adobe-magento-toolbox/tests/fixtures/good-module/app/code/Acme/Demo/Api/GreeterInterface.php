<?php
declare(strict_types=1);

namespace Acme\Demo\Api;

interface GreeterInterface
{
    /**
     * @param string $name
     * @return string
     */
    public function greet(string $name): string;
}
