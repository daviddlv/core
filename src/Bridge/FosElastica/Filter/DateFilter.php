<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Filter;

use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filters the collection by date intervals.
 *
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
class DateFilter extends AbstractFilter
{
    const PARAMETER_BEFORE = 'before';
    const PARAMETER_AFTER = 'after';
    const EXCLUDE_NULL = 'exclude_null';
    const INCLUDE_NULL_BEFORE = 'include_null_before';
    const INCLUDE_NULL_AFTER = 'include_null_after';
    const ELASTICA_DATE_TYPES = ['date', 'datetime', 'datetimetz', 'time'];

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

        foreach ($properties as $property => $nullManagement) {
            if (!$this->isPropertyMapped($property, $resourceClass) || !$this->isDateField($property, $resourceClass)) {
                continue;
            }

            $description += $this->getFilterDescription($property, self::PARAMETER_BEFORE);
            $description += $this->getFilterDescription($property, self::PARAMETER_AFTER);
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, string $filter, string $resourceClass, string $operationName = null)
    {
        // Expect $values to be an array having the period as keys and the date value as values
        if (
            !is_array($values) ||
            !$this->isPropertyEnabled($property) ||
            !$this->isPropertyMapped($property, $resourceClass) ||
            !$this->isDateField($property, $resourceClass)
        ) {
            return;
        }

        $nullManagement = isset($this->properties[$property]) ? $this->properties[$property] : null;

        if (self::EXCLUDE_NULL === $nullManagement) {
            $query = $queryBuilder->getExistsQuery($property);
        }

        if (isset($values[self::PARAMETER_BEFORE])) {
            $query = $queryBuilder->getRangeQuery($property, ['lte' => $values[self::PARAMETER_BEFORE]]);
        }

        if (isset($values[self::PARAMETER_AFTER])) {
            $query = $queryBuilder->getRangeQuery($property, ['gte' => $values[self::PARAMETER_AFTER]]);
        }

        if (isset($query)) {
            if ($filter === AbstractFilter::FILTER_POST_FILTER) {
                $queryBuilder->filter($query);
            } else {
                $queryBuilder->query($query);
            }
        }
    }

    /**
     * Determines whether the given property refers to a date field.
     *
     * @param string $property
     * @param string $resourceClass
     * @return bool
     */
    protected function isDateField(string $property, string $resourceClass) : bool
    {
        $propertyType = $this->getPropertyType($resourceClass, $property);

        return in_array($propertyType, self::ELASTICA_DATE_TYPES);
    }

    /**
     * Gets filter description.
     *
     * @param string $property
     * @param string $period
     * @return array
     */
    protected function getFilterDescription(string $property, string $period) : array
    {
        return [
            sprintf('%s[%s]', $property, $period) => [
                'property' => $property,
                'type' => \DateTimeInterface::class,
                'required' => false,
            ],
        ];
    }
}
