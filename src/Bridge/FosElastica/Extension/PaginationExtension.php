<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Extension;

use ApiPlatform\Core\Metadata\Resource\Factory\ResourceMetadataFactoryInterface;
use ApiPlatform\Core\Metadata\Resource\ResourceMetadata;
use ApiPlatform\Core\Bridge\FosElastica\Paginator;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use Elastica\Query;
use FOS\ElasticaBundle\Manager\RepositoryManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
final class PaginationExtension implements QueryResultCollectionExtensionInterface
{
    private $repositoryManager;
    private $requestStack;
    private $resourceMetadataFactory;
    private $enabled;
    private $clientEnabled;
    private $clientItemsPerPage;
    private $itemsPerPage;
    private $pageParameterName;
    private $enabledParameterName;
    private $itemsPerPageParameterName;
    private $maximumItemPerPage;

    public function __construct(RepositoryManagerInterface $repositoryManager, RequestStack $requestStack, ResourceMetadataFactoryInterface $resourceMetadataFactory, bool $enabled = true, bool $clientEnabled = false, bool $clientItemsPerPage = false, int $itemsPerPage = 30, string $pageParameterName = 'page', string $enabledParameterName = 'pagination', string $itemsPerPageParameterName = 'itemsPerPage', int $maximumItemPerPage = null)
    {
        $this->repositoryManager = $repositoryManager;
        $this->requestStack = $requestStack;
        $this->resourceMetadataFactory = $resourceMetadataFactory;
        $this->enabled = $enabled;
        $this->clientEnabled = $clientEnabled;
        $this->clientItemsPerPage = $clientItemsPerPage;
        $this->itemsPerPage = $itemsPerPage;
        $this->pageParameterName = $pageParameterName;
        $this->enabledParameterName = $enabledParameterName;
        $this->itemsPerPageParameterName = $itemsPerPageParameterName;
        $this->maximumItemPerPage = $maximumItemPerPage;
    }

    /**
     * {@inheritdoc}
     */
    public function applyToCollection(QueryBuilder $queryBuilder, string $resourceClass, string $operationName = null)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
        if (!$this->isPaginationEnabled($request, $resourceMetadata, $operationName)) {
            return;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function supportsResult(string $resourceClass, string $operationName = null) : bool
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return false;
        }

        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);

        return $this->isPaginationEnabled($request, $resourceMetadata, $operationName);
    }

    /**
     * {@inheritdoc}
     */
    public function getResult(Query $query, string $resourceClass, string $operationName)
    {
        /** @var \FOS\ElasticaBundle\Repository $repository */
        $repository = $this->repositoryManager->getRepository($resourceClass);

        return new Paginator($repository->findPaginated($query, $this->getOptions($resourceClass, $operationName)));
    }

    /**
     * @param Request $request
     * @param ResourceMetadata $resourceMetadata
     * @param string|null $operationName
     * @return bool
     */
    private function isPaginationEnabled(Request $request, ResourceMetadata $resourceMetadata, string $operationName = null) : bool
    {
        $enabled = $resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_enabled', $this->enabled, true);
        $clientEnabled = $resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_client_enabled', $this->clientEnabled, true);

        if ($clientEnabled) {
            $enabled = filter_var($request->query->get($this->enabledParameterName, $enabled), FILTER_VALIDATE_BOOLEAN);
        }

        return $enabled;
    }

    /**
     * @param string $resourceClass
     * @param string $operationName
     * @return array
     */
    private function getOptions(string $resourceClass, string $operationName) : array
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [];
        }

        $resourceMetadata = $this->resourceMetadataFactory->create($resourceClass);
        if (!$this->isPaginationEnabled($request, $resourceMetadata, $operationName)) {
            return [];
        }

        $itemsPerPage = $resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_items_per_page', $this->itemsPerPage, true);
        if ($resourceMetadata->getCollectionOperationAttribute($operationName, 'pagination_client_items_per_page', $this->clientItemsPerPage, true)) {
            $itemsPerPage = (int) $request->query->get($this->itemsPerPageParameterName, $itemsPerPage);
            $itemsPerPage = (null !== $this->maximumItemPerPage && $itemsPerPage >= $this->maximumItemPerPage ? $this->maximumItemPerPage : $itemsPerPage);
        }

        return [
            'from' => ($request->query->get($this->pageParameterName, 1) - 1) * $itemsPerPage,
            'size' => $itemsPerPage,
        ];
    }
}
