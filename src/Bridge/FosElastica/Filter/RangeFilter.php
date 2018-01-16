<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Filter;

use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Filters the collection by range.
 *
 * @author David DELEVOYE <daviddelevoye@gamil.com>
 */
class RangeFilter extends AbstractFilter
{
    const PARAMETER_BETWEEN = 'between';
    const PARAMETER_GREATER_THAN = 'gt';
    const PARAMETER_GREATER_THAN_OR_EQUAL = 'gte';
    const PARAMETER_LESS_THAN = 'lt';
    const PARAMETER_LESS_THAN_OR_EQUAL = 'lte';

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
            if (!$this->isPropertyMapped($property, $resourceClass)) {
                continue;
            }

            $description += $this->getFilterDescription($property, self::PARAMETER_BETWEEN);
            $description += $this->getFilterDescription($property, self::PARAMETER_GREATER_THAN);
            $description += $this->getFilterDescription($property, self::PARAMETER_GREATER_THAN_OR_EQUAL);
            $description += $this->getFilterDescription($property, self::PARAMETER_LESS_THAN);
            $description += $this->getFilterDescription($property, self::PARAMETER_LESS_THAN_OR_EQUAL);
        }

        return $description;
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $values, QueryBuilder $queryBuilder, string $filter, string $resourceClass, string $operationName = null)
    {
        if (
            !is_array($values) ||
            !$this->isPropertyEnabled($property) ||
            !$this->isPropertyMapped($property, $resourceClass)
        ) {
            return;
        }

        foreach ($values as $operator => $value) {
            $this->addMust($queryBuilder, $property, $operator, $value);
        }
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param $property
     * @param $operator
     * @param $value
     */
    protected function addMust(QueryBuilder $queryBuilder, $property, $operator, $value)
    {
        switch ($operator) {
            case self::PARAMETER_BETWEEN:
                $rangeValue = explode('..', $value);

                if (2 !== count($rangeValue)) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid format for "[%s]", expected "<min>..<max>"', $operator)),
                    ]);

                    return;
                }

                if (!is_numeric($rangeValue[0]) || !is_numeric($rangeValue[1])) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid values for "[%s]" range, expected numbers', $operator)),
                    ]);

                    return;
                }

                $queryBuilder->query($queryBuilder->getRangeQuery($property, [
                    self::PARAMETER_GREATER_THAN_OR_EQUAL => $rangeValue[0],
                    self::PARAMETER_LESS_THAN_OR_EQUAL => $rangeValue[1]
                ]));
                break;

            case self::PARAMETER_GREATER_THAN:
            case self::PARAMETER_GREATER_THAN_OR_EQUAL:
            case self::PARAMETER_LESS_THAN:
            case self::PARAMETER_LESS_THAN_OR_EQUAL:
                if (!is_numeric($value)) {
                    $this->logger->notice('Invalid filter ignored', [
                        'exception' => new InvalidArgumentException(sprintf('Invalid value for "[%s]", expected number', $operator)),
                    ]);

                    return;
                }

                $queryBuilder->query($queryBuilder->getRangeQuery($property, [$operator => $value]));
                break;
        }
    }

    /**
     * Gets filter description.
     *
     * @param string $fieldName
     * @param string $operator
     *
     * @return array
     */
    protected function getFilterDescription(string $fieldName, string $operator) : array
    {
        return [
            sprintf('%s[%s]', $fieldName, $operator) => [
                'property' => $fieldName,
                'type' => 'string',
                'required' => false,
            ],
        ];
    }
}
