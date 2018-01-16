<?php

namespace ApiPlatform\Core\Bridge\FosElastica;

use ApiPlatform\Core\Bridge\FosElastica\Extension\QueryResultCollectionExtensionInterface;
use ApiPlatform\Core\DataProvider\CollectionDataProviderInterface;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;

/**
 * Collection data provider for the Elastica library.
 *
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
class CollectionDataProvider implements CollectionDataProviderInterface
{
    private $configManager;
    private $repositoryManager;
    private $collectionExtensions;

    /**
     * CollectionDataProvider constructor.
     *
     * @param ConfigManager $configManager
     * @param RepositoryManagerInterface $repositoryManager
     * @param array $collectionExtensions
     */
    public function __construct(ConfigManager $configManager, RepositoryManagerInterface $repositoryManager, array $collectionExtensions = [])
    {
        $this->configManager = $configManager;
        $this->repositoryManager = $repositoryManager;
        $this->collectionExtensions = $collectionExtensions;
    }

    /**
     * {@inheritdoc}
     */
    public function getCollection(string $resourceClass, string $operationName = null)
    {
        $typeName = $this->getElasticTypeName($resourceClass);
        if (null === $typeName) {
            throw new ResourceClassNotSupportedException();
        }

        /** @var \FOS\ElasticaBundle\Repository $repository */
        $repository = $this->repositoryManager->getRepository($resourceClass);

        $queryBuilder = new QueryBuilder();

        foreach ($this->collectionExtensions as $extension) {
            $extension->applyToCollection($queryBuilder, $resourceClass, $operationName);

            if ($extension instanceof QueryResultCollectionExtensionInterface && $extension->supportsResult($resourceClass, $operationName)) {
                return $extension->getResult($queryBuilder->getQuery(), $resourceClass, $operationName);
            }
        }

        return $repository->find($queryBuilder->getQuery());
    }

    /**
     * @param string $resourceClass
     * @return null|string
     */
    private function getElasticTypeName(string $resourceClass)
    {
        $indexes = $this->configManager->getIndexNames();

        foreach ($indexes as $index) {
            $indexConfig = $this->configManager->getIndexConfiguration($index);
            $types = $indexConfig->getTypes();
            foreach ($types as $typeConfig) {
                if ($typeConfig->getModel() === $resourceClass) {
                    return sprintf('%s/%s', $indexConfig->getName(), $typeConfig->getName());
                }
            }
        }

        return null;
    }
}
