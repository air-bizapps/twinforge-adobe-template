<?php
/**
 * Reference module of the adobe-magento-toolbox skill: copy the pattern, rename Acme\Reference.
 */
declare(strict_types=1);

namespace Acme\Reference\Model;

use Acme\Reference\Api\Data\ItemSearchResultsInterface;
use Magento\Framework\Api\SearchResults;

/**
 * Implementation of the search results contract (preference in etc/di.xml).
 */
class ItemSearchResults extends SearchResults implements ItemSearchResultsInterface
{
}
