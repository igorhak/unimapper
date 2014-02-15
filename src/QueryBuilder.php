<?php

namespace UniMapper;

use UniMapper\Query\FindOne,
    UniMapper\Query\Count,
    UniMapper\Query\FindAll;

class QueryBuilder
{

    protected $entity;
    protected $mappers;
    protected $logger;

    public function __construct(Entity $entity, array $mappers, \UniMapper\Logger $logger = null)
    {
        $this->entity = $entity;
        $this->mappers = $mappers;
        $this->logger = $logger;
    }

    protected function logQuery(\UniMapper\Query $query)
    {
        if ($this->logger) {
            $this->logger->logQuery($query);
        }
    }

    /**
     * Count
     *
     * @return \UniMapper\Query\Countl
     */
    public function count()
    {
        $query = new Count($this->entity, $this->mappers);
        $this->logQuery($query);
        return $query;
    }

    /**
     * Find all records
     *
     * @return \UniMapper\Query\FindAll
     */
    public function findAll()
    {
        $query = new FindAll($this->entity, $this->mappers, func_get_args());
        $this->logQuery($query);
        return $query;
    }

    /**
     * Find single record
     *
     * @param mixed $primaryValue Primary property value
     *
     * @return \UniMapper\Query\FindOne
     */
    public function findOne($primaryValue)
    {
        $query = new FindOne($this->entity, $this->mappers, $primaryValue);
        $this->logQuery($query);
        return $query;
    }

}