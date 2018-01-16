<?php

namespace ApiPlatform\Core\Bridge\FosElastica\Filter;

use ApiPlatform\Core\Exception\PropertyNotFoundException;
use ApiPlatform\Core\Exception\ResourceClassNotSupportedException;
use ApiPlatform\Core\Util\RequestParser;
use ApiPlatform\Core\Bridge\FosElastica\QueryBuilder\QueryBuilder;
use FOS\ElasticaBundle\Configuration\ConfigManager;
use FOS\ElasticaBundle\Configuration\TypeConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @author David DELEVOYE <daviddelevoye@gmail.com>
 */
abstract class AbstractFilter implements FilterInterface
{
    const FILTER_POST_FILTER = 'filter';

    const FILTER_QUERY = 'query';

    protected $configManager;
    protected $requestStack;
    protected $logger;
    protected $properties;

    public function __construct(ConfigManager $configManager, RequestStack $requestStack, LoggerInterface $logger = null, array $properties = null)
    {
        $this->configManager = $configManager;
        $this->requestStack = $requestStack;
        $this->logger = $logger ?? new NullLogger();
        $this->properties = $properties;
    }

    /**
     * {@inheritdoc}
     */
    public function apply(QueryBuilder $queryBuilder, string $resourceClass, string $operationName = null)
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return;
        }

        foreach ($this->extractProperties($request) as $property => $value) {
            if (isset($value[AbstractFilter::FILTER_POST_FILTER])) {
                $this->filterProperty($property, $value[AbstractFilter::FILTER_POST_FILTER], $queryBuilder, AbstractFilter::FILTER_POST_FILTER, $resourceClass, $operationName);
            } elseif (isset($value[AbstractFilter::FILTER_QUERY])) {
                $this->filterProperty($property, $value[AbstractFilter::FILTER_QUERY], $queryBuilder, AbstractFilter::FILTER_QUERY, $resourceClass, $operationName);
            } else {
                $this->filterProperty($property, $value, $queryBuilder, AbstractFilter::FILTER_QUERY, $resourceClass, $operationName);
            }
        }
    }

    /**
     * Passes a property through the filter.
     *
     * @param string $property
     * @param $value
     * @param QueryBuilder $queryBuilder
     * @param string $filter
     * @param string $resourceClass
     * @param string|null $operationName
     * @return mixed
     */
    abstract protected function filterProperty(string $property, $value, QueryBuilder $queryBuilder, string $filter, string $resourceClass, string $operationName = null);

    /**
     * Gets type config for the given resource.
     *
     * @param string $typeOrResourceClass
     * @return TypeConfig
     * @throws ResourceClassNotSupportedException
     */
    protected function getTypeConfig(string $typeOrResourceClass) : TypeConfig
    {
        $indexes = $this->configManager->getIndexNames();

        foreach ($indexes as $index) {
            $indexConfig = $this->configManager->getIndexConfiguration($index);
            $types = $indexConfig->getTypes();
            foreach ($types as $typeConfig) {
                if ($typeConfig->getName() === $typeOrResourceClass || $typeConfig->getModel() === $typeOrResourceClass) {
                    return $typeConfig;
                }
            }
        }

        throw new ResourceClassNotSupportedException();
    }

    /**
     * @param string $typeOrResourceClass
     * @return array
     */
    protected function getMapping(string $typeOrResourceClass) : array
    {
        $typeConfig = $this->getTypeConfig($typeOrResourceClass);

        return $typeConfig->getMapping();
    }

    /**
     * @param string $typeOrResourceClass
     * @return array
     */
    protected function getProperties(string $typeOrResourceClass) : array
    {
        $mapping = $this->getMapping($typeOrResourceClass);

        return $mapping['properties'];
    }

    /**
     * @param string $typeOrResourceClass
     * @return string
     */
    protected function getParentType(string $typeOrResourceClass) : string
    {
        $mapping = $this->getMapping($typeOrResourceClass);

        return $mapping['_parent']['type'];
    }

    /**
     * @param string $typeOrResourceClass
     * @param string $property
     * @return array
     * @throws PropertyNotFoundException
     */
    protected function getPropertyConfig(string $typeOrResourceClass, string $property) : array
    {
        $mapping = $this->getMapping($typeOrResourceClass);

        if ($this->isPropertyNested($property)) {
            $propertyParts = $this->splitPropertyParts($property);
            foreach ($propertyParts['properties'] as $propertyPart) {
                if (isset($mapping['properties'][$propertyPart])) {
                    $mapping = $mapping['properties'][$propertyPart];
                }
            }
            $property = $propertyParts['property'];
        }

        if ($this->isPropertyParent($property) && isset($mapping['_parent'])) {
            $mapping = $this->getMapping($mapping['_parent']['type']);
            $propertyParts = $this->splitPropertyParts($property, '_');
            $property = $propertyParts['property'];
        }

        if (isset($mapping['properties'][$property])) {
            return $mapping['properties'][$property];
        }

        throw new PropertyNotFoundException();
    }

    /**
     * @param string $typeOrResourceClass
     * @param string $property
     * @return string
     */
    protected function getPropertyType(string $typeOrResourceClass, string $property) : string
    {
        $propertyConfig = $this->getPropertyConfig($typeOrResourceClass, $property);

        if (isset($propertyConfig['type'])) {
            return $propertyConfig['type'];
        }

        return 'string';
    }

    /**
     * Determines whether the given property is enabled.
     *
     * @param string $property
     * @return bool
     */
    protected function isPropertyEnabled(string $property) : bool
    {
        if (null === $this->properties) {
            // to ensure sanity, nested properties must still be explicitly enabled
            return !$this->isPropertyNested($property) || !$this->isPropertyParent($property);
        }

        return array_key_exists($property, $this->properties);
    }

    /**
     * Determines whether the given property is mapped.
     *
     * @param string $property
     * @param string $resourceClass
     * @param bool   $allowNested
     * @return bool
     */
    protected function isPropertyMapped(string $property, string $typeOrResourceClass, bool $allowNested = true, bool $allowParent = true) : bool
    {
        $properties = $this->getProperties($typeOrResourceClass);

        $isMapped = false;
        $isNested = $this->isPropertyNested($property);
        $isParent = $this->isPropertyParent($property);
        if ($isNested || $isParent) {
            try {
                $isNested = is_array($this->getPropertyConfig($typeOrResourceClass, $property));
            } catch (PropertyNotFoundException $e) {
                $isNested = false;
            }
            try {
                $isParent = is_array($this->getPropertyConfig($typeOrResourceClass, $property));
            } catch (PropertyNotFoundException $e) {
                $isParent = false;
            }
        } else {
            $isMapped = isset($properties[$property]);
        }

        return $isMapped || ($allowNested && $isNested) || ($allowParent && $isParent);
    }

    /**
     * Determines whether the given property is nested.
     *
     * @param string $property
     * @return bool
     */
    protected function isPropertyNested(string $property) : bool
    {
        return false !== strpos($property, '.');
    }

    /**
     * Determines whether the given property is nested.
     *
     * @param string $property
     * @return bool
     */
    protected function isPropertyParent(string $property) : bool
    {
        return strpos($property, '_') === 0;
    }

    /**
     * Splits the given property into parts.
     *
     * Returns an array with the following keys:
     *   - properties: array of properties according to nesting order
     *   - property: string holding the actual property (leaf node)
     *
     * @param string $property
     * @param string $delimiter
     * @return array
     */
    protected function splitPropertyParts(string $property, string $delimiter = '.') : array
    {
        $parts = explode($delimiter, $property);

        return [
            'properties' => array_slice($parts, 0, -1),
            'property' => end($parts),
        ];
    }

    /**
     * Extracts properties to filter from the request.
     *
     * @param Request $request
     * @return array
     */
    protected function extractProperties(Request $request) : array
    {
        $needsFixing = false;

        dump($this->properties);
        if (null !== $this->properties) {
            foreach ($this->properties as $property => $value) {
                if ($this->isPropertyNested($property) && $request->query->has(str_replace('.', '_', $property))) {
                    $needsFixing = true;
                }
                if ($this->isPropertyParent($property) && $request->query->has($property)) {
                    $needsFixing = true;
                }
            }
        }

        if ($needsFixing) {
            $request = RequestParser::parseAndDuplicateRequest($request);
        }

        return $request->query->all();
    }
}
