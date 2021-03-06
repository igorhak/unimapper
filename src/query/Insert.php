<?php

namespace UniMapper\Query;

use UniMapper\Mapper,
    UniMapper\Exceptions\QueryException,
    UniMapper\Reflection;

class Insert extends \UniMapper\Query
{

    /** @var array */
    private $values = [];

    public function __construct(Reflection\Entity $entityReflection, Mapper $mapper, array $data)
    {
        parent::__construct($entityReflection, $mapper);

        $class = $entityReflection->getClassName();
        $entity = new $class;
        $entity->import($data); // @todo easier validation

        $this->values = $mapper->unmapEntity($entity);
        if (empty($this->values)) {
            throw new QueryException("Nothing to insert");
        }
    }

    public function getValues()
    {
        return $this->values;
    }

    public function onExecute(\UniMapper\Mapper $mapper)
    {
        // Primary value can not be empty
        $primaryName = $this->entityReflection->getPrimaryProperty()->getMappedName();
        if (empty($this->values[$primaryName])) {
            unset($this->values[$primaryName]);
        }

        $primaryValue = $mapper->insert(
            $this->entityReflection->getMapperReflection()->getResource(),
            $this->values
        );
        if ($primaryValue === null) {
            throw new QueryException("Insert should return primary value but null given!");
        }
        return $mapper->mapValue($this->entityReflection->getPrimaryProperty(), $primaryValue);
    }

}