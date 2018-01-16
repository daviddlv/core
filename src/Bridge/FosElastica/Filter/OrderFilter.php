<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Filter;

use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Order the collection by given properties.
 *
 * The ordering is done in the same sequence as they are specified in the query,
 * and for each property a direction value can be specified.
 *
 * For each property passed, if the resource does not have such property or if the
 * direction value is different from "asc" or "desc" (case insensitive), the property
 * is ignored.
 *
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
class OrderFilter extends AbstractFilter
{
    /**
     * @var string Keyword used to retrieve the value.
     */
    protected $orderParameterName;

    public function __construct(ConfigManager $configManager, RequestStack $requestStack, string $orderParameterName, LoggerInterface $logger = null, array $properties = null)
    {
        parent::__construct($configManager, $requestStack, $logger, $properties);

        $this->orderParameterName = $orderParameterName;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription(string $resourceClass) : array
    {
        $description = [];

        $properties = $this->properties;
        if (null === $properties) {
            $properties = array_fill_keys(array_keys($this->getProperties($resourceClass)), null);
        }

        foreach ($properties as $property => $defaultDirection) {
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $description[sprintf('%s[%s]', $this->orderParameterName, $property)] = [
                'property' => $property,
                'type' => 'string',
                'required' => false,
            ];
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, string $filter, string $resourceClass, string $operationName = null)
    {
        if (!$this->isPropertyEnabled($property) || !$this->isPropertyMapped($property, $resourceClass)) {
            return;
        }

        if (empty($direction) && isset($this->properties[$property])) {
            // fallback to default direction
            $direction = $this->properties[$property];
        }

        $direction = strtolower($direction);
        if (!in_array($direction, ['asc', 'desc'])) {
            return;
        }

        $queryBuilder->sort($property, $direction);
    }

    /**
     * {@inheritdoc}
     */
    protected function extractProperties(Request $request) : array
    {
        return $request->query->get($this->orderParameterName, []);
    }
}
