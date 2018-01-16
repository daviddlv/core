<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Extension;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;

/**
 * @author David Delevoye <daviddelevoye@gmail.com>
 */
final class OrderExtension implements QueryCollectionExtensionInterface
{
    private $order;
    private $resourceMetadataFactory;

    public function __construct(string $order = null, ResourceMetadataFactoryInterface $resourceMetadataFactory = null)
    {
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->order = $order;
    }

    /**
     * {@inheritdoc}
     */
    public function applyToCollection(QueryBuilder $queryBuilder, string $resourceClass, string $operationName = null)
    {
        if (null !== $this->resourceMetadataFactory) {
            $defaultOrder = $this->resourceMetadataFactory->create($resourceClass)->getAttribute('order');
            if (null !== $defaultOrder) {
                foreach ($defaultOrder as $field => $order) {
                    if (is_int($field)) {
                        // Default direction
                        $field = $order;
                        $order = 'asc';
                    }

                    $queryBuilder->sort($field, $order);
                }

                return;
            }
        }

        if (null !== $this->order) {
            $queryBuilder->sort('_score', $this->order);
        }
    }
}
