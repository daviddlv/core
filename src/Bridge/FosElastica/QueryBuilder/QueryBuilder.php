<?php

namespace ApiPlatform\Core\Bridge\FosElastica\QueryBuilder;

use Elastica\Aggregation\AbstractAggregation;
use Elastica\Aggregation\Children;
use Elastica\Aggregation\Filter;
use Elastica\Aggregation\Terms;
use Elastica\Query;

class QueryBuilder
{
    const MUST = 1;
    const MUST_NOT = 2;
    const SHOULD = 3;
    const FILTER = 4;

    private $_query;

    private $_queryParts = array(
        'query'         => array(),
        'postFilter'    => array(),
        'aggs'          => array()
    );

    public function __construct()
    {
        $this->_query = new Query();
    }

    public function getQuery()
    {
        $query = $this->buildFilters($this->_queryParts['query']);
        if ($query) {
            $this->_query->setQuery($query);
        } else {
            $this->_query->setQuery(new Query\MatchAll());
        }

        $postFilter = $this->buildFilters($this->_queryParts['postFilter']);
        if ($postFilter) {
            $this->_query->setPostFilter($postFilter);
        }

        $aggregations = $this->buildAggs($this->_queryParts['aggs']);
        foreach ($aggregations as $aggregation) {
            $this->_query->addAggregation($aggregation);
        }

        return $this->_query;
    }

    public function query(Query\AbstractQuery $query, $operator = QueryBuilder::MUST, $key = null)
    {
        return $this->add('query', [$operator => $query], $key);
    }

    public function filter(Query\AbstractQuery $query, $operator = QueryBuilder::MUST, $key = null)
    {
        return $this->add('postFilter', [$operator => $query], $key);
    }

    public function sort($field, $order = 'asc')
    {
        return $this->_query->addSort([$field => ['order' => strtolower($order)]]);
    }

    public function from($from = 0)
    {
        return $this->_query->setFrom($from);
    }

    public function limit($limit)
    {
        return $this->_query->setSize($limit);
    }

    public function getTermQuery($field, $value, string $parentType = null)
    {
        if (is_array($value)) {
            return new Query\Terms($field, $value);
        }

        $query = new Query\Term([$field => $value]);

        if ($parentType !== null) {
            return $this->getHasParentQuery($query, $parentType);
        }

        return $query;
    }

    public function getExistsQuery($field, string $parentType = null)
    {
        $query = new Query\Exists($field);

        if ($parentType !== null) {
            return $this->getHasParentQuery($query, $parentType);
        }

        return $query;
    }

    public function getRangeQuery($field = null, array $args = [], string $parentType = null)
    {
        $query = new Query\Range($field, $args);

        if ($parentType !== null) {
            return $this->getHasParentQuery($query, $parentType);
        }

        return $query;
    }

    public function getMatchQuery($field, $value, string $parentType = null)
    {
        $query = new Query\Match($field, $value);

        if ($parentType !== null) {
            return $this->getHasParentQuery($query, $parentType);
        }

        return $query;
    }

    public function getPrefixQuery(array $prefix = [], string $parentType = null)
    {
        $query = new Query\Prefix($prefix);

        if ($parentType !== null) {
            return $this->getHasParentQuery($query, $parentType);
        }

        return $query;
    }

    public function getHasParentQuery(Query\AbstractQuery $query, $type)
    {
        return new Query\HasParent($query, $type);
    }

    public function aggregate(AbstractAggregation $aggregation, $parentName = null)
    {
        $key = $aggregation->getName();

        if ($parentName) {
            if (strpos($parentName, '.') !== false) {
                $parentKeys = explode('.', $parentName);
            } else {
                $parentKeys = [$parentName];
            }

            $array = [];
            $arr = &$array;
            foreach ($parentKeys as $i => $parentKey) {
                $arr[$parentKey] = [];
                $arr = &$arr[$parentKey]['_children'];

                if ($parentKeys[$i] === $parentKeys[count($parentKeys) - 1]) {
                    $arr = [$key => ['_aggregations' => [$aggregation]]];;
                }
            }
            unset($arr);
        } else {
            $array = [$key => ['_aggregations' => [$aggregation]]];
        }

        return $this->add('aggs', $array, $key);
    }

    public function getTermsAggregation($name, $field)
    {
        $aggregation = new Terms($name);
        $aggregation->setField($field);

        return $aggregation;
    }

    public function getFilterAggregation($name, Query\AbstractQuery $filter)
    {
        $aggregation = new Filter($name);
        $aggregation->setFilter($filter);

        return $aggregation;
    }

    public function getChildrenAggregation($name, $type)
    {
        $aggregation = new Children($name);
        $aggregation->setType($type);

        return $aggregation;
    }

    private function add($partName, $part, $partKey = null)
    {
        if ($partName !== 'aggs') {
            $operator = QueryBuilder::MUST;

            if (is_array($part)) {
                $operator = key($part);
                $part = current($part);
            }

            $this->_queryParts[$partName][$operator][$partKey] = $part;
        } else {
            $this->_queryParts[$partName] = array_merge_recursive($this->_queryParts[$partName], $part);
        }

        return $this;
    }

    private function buildFilters(array $filters, $exceptIndex = null)
    {
        if (empty($filters)) {
            return null;
        }

        $boolQuery = new Query\BoolQuery();
        $hasFilter = false;

        foreach ($filters as $operator => $parts) {
            foreach ($parts as $index => $part) {
                if ($exceptIndex && $exceptIndex === $index) {
                    continue;
                }

                switch ($operator) {
                    case QueryBuilder::MUST:
                        $boolQuery->addMust($part);
                        break;
                    case QueryBuilder::MUST_NOT:
                        $boolQuery->addMustNot($part);
                        break;
                    case QueryBuilder::SHOULD:
                        $boolQuery->addShould($part);
                        break;
                    case QueryBuilder::FILTER:
                        $boolQuery->addFilter($part);
                        break;
                }
                $hasFilter = true;
            }
        }

        if ($hasFilter) {
            return $boolQuery;
        }

        return null;
    }

    private function buildAggs(array $aggregations, AbstractAggregation $parentAggregation = null)
    {
        if (empty($aggregations)) {
            return [];
        }

        $rootAggregations = [];
        $parentAggregations = [];

        foreach ($aggregations as $key => $aggregation) {
            if (isset($aggregation['_aggregations'])) {
                foreach ($aggregation['_aggregations'] as $rootAggregation) {
                    $rootAggregations[] = $rootAggregation;
                    if (isset($aggregation['_children'])) {
                        $parentAggregations[] = $rootAggregation;
                    }
                }
            }
        }

        foreach ($aggregations as $key => $aggregation) {
            if (isset($aggregation['_children'])) {
                foreach ($parentAggregations as $rootAggregation) {
                    $childrenAggregations = $this->buildAggs($aggregation['_children'], $rootAggregation);
                    foreach ($childrenAggregations as $childAggregation) {
                        if ($parentAggregation) {
                            $parentAggregation->addAggregation($childAggregation);
                        }
                    }
                }
            }
        }

        return $rootAggregations;
    }
}