<?php
declare(strict_types=1);

namespace Acme\Demo\Model;

use Magento\Framework\Api\SearchCriteriaInterface;

class ItemRepository
{
    public function getList(SearchCriteriaInterface $criteria): SearchResultsInterface
    {
        throw new \LogicException('nao implementado');
    }
}
