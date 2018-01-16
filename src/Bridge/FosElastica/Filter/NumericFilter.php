<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Filter;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filters the collection by numeric values.
 *
 * Filters collection by equality of numeric properties.
 *
 * For each property passed, if the resource does not have such property or if
 * the value is not numeric, the property is ignored.
 *
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
class NumericFilter extends AbstractFilter
{
    /**
     * Type of numeric.
     */
    const ELASTICA_NUMERIC_TYPES = ['int', 'integer', 'float'];

    public function __construct(ConfigManager $configManager, RequestStack $requestStack, LoggerInterface $logger = null, array $properties = null)
    {
        parent::__construct($configManager, $requestStack, $logger, $properties);
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

        foreach ($properties as $property => $unused) {
            if (!$this->isPropertyMapped($property, $resourceClass) || !$this->isNumericField($property, $resourceClass)) {
                continue;
            }

            $description[$property] = [
                'property' => $property,
                'type' => 'int', // @todo change this
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
        if (
            !$this->isPropertyEnabled($property) ||
            !$this->isPropertyMapped($property, $resourceClass) ||
            !$this->isNumericField($property, $resourceClass)
        ) {
            return;
        }

        if (!is_numeric($value)) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid numeric value for "%s" property', $property)),
            ]);

            return;
        }

        if ($filter === AbstractFilter::FILTER_POST_FILTER) {
            $queryBuilder->filter($queryBuilder->getTermQuery($property, $value), QueryBuilder::FILTER);
        } else {
            $queryBuilder->query($queryBuilder->getTermQuery($property, $value), QueryBuilder::FILTER);
        }
    }

    /**
     * Determines whether the given property refers to a numeric field.
     *
     * @param string $property
     * @param string $resourceClass
     * @return bool
     */
    protected function isNumericField(string $property, string $resourceClass) : bool
    {
        $propertyType = $this->getPropertyType($resourceClass, $property);

        return in_array($propertyType, self::ELASTICA_NUMERIC_TYPES);
    }
}
