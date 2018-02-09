<?php
/**
 * Created by PhpStorm.
 * User: Xavier Amella
 * Date: 14-Apr-14
 * Time: 14:12
 */

namespace OrmX;

use OrmX\Exception\InvocationException;
use OrmX\Exception\NotFoundException;
use OrmX\Exception\NotImplementedException;
use RuntimeException;
use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Where;

abstract class OrmXAbstract implements OrmXInterface
{
    public static $tableName     = '';
    public static $schema        = '';
    public static $primaryKeys   = []; //['id', 'groupId']
    public static $foreignKeys   = []; //[Application\Model\Orders::class => ['orderId', 'customerId]]
    public static $joins         = []; //[Application\Model\OtherOrderDetails::class => 'left'] //default is INNER
    public static $childClasses  = []; //['name' => Application\Model\Orders::class]
    public static $parentClasses = []; //['orders' => Application\Model\Orders::class]
    public static $mapping       = []; // ['internalName' => 'externalName']
    public static $typeMapping   = []; //['customerId' => 'int']
    public static $sqlMapping    = null;
//    public static $validationOptionDefault = ['required' => false, 'unique' => false, 'primaryKey' => false, 'foreignKey' => false];
    protected $debug       = false;
    protected $collections = [];
    protected $adapter     = null;
    protected $variableMap = [];
//    private $internalIds = [];

    /**
     * ID's must match the declared order of the primary keys
     * OrmXAbstract constructor.
     * @param array $id
     * @throws \OrmX\Exception\InvocationException
     * @throws \Exception
     */
    public function __construct(...$id)
    {
        $lastOfArray = \end($id);
        if ($lastOfArray instanceof Adapter) {
            $this->setAdapter($lastOfArray);
            \array_pop($id);
        }

        if (empty($id)) {
            $this->reset();
        } else {
            if ($id[0] === null) {
                throw new InvocationException('Trying to invoke with null id ' . static::class);
            }

            $this->setId($this->mapKeys($id));
            $self = $this->getCollection('self');
            $this->set($self->getInvoke());
        }
    }

    public function reset()
    {
        foreach (static::$mapping as $internalName => $externalName) {
            $this->setValue($internalName, null);
        }
    }

    /**
     * @param $name
     * @param $value
     * @param null|array $mapping
     * @return $this
     * @throws \RuntimeException
     */
    public function setValue($name, $value, $mapping = null)
    {
        if ($mapping === null) {
            $mapping = static::$mapping;
        }
        if (isset($mapping[$name])) {
            $castValue = $this->cast($name, $value);
            $setter = Util::makeSetter($name);
            if ($setter !== 'setId') {
                $this->$setter($castValue);
            } else {
                $this->{$name} = $castValue;
            }
        } else {
            throw new \RuntimeException('Unknown Variable');
        }

        return $this;
    }

    /**
     * @param array|mixed $ids
     * @return $this
     * @throws \Exception
     */
    public function setId($ids)
    {
        if (\is_array($ids)) {
            foreach ($ids as $objectKey => $value) {
                $this->setValue($objectKey, $value);
            }
        } else {
            $this->setValue(static::$primaryKeys[0], $ids);
        }

        return $this;
    }

    public function getAdapter()
    {
        if ($this->adapter === null) {
            throw new RuntimeException('No Adapter Specified');
        }

        return $this->adapter;

    }

    public function setAdapter(Adapter $adapter)
    {
        $this->adapter = $adapter;

        return $this;
    }

    /**
     * @param $name
     * @return mixed
     */
    public function getValue($name)
    {
        if (isset($this->{$name})) {
            return $this->{$name};
        }

        return null;
    }

    /**
     * @param $object
     * @param array $mapping
     * @throws \RuntimeException
     * @throws \Exception
     */
    public function set($object, $mapping = [])
    {
        if (empty($mapping)) {
            if (\is_object($object)) {
                if (\is_a($object, 'ArrayObject')) {
                    /** @var array $mapping */
                    $mapping = static::$mapping;
                } else {
                    // object variable names are the same as internal names
                    $mapping = \array_combine(array_keys(static::$mapping), array_keys(static::$mapping));
                }
            } else {
                throw new \RuntimeException('Passed parameter is not an object');
            }
        }

        foreach ($mapping as $objectKey => $dataKey) {
            $valueToAssign = $object->{$dataKey};
            $this->setValue($objectKey, $valueToAssign, $mapping);
        }
    }

