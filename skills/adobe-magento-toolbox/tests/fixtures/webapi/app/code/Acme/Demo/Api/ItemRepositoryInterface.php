<?php
declare(strict_types=1);

namespace Acme\Demo\Api;

interface ItemRepositoryInterface
{
    public function getById(int $id): Data\ItemInterface;
}
