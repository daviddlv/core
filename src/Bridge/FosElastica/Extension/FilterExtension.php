<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Extension;

use ApiPlatform\Core\Api\FilterCollection;
use ApiPlatform\Core\Api\FilterLocatorTrait;
use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Bridge\FosElastica\Filter\FilterInterface;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use Psr\Container\ContainerInterface;

/**
 * @author David Delevoye <daviddelevoye@gmail.com>
 */
final class FilterExtension implements QueryCollectionExtensionInterface
{
    use FilterLocatorTrait;

    private $resourceMetadataFactory;

    /**
     * @param ContainerInterface|FilterCollection $filterLocator The new filter locator or the deprecated filter collection
     */
    public function __construct(ResourceMetadataFactoryInterface $resourceMetadataFactory, $filterLocator)
    {
        $this->setFilterLocator($filterLocator);

        $this->resourceMetadataFactory = $resourceMetadataFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function applyToCollection(QueryBuilder $queryBuilder, string $resourceClass, string $operationName = null)
    {
        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
        $resourceFilters = $resourceMetadata->getCollectionOperationAttribute($operationName, 'filters', [], true);

        if (empty($resourceFilters)) {
            return;
        }

        foreach ($resourceFilters as $filterId) {
            if (!($filter = $this->getFilter($filterId)) instanceof FilterInterface) {
                continue;
            }

            $filter->apply($queryBuilder, $resourceClass, $operationName);
        }
    }
}
