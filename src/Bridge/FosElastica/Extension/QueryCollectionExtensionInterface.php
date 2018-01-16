<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Extension;

use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;

/**
 * @author David Delevoye <daviddelevoye@gmail.com>
 */
interface QueryCollectionExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, string $resourceClass, string $operationName = null);
}