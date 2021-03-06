<?php

namespace UniMapper;

use UniMapper\Mapper,
    UniMapper\Reflection,
    UniMapper\Query\IQuery,
    UniMapper\EntityCollection,
    UniMapper\Exceptions\QueryException;

abstract class Query implements IQuery
{

    /** @var integer */
    private $elapsed;

    /** @var mixed */
    private $result;

    /** @var array */
    protected $conditionOperators = ["=", "<", ">", "<>", ">=", "<=", "IS", "IS NOT", "!=", "LIKE", "COMPARE", "IN"];

    /** @var array */
    protected $conditions = [];

    /** @var \UniMapper\Mapper */
    protected $mapper;

    /** @var \UniMapper\Reflection\Entity */
    protected $entityReflection;

    public function __construct(Reflection\Entity $entityReflection, Mapper $mapper)
    {
        // Check if correct mapper given
        if ($entityReflection->getMapperReflection()->getName() !== $mapper->getName()) {
            throw new QueryException(
                "Mapper name '" . $entityReflection->getMapperReflection()->getName()
                . "' in query does not match with mapper name '" . $mapper->getName()
                . "' from entity " . $entityReflection->getClassName() . "!"
            );
        }

        $this->mapper = $mapper;
        $this->entityReflection = $entityReflection;
    }

    public function getResult()
    {
        return $this->result;
    }

    public function getConditions()
    {
        return $this->conditions;
    }

    public function getElapsed()
    {
        return $this->elapsed;
    }

    public function getMapper()
    {
        return $this->mapper;
    }

    public function getEntityReflection()
    {
        return $this->entityReflection;
    }

    public static function getName()
    {
        $reflection = new \ReflectionClass(get_called_class());
        return lcfirst($reflection->getShortName());
    }

    protected function addCondition($propertyName, $operator, $value, $joiner = 'AND')
    {
        if (!$this instanceof Query\IConditionable) {
            throw new QueryException("Conditions should be called only on conditionable queries!");
        }

        if (!$this->entityReflection->hasProperty($propertyName)) {
            throw new QueryException("Invalid property name '" . $propertyName . "'!");
        }

        if ($operator !== null && !in_array($operator, $this->conditionOperators)) {
            throw new QueryException("Condition operator " . $operator . " not allowed! You can use one of the following " . implode(" ", $this->conditionOperators) . ".");
        }

        $this->conditions[] = [
            $this->entityReflection->getProperty($propertyName)->getMappedName(),
            $operator,
            $value,
            $joiner
        ];
    }

    protected function addNestedConditions(\Closure $callback, $joiner = 'AND')
    {
        $query = new $this($this->entityReflection, $this->mapper);

        call_user_func($callback, $query);

        if (count($query->conditions) === 0) {
            throw new QueryException("Nested query must contain one condition at least!");
        }

        $this->conditions[] = array($query->conditions, $joiner);

        return $query;
    }

    public function where($propertyName, $operator, $value)
    {
        $this->addCondition($propertyName, $operator, $value);
        return $this;
    }

    public function whereAre(\Closure $callback)
    {
        $this->addNestedConditions($callback);
        return $this;
    }

    public function orWhereAre(\Closure $callback)
    {
        $this->addNestedConditions($callback, "OR");
        return $this;
    }

    public function orWhere($propertyName, $operator, $value)
    {
        $this->addCondition($propertyName, $operator, $value, "OR");
        return $this;
    }

    final public function execute()
    {
        $start = microtime(true);

        $this->result = $this->onExecute($this->mapper);

        // Set entities active
        if ($this->result instanceof Entity && !$this->result->isActive()) {
            $this->result->setActive($this->mapper);
        } elseif ($this->result instanceof EntityCollection) {
            foreach ($this->result as $entity) {
                $entity->setActive($this->mapper);
            }
        }

        $this->elapsed = microtime(true) - $start;
        return $this->result;
    }

    protected function getPrimaryValuesFromCollection(EntityCollection $collection)
    {
        $keys = array();

        $primaryProperty = $this->entityReflection->getPrimaryProperty();
        if ($primaryProperty === null) {
            throw new \Exception("Primary property not set in entity " . $this->entityReflection->getClassName() . "!"); // @todo remove when primary property is required
        }

        foreach ($collection as $entity) {

            if (isset($entity->{$primaryProperty->getName()})) {
                $keys[] = $entity->{$primaryProperty->getName()};
            }
        }
        return $keys;
    }

}