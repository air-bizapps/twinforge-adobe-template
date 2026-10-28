<?php
declare(strict_types=1);

namespace Acme\Demo\Model;

class Calc
{
    public function sum(int $a, int $b): int
    {
        /** @phpstan-ignore-next-line */
        return $a + $b;
    }
}
