<?php

namespace ApiPlatform\Core\Bridge\FosElastica;

use ApiPlatform\Core\DataProvider\PaginatorInterface;
use Pagerfanta\Pagerfanta;

final class Paginator implements \IteratorAggregate, PaginatorInterface
{
    private $paginator;

    /**
     * @var int
     */
    private $firstResult;

    /**
     * @var int
     */
    private $maxResults;

    /**
     * @var int
     */
    private $totalItems;

    /**
     * @var \Traversable
     */
    private $iterator;

    public function __construct(Pagerfanta $paginator)
    {
        $this->paginator = $paginator;
        $this->firstResult = $paginator->getCurrentPageOffsetStart();
        $this->maxResults = $paginator->getCurrentPageOffsetEnd();
        $this->totalItems = $paginator->getNbResults();
    }

    /**
     * {@inheritdoc}
     */
    public function getCurrentPage(): float
    {
        return (float) $this->paginator->getCurrentPage();
    }

    /**
     * {@inheritdoc}
     */
    public function getLastPage(): float
    {
        return (float) $this->paginator->getNbPages();
    }

    /**
     * {@inheritdoc}
     */
    public function getItemsPerPage(): float
    {
        return (float) $this->paginator->getMaxPerPage();
    }

    /**
     * {@inheritdoc}
     */
    public function getTotalItems(): float
    {
        return (float) $this->paginator->getNbResults();
    }

    /**
     * {@inheritdoc}
     */
    public function getIterator()
    {
        if (null === $this->iterator) {
            $this->iterator = $this->paginator->getIterator();
        }

        return $this->iterator;
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        return count($this->getIterator());
    }
}