    /**
     * @param OrmXInterface $child
     * @return $this
     * @throws NotFoundException
     */
    public function add(OrmXInterface $child)
    {
        foreach (static::$childClasses as $objectVariable => $className) {
            if ($child instanceof $className) {
                $this->{$objectVariable}[] = $child;

                return $this;
            }
        }
        throw new NotFoundException('Unknown Child.');
    }

    /**
     * @param string $name
     * @return OrmXInterface
     * @throws \Exception
     */
    public function getOnlyChild($name)
    {
        if (!isset($this->{$name}) || \is_array($this->{$name})) {
            $this->getChildren($name);
            //assigned the only value to it's $name so it is no longer an array
            $this->{$name} = \reset($this->{$name});
        }

        return $this->{$name};
    }

    /**
     * @param $name
     * @param array $subChildren
     * @return mixed
     * @throws \Exception
     */
    public function getChildren($name, $subChildren = [])
    {
        if (!isset($this->{$name}) && empty($this->{$name})) {
            $this->{$name} = [];

            /** @var OrmXCollection $children */
            $children = $this->getCollection($name)
                             ->allowEmpty();
            if (!empty($subChildren)) {
                $children->withChildren($subChildren);
            }

            $this->{$name} = $children->get();
        }

        return $this->{$name};
    }

    /**
     * @param string $name
     * @param array ...$childId
     * @return mixed
     */
    public function getOneChild($name, ...$childId)
    {
        if (!isset($this->{$name}) && empty($this->{$name})) {
            $this->{$name} = [];
        }

        $childObjectName = static::$childClasses[$name];
        $childObject = new $childObjectName($childId, $this->getAdapter());
        /** @var OrmXInterface $childObject */
        $this->{$name}[] = $childObject;

        return $childObject;
    }

    /**
     * @param string $name
     * @param string $mappedName
     * @param mixed $value
     * @return OrmXInterface|bool
     * @throws \Exception
     */
    public function find($name, $mappedName, $value)
    {
        if (!isset($this->{$name})) {
            $this->getChildren($name);
        }

        foreach ($this->{$name} as $child) {
            /** @var OrmXInterface $child */
            if ($child->getValue($mappedName) === $value) {
                return $child;
            }
        }

        return false;
    }

    /**
     * * just update the values passed in, leave everything else alone ... not sure if this works
     * @param $object
     * @return $this
     * @throws \Exception
     */
    public function update($object)
    {
        if (!\is_object($object) || $object === null) {
            return $this;
        }

        $mapping = [];
        //we only want to update the values passed, leave everything else alone
        foreach ($this->getMapping() as $key => $databaseKey) {
            if (isset($object->{$key})) {
                $mapping[$key] = $key;
            }
        }
        $this->set($object, $mapping);

        return $this;
    }

    public function destroy()
    {
        //if there are any children, you need to destroy them first, otherwise foreign key constrains will be violated
        $this->destroyChildren();
        $destroy = $this->getCollection('destroy');
        $destroy->destroy();
        $this->reset();
    }

    /**
     *saves the object and any children to the connection
     * @throws \Exception
     * @throws \OrmX\Exception\NotImplementedException
     */
    public function store()
    {
        if (\count(static::$primaryKeys) > 1) {
            throw new NotImplementedException('This can not be used with tables with more than 1 primary key yet');
        }
        $values = [];
        foreach (static::$mapping as $objectKey => $connectionKey) {
            $values[$connectionKey] = $this->{$objectKey};
            //if it is null do not update/add it
            if ($values[$connectionKey] === null) {
                unset($values[$connectionKey]);
            }
        }
        $firstPrimaryKey = reset(static::$primaryKeys);

        if ($this->{$firstPrimaryKey} > 0) {
            $update = $this->getCollection('update');
            $update->update($values);
        } else {
            $insert = $this->getCollection('insert');
            $this->setId($insert->insert($values));
        }

        return $this;
    }

    public function getId()
    {
        $returnData = [];
        for ($i = 0, $iMax = \count(static::$primaryKeys); $i < $iMax; $i++) {
            $variableName = static::$primaryKeys[$i];
            $returnData[$variableName] = $this->getValue($variableName);
        }

        if (\count($returnData) === 1) {
            return \reset($returnData);
        }

        return $returnData;
    }

    public function debug($debug = false)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @param array $values
     * @return OrmXInterface|void
     * @throws \Exception
     */
    public function get(array $values)
    {
        $self = $this->getCollection('self');
        $where = new Where();

        $columns = [];
        foreach ($values as $key => $value) {
            $columns[static::$mapping[$key]] = $value;
        }

        foreach ($columns as $column => $value) {
            $where->and->equalTo($column, $value);
        }

        $self->setConditions($where);
        /*Replace the self collection with how this object was generated we could just use
        it off of the primary key now that we have it but lets keep it consistent and not swap
        things out*/

        $this->setCollection('self', $self);
        $this->set($self->getInvoke());
    }

    /**
     * @param $name
     * @param $value
     * @param array $mapping
     * @return mixed
     */
    protected function cast($name, $value, array $mapping = [])
    {
        if (empty($mapping)) {
            $mapping = static::$typeMapping;
        }

        //if the value is null, just explicitly return it as null
        if ($value === null && !isset($mapping[$name])) {
            return null;
        }

        //otherwise set it to it's type if it has a mapping
        if (isset($mapping[$name])) {
            $type = $mapping[$name];
            \settype($value, $type);

            return $value;
        }

        //otherwise just return the value
        return $value;
    }

    /**
     * @param array ...$ids
     * @return array
     */
    public function mapKeys($ids)
    {
        $count = \count($ids);
        $returnData = [];
        for ($i = 0; $i < $count; $i++) {
            $variableName = static::$primaryKeys[$i];
            $returnData[$variableName] = $ids[$i];
        }

        return $returnData;
    }

    /**
     * @param $name
     * @param null $childClass
     * @return OrmXCollectionInterface
     */
    public function getCollection($name, $childClass = null)
    {
        /*If we have a collection already use that otherwise generate a default one*/
        if (!isset($this->collections[$name])) {
            $collection = new OrmXCollection(static::class);
            $collection->setAdapter($this->getAdapter());

            /*generate the default self collection*/
            if ($name === 'self') {
                /** @var Where $where */
                $where = $this->getPrimaryKeyConditions();
                $collection->setConditions($where);
                $this->setCollection('self', $collection);
            }

            /*destroy collection*/
            if ($name === 'destroy') {
                $where = $this->getPrimaryKeyConditions();
                $collection->setConditions($where);
                $this->setCollection('destroy', $collection);
            }
            /*
             * destroy children collection
             * this is so that we don't have to individually destroy every child with as previous version
             * this will just remove all the specified children with 1 sql statement
            */
            if ($name === 'destroyChildren') {
                $where = $this->getForeignKeyConditions($childClass);
                $collection
                    ->setClass($childClass)
                    ->setConditions($where);
                $this->setCollection('destroyChildren', $collection);
            }

            /*insert collection*/
            if ($name === 'insert') {
                $this->setCollection('insert', $collection);
            }

            /*update collection*/
            if ($name === 'update') {
                $where = $this->getPrimaryKeyConditions();
                $collection->setConditions($where);
                $this->setCollection('update', $collection);
            }

            /*child collection*/
            if (isset(static::$childClasses[$name])) {
                $childObject = static::$childClasses[$name];
                $collection = new OrmXCollection($childObject);
                $collection->setAdapter($this->getAdapter());
                $where = $this->getForeignKeyConditions($childObject);
                $collection->setConditions($where);
                $this->setCollection($name, $collection);
            }
        }

        return $this->collections[$name];
    }

    /**
     * @return Where
     */
    protected function getPrimaryKeyConditions()
    {
        $where = new Where();
        foreach (static::$primaryKeys as $primaryKey) {
            $primaryKeyValue = $this->getValue($primaryKey);
            //only add the conditions if we have the primary key values
            if ($this->getValue($primaryKey) !== null) {
                $where->equalTo(OrmXCollection::attachSchema(static::class, $primaryKey), $primaryKeyValue);
            }
        }

        return $where;
    }

    /*setup auto setters and getters for the mapped variables
      the variables are still public and you can access them directly but
      it's best practise to use this that way you can just over ride one of these
      methods if you need to do something specific
      PSR-1 naming
   */

    public function setCollection($name, OrmXCollectionInterface $collection)
    {
        $collection->debug($this->debug);
        $this->collections[$name] = $collection;

        return $this;
    }

    protected function getForeignKeyConditions($childClass)
    {
        $where = new Where();
        /** @var OrmXAbstract $childClass */
        /** @var array $foreignKeys */
        $key = 0;
        foreach ($childClass::$foreignKeys[static::class] as $foreignKey) {
            /** @var OrmXAbstract $childClass */
            /** @var array $mapping */
            $where->equalTo(OrmXCollection::attachSchema($childClass, $foreignKey), $this->getValue(static::$primaryKeys[$key]));
            $key++;
        }

        return $where;
    }

    public function getMapping()
    {
        if (empty($this->variableMap)) {
            return static::$mapping;
        }

        return $this->variableMap;
    }

    public function destroyChildren($name = null)
    {
        if (!empty(static::$childClasses)) {
            if ($name === null) {
                foreach (static::$childClasses as $class) {
                    $destroyCollection = $this->getCollection('destroyChildren', $class);
                    $destroyCollection->destroy();
                }
            } else {
                $destroyCollection = $this->getCollection('destroyChildren', static::$childClasses[$name]);
                $destroyCollection->destroy();
            }
        }
    }

    /**
     * @param $name
     * @param null|String $on
     * @return mixed
     * @throws \Exception
     */
    public function getIndexedChildren($name, $on = null)
    {
        if (!isset($this->{$name}) && empty($this->{$name})) {
            $this->{$name} = [];

            /** @var OrmXCollection $children */
            $children = $this->getCollection($name)
                             ->allowEmpty()
                             ->setIndex(true, $on);
            $this->{$name} = $children->get();
        }

        return $this->{$name};
    }

    public function setMapping(array $mapping)
    {
        $this->variableMap = $mapping;

        return $this;
    }

    public function setChildren($name, Array $children)
    {
        $this->{$name} = $children;
    }

    /**
     * @param $name
     * @return mixed
     * @throws \Exception
     */
    public function getParent($name)
    {
        if (!isset($this->{$name})) {

            /** @var OrmXAbstract::class $parentObjectName */
            /** @var OrmXAbstract $parentObject */
            $parentObjectName = static::$parentClasses[$name];
            $parentObjectValues = [];
            foreach ($parentObjectName::$primaryKeys as $parentPrimaryKey) {
                foreach (static::$foreignKeys[$parentObjectName] as $foreignKey) {
                    $parentObjectValues[] = $this->getValue($foreignKey);
                }
            }
            $parentObject = new $parentObjectName($this->getAdapter());
            $keys = $parentObject->mapKeys($parentObjectValues);
            $parentObject->get($keys);
            $this->{$name} = $parentObject;
        }

        return $this->{$name};
    }

    /**
     * If you want to load all the children
     * @return $this
     * @throws \Exception
     */
    public function loadChildren()
    {
        foreach (static::$childClasses as $name => $class) {
            $this->getChildren($name);
        }

        return $this;
    }

    /**
     *
     * Sets up automatic getters and setters to the mapped variables
     *
     * @param string $method
     * @param array $args
     * @return mixed|$this
     * @throws \BadMethodCallException
     * @throws \Exception
     */
    public function __call($method, $args)
    {
        $type = \substr($method, 0, 3);
        $name = $this->psr2VariableName(\substr($method, 3));

        //if it's a mapped element you can get or set if it is a child, then you can't set
        //use add
        if (\array_key_exists($name, $this->getMapping())) {
            switch ($type) {
                case 'get':
                    return $this->{$name};
                case 'set':
                    $this->{$name} = $this->cast($name, $args[0]);

                    return $this;
                default:
                    throw new \BadMethodCallException($method . ' unknown variable ' . $name);
                    break;
            }
        } else {
            throw new \BadMethodCallException($method);
        }
    }

    /**
     * @param string $value name of the variable with unknown first letter capitalization eg VariableName
     * @return string  return the value with first letter as lowercase eg variableName
     * */
    private function psr2VariableName($value)
    {
        //set the first character to lowercase
        return \strtolower($value[0]) . \substr($value, 1);
    }

    /**
     * @return array
     */
    public function getCollections()
    {
        return $this->collections;
    }

    public function toArray()
    {
        $array = [];
        foreach ($this->getMapping() as $key => $value) {
            $array[$key] = $this->getValue($key);
        }

        return $array;
    }

}

