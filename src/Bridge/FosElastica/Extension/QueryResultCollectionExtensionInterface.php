<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Extension;

use Elastica\Query;

/**
 * @author David Delevoye <daviddelevoye@gmail.com>
 */
interface QueryResultCollectionExtensionInterface extends QueryCollectionExtensionInterface
{
    /**
     * @param string      $resourceClass
     * @param string|null $operationName
     *
     * @return bool
     */
    public function supportsResult(string $resourceClass, string $operationName = null): bool;

    /**
     * @param Query $query
     * @param string $resourceClass
     * @param string $operationName
     *
     * @return mixed
     */
    public function getResult(Query $query, string $resourceClass, string $operationName);
}
