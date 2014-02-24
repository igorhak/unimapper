<?php

namespace UniMapper;

use UniMapper\EntityCollection,
    UniMapper\Exceptions\PropertyTypeException,
    UniMapper\Exceptions\PropertyAccessException;

/**
 * Entity is ancestor for all ORM entities and provides global methods, which
 * can be used in every new entity object.
 */
abstract class Entity implements \JsonSerializable
{

    protected $mappers = array();
    protected $reflection;
    private $data = array();

    public function __construct(\UniMapper\Mapper $mapper = null)
    {
        if ($mapper) {
            $this->addMapper($mapper);
        }
        $this->reflection = new Reflection\EntityReflection($this);
    }

    public function addMapper(\UniMapper\Mapper $mapper)
    {
        $this->mappers[$mapper->getName()] = $mapper;
    }

    public function isActive()
    {
        return count($this->mappers) > 0;
    }

    public function getMappers()
    {
        if (!$this->isActive()) {
            throw new \Exception("Entity is not active!");
        }
        return $this->mappers;
    }

    /**
     * Get property value
     *
     * @param string $name Property name
     *
     * @return mixed
     */
    public function __get($name)
    {
        if (isset($this->data[$name])) {
            return $this->data[$name];
        }

        $properties = $this->reflection->getProperties();
        if (isset($properties[$name])) {
            return null;
        }

        throw new PropertyAccessException(
            "Undefined property with name '" . $name . "'!",
            $this->reflection
        );
    }

    /**
     * Set property value
     *
     * @param string $name  Property name
     * @param mixed  $value Property value
     *
     * @throws \UniMapper\Exceptions\PropertyAccessException
     */
    public function __set($name, $value)
    {
        $properties = $this->reflection->getProperties();
        if (!isset($properties[$name])) {
            throw new PropertyAccessException(
                "Undefined property with name '" . $name . "'!",
                $reflection
            );
        }

        // Validate value
        try {
            $properties[$name]->validateValue($value);
        } catch (PropertyTypeException $exception) {
            throw new PropertyAccessException(
                $exception->getMessage(),
                $properties[$name]->getEntityReflection(),
                $properties[$name]->getRawDefinition()
            );
        }

        // Set value
        if ($properties[$name]->getType() instanceof EntityCollection
            && gettype($value) === "array"
        ) {
            $collection = $properties[$name]->getType();
            foreach ($value as $key => $item) {
                $collection[$key] = $item;
            }
            $this->data[$name] = $collection;
        } else {
            $this->data[$name] = $value;
        }
    }

    public function __isset($name)
    {
        return isset($this->data[$name]);
    }

    public function __unset($name)
    {
        unset($this->data[$name]);
    }

    public function getReflection()
    {
        return $this->reflection;
    }

    /**
     * Get entity values as array
     *
     * @param boolean $collections Convert collections too
     *
     * @return array
     */
    final public function toArray($collections = false)
    {
        if ($collections === false) {
            return $this->data;
        }

        $output = array();
        foreach ($this->data as $name => $value) {
            if ($value instanceof \UniMapper\EntityCollection) {
                $output[$name] = $value->toArray($collections);
            } else {
                $output[$name] = $value;
            }
        }
        return $output;
    }

    /**
     * Load entity from sourceData
     *
     * @param \UniMapper\Entity $sourceData  Source data (entity) for load
     * @param array           $itemsToLoad If null, properties from annotation will be loaded
     */
    public function loadFromSource($sourceData, $itemsToLoad = null)
    {
        if ($itemsToLoad === null) {
            $itemsToLoad = array_keys($this->reflection->getProperties());
        }

        foreach ($itemsToLoad as $item) {
            if (isset($sourceData->{$item})) {
                $this->data[$item] = $sourceData->{$item};
            }
        }
    }

    /**
     * Convert to json representation of entity collection
     *
     * @return array
     */
    public function jsonSerialize()
    {
        return $this->toArray(true);
    }

    /**
     * Merge entity
     *
     * @param \UniMapper\Entity $entity
     *
     * @return \UniMapper\Entity
     */
    public function merge(\UniMapper\Entity $entity)
    {
        $entityClass = get_called_class();
        if (!$entity instanceof $entityClass) {
            throw \Exception("Merged entity must be instance of " . $entityClass . "!");
        }

        foreach ($entity as $name => $value) {
            if (!isset($this->data[$name])) {
                $this->data[$name] = $value;
            }
        }
        return $this;
    }

    /**
     * Save entity
     *
     * @return void
     */
    public function save()
    {
        if (!$this->isActive()) {
            throw new \Exception("Entity must have attached mappers!");
        }

        return new Query\Insert($this, $this->mappers);
    }

    public function delete()
    {
        if (!$this->isActive()) {
            throw new \Exception("Entity must have attached mappers!");
        }

        $primaryProperty = $this->reflection->getPrimaryProperty();
        if (!$primaryProperty) {
            throw new \Exception("No primary property defined!");
        }

        $query = new Query\Delete($this, $this->mappers);
        $query->where($primaryProperty->getName(), "=", $this->data[$primaryProperty->getName()]);
        $query->limit(1);
        return $query->execute();
    }

}