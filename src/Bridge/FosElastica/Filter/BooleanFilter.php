<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Filter;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filters the collection by boolean values.
 *
 * Filters collection on equality of boolean properties. The value is specified
 * as one of ( "true" | "false" | "1" | "0" ) in the query.
 *
 * For each property passed, if the resource does not have such property or if
 * the value is not one of ( "true" | "false" | "1" | "0" ) the property is ignored.
 *
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
class BooleanFilter extends AbstractFilter
{
    /**
     * Type of booleans.
     */
    const ELASTICA_BOOLEAN_TYPES = ['bool', 'boolean'];

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
            if (!$this->isPropertyMapped($property, $resourceClass) || !$this->isBooleanField($property, $resourceClass)) {
                continue;
            }

            $description[$property] = [
                'property' => $property,
                'type' => 'bool',
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
            !$this->isBooleanField($property, $resourceClass)
        ) {
            return;
        }

        if (in_array($value, ['true', '1'], true)) {
            $value = true;
        } elseif (in_array($value, ['false', '0'], true)) {
            $value = false;
        } else {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('Invalid boolean value for "%s" property, expected one of ( "%s" )', $property, implode('" | "', [
                    'true',
                    'false',
                    '1',
                    '0',
                ]))),
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
     * Determines whether the given property refers to a boolean field.
     *
     * @param string $property
     * @param string $resourceClass
     * @return bool
     */
    protected function isBooleanField(string $property, string $resourceClass) : bool
    {
        $propertyType = $this->getPropertyType($resourceClass, $property);

        return in_array($propertyType, self::ELASTICA_BOOLEAN_TYPES);
    }
}
