<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Filter;

use ApiPlatform\Core\Api\FilterInterface as BaseFilterInterface;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;

/**
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
interface FilterInterface extends BaseFilterInterface
{
    /**
     * @param QueryBuilder $queryBuilder
     * @param string $resourceClass
     * @param string|null $operationName
     * @return mixed
     */
    public function apply(QueryBuilder $queryBuilder, string $resourceClass, string $operationName = null);
}
