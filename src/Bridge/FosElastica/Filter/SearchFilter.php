<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Filter;

use ApiPlatform\Core\Api\IriConverterInterface;
use ApiPlatform\Core\Exception\InvalidArgumentException;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Filter the collection by given properties.
 *
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
class SearchFilter extends AbstractFilter
{
    /**
     * @var string Exact matching.
     */
    const STRATEGY_EXACT = 'exact';

    /**
     * @var string The value must be contained in the field.
     */
    const STRATEGY_PARTIAL = 'partial';

    /**
     * @var string Finds fields that are starting with the value.
     */
    const STRATEGY_START = 'start';

    /**
     * @var string Finds fields that are ending with the value.
     */
    const STRATEGY_END = 'end';

    /**
     * @var string Finds fields that are starting with the word.
     */
    const STRATEGY_WORD_START = 'word_start';

    protected $propertyAccessor;

    public function __construct(ConfigManager $configManager, RequestStack $requestStack, PropertyAccessorInterface $propertyAccessor = null, LoggerInterface $logger = null, array $properties = null)
    {
        parent::__construct($configManager, $requestStack, $logger, $properties);

        $this->propertyAccessor = $propertyAccessor ?: PropertyAccess::createPropertyAccessor();
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

        foreach ($properties as $property => $strategy) {
            if (!$this->isPropertyMapped($property, $resourceClass, true)) {
                continue;
            }

            $typeOfField = $this->getType($property);
            $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;
            $filterParameterNames = [$property];

            if (self::STRATEGY_EXACT === $strategy) {
                $filterParameterNames[] = $property.'[]';
            }

            foreach ($filterParameterNames as $filterParameterName) {
                $description[$filterParameterName] = [
                    'property' => $property,
                    'type' => $typeOfField,
                    'required' => false,
                    'strategy' => $strategy,
                ];
            }
        }

        return $description;
    }

    /**
     * Converts a Doctrine type in PHP type.
     *
     * @param string $doctrineType
     *
     * @return string
     */
    private function getType(string $doctrineType) : string
    {
        return 'string';
    }

    /**
     * {@inheritdoc}
     */
    protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, string $filter, string $resourceClass, string $operationName = null)
    {
        if (
            !$this->isPropertyEnabled($property) ||
            !$this->isPropertyMapped($property, $resourceClass, true) ||
            null === $value
        ) {
            return;
        }

        $values = $this->normalizeValues((array) $value);

        if (empty($values)) {
            $this->logger->notice('Invalid filter ignored', [
                'exception' => new InvalidArgumentException(sprintf('At least one value is required, multiple values should be in "%1$s[]=firstvalue&%1$s[]=secondvalue" format', $property)),
            ]);

            return;
        }

        $caseSensitive = true;

        if ('id' === $property) {
            $values = array_map([$this, 'getIdFromValue'], $values);
        }

        $strategy = $this->properties[$property] ?? self::STRATEGY_EXACT;

        // prefixing the strategy with i makes it case insensitive
        if (strpos($strategy, 'i') === 0) {
            $strategy = substr($strategy, 1);
            $caseSensitive = false;
        }

        $this->addMust($queryBuilder, $strategy, $property, $values, $resourceClass);
    }

    /**
     * Adds where clause according to the strategy.
     *
     * @param QueryBuilder $queryBuilder
     * @param string $strategy
     * @param string $property
     * @param mixed $value
     * @param string $resourceClass
     * @throws InvalidArgumentException If strategy does not exist
     */
    protected function addMust(QueryBuilder $queryBuilder, string $strategy, string $property, $value, string $resourceClass)
    {
        if (is_array($value) && count($value) === 1) {
            $value = $value[0];
        }

        $isParent = false;
        if ($this->isPropertyParent($property)) {
            $propertyParts = $this->splitPropertyParts($property, '_');
            $property = $propertyParts['property'];
            $isParent = true;
        }

        $parentType = $isParent ? $this->getParentType($resourceClass) : null;

        switch ($strategy) {
            case null:
            case self::STRATEGY_EXACT:
                $queryBuilder->query($queryBuilder->getTermQuery($property, $value, $parentType));
                break;

            case self::STRATEGY_PARTIAL:
                $queryBuilder->query($queryBuilder->getMatchQuery($property, $value, $parentType));
                break;

            case self::STRATEGY_START:
                //$bool->addMust(new Query\Prefix([$property => $value]));
                break;

            case self::STRATEGY_END:
                //$bool->addMust(new Query\Regexp([$property => '.*'.$value]));
                break;

            case self::STRATEGY_WORD_START:
                //$bool->addMust(new Query\MatchPhrasePrefix([$property => $value]));
                break;

            default:
                throw new InvalidArgumentException(sprintf('strategy %s does not exist.', $strategy));
        }
    }

    /**
     * Gets the ID from an IRI or a raw ID.
     *
     * @param string $value
     * @return mixed
     */
    protected function getIdFromValue(string $value)
    {
        try {
            //if ($item = $this->iriConverter->getItemFromIri($value)) {
                //return $this->propertyAccessor->getValue($item, 'id');
            //}
        } catch (InvalidArgumentException $e) {
            // Do nothing, return the raw value
        }

        return $value;
    }

    /**
     * Normalize the values array.
     *
     * @param array $values
     * @return array
     */
    protected function normalizeValues(array $values) : array
    {
        foreach ($values as $key => $value) {
            if (!is_int($key) || !is_string($value)) {
                unset($values[$key]);
            }
        }

        return array_values($values);
    }
}
