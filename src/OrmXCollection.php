<?php
/**
 * Created by PhpStorm.
 * User: Xav
 * Date: 02-Jun-14
 * Time: 09:29
 *
 * Returns an array of OrmXInterface Objects
 *
 */

namespace Application\Util;

use Application\Util\Exception\InvocationException;
use Application\Util\Exception\NotFoundException;
use Zend\Db\Adapter\Adapter;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\Sql\Delete;
use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Insert;
use Zend\Db\Sql\Platform\Platform;
use Zend\Db\Sql\PreparableSqlInterface;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Sql;
use Zend\Db\Sql\TableIdentifier;
use Zend\Db\Sql\Update;
use Zend\Db\Sql\Where;
use Zend\Stdlib\ArrayObject;

class OrmXCollection implements OrmXCollectionInterface
{
    protected $indexOn        = null;
    protected $class          = null;
    protected $conditions     = null;
    protected $limit          = null;
    protected $offset         = null;
    protected $order          = null;
    protected $childrenToLoad = [];
    protected $adapter        = null;
    protected $loadParent     = false;
    protected $join           = null;
    protected $withJoin       = false;
    protected $debug          = false;
    protected $index          = false;
    protected $allowEmpty     = false;

    /**
     * OrmXCollection constructor.
     * @param array $classAndOrAdapter
     * @internal param null $class
     * @internal param null|Adapter $adapter
     */
    public function __construct(...$classAndOrAdapter)
    {
        foreach ($classAndOrAdapter as $value) {
            if ($value instanceof Adapter) {
                $this->setAdapter($value);
            } else {
                $this->setClass($value);
            }
        }
    }

    /**
     * @param array ...$ids
     * @return OrmXAbstract|mixed
     * @throws \Exception
     */
    public function byId(...$ids)
    {
        $class = $this->getClass();
        /** @var OrmXAbstract $object */
        $object = new $class($this->getAdapter());
        $object->setId($object->mapKeys($ids));
        /** @var OrmXCollection $collection */
        $collection = $object->getCollection('self');
        if ($this->isWithJoin()) {
            $collection->withJoins();
        }

        $object = $collection->get()[0];
        $object->setMapping($collection->getJoinMapping());

        return $object;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param string $class
     * @return $this
     */
    public function setClass($class)
    {
        $this->class = $class;

        return $this;
    }

    /**
     * @return Adapter
     */
    public function getAdapter()
    {
        return $this->adapter;
    }

    /**
     * @param Adapter $adapter
     */
    public function setAdapter(Adapter $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * @param string $class
     * @return array
     * @throws \Exception
     */
    public function get($class = null)
    {
        return $this->fetch($class);
    }

    /**
     * @return \Zend\Db\Sql\Where;
     */
    public function getConditions()
    {
        if ($this->conditions === null) {
            $this->conditions = new Where();
        }

        return $this->conditions;
    }

    /**
     * @param \Zend\Db\Sql\Where $conditions
     * @return $this
     */
    public function setConditions($conditions)
    {
        $this->conditions = $conditions;

        return $this;
    }

    /**
     * @return string|array
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return int
     */
    public function getLimit()
    {
        return $this->limit;
    }

    /**
     * @return int
     */
    public function getOffset()
    {
        return $this->offset;
    }

    /**
     * @param null|string $class
     * @return array
     * @throws \Exception
     */
    public function getInvoke($class = null)
    {
        return $this->fetch($class, true);
    }

    /**
     *
     */
    public function destroy()
    {
        $class = $this->getClass();
        $delete = new Delete();
        /** @var OrmXAbstract $class */
        $delete->from($class::$tableName);
        $delete->where($this->getConditions());

        $this->execute($delete);
    }

    /**
     * @param array $values
     * @throws \Exception
     */
    public function update(array $values)
    {
        $class = $this->class;
        $update = new Update();
        /** @var OrmXAbstract $class */
        $update->table($class::$tableName);

        $update->where($this->getConditions());
        $update->set($values);

        $this->execute($update);
    }

    /**
     * This will not work with multi key primary keys
     * @param array $values
     * @return bool|mixed|null|\Zend\Db\ResultSet\ResultSet
     * @throws \Exception
     */
    public function insert(array $values)
    {
        /** @var OrmXAbstract $class */
        $class = $this->getClass();
        $insert = new Insert();
        $insert->into($class::$tableName);
        $newId = null;
        /** @var OrmXAbstract $class */
        $firstPrimaryKey = reset($class::$primaryKeys);

        $platform = $this->getAdapter()
                         ->getPlatform();
        /** @var Platform $platform */
        $platformName = $platform->getName();

        if ($platformName === 'PostgreSQL') {
            /** @var OrmXAbstract $class */
            /** @var OrmXAbstract $class */
            $sequence = $class::$tableName . '_' . $firstPrimaryKey . '_seq';
            $sql = 'SELECT NEXTVAL(\'"' . $sequence . '"\')';
            $result = $this->executeSQL($sql);
            $newId = $result->current()['nextval'];
            /** @var OrmXAbstract $class */
            $values[$firstPrimaryKey] = $newId;
        }

        $insert->values($values);
        $result = $this->execute($insert);

        return $newId !== null ? $newId : $result;
    }

    public function hasConditions()
    {
        return $this->conditions === null ? false : true;
    }

    /**
     * @return int
     * @throws \Exception
     */
    public function total()
    {
        $select = $this->prepareSelect();
        $select = $this->addConditions($select);

        $select->columns(['count' => new Expression('COUNT(*)')]);

//        $test = DB::getSqlString($select);

        $results = $this->execute($select);

        return (int)$results->current()->count;
    }

    /**
     * Toggles debugging
     * @param bool $debug
     * @return OrmXCollection
     */
    public function debug($debug = false)
    {
        $this->debug = $debug;

        return $this;
    }

    /**
     * @param bool $index
     * @param null $on
     * @return OrmXCollection
     */
    public function setIndex($index, $on = null)
    {
        $this->index = $index;
        $this->indexOn = $on;

        return $this;
    }

    public function allowEmpty()
    {
        return $this->setAllowEmpty(true);
    }

    /**
     * @return boolean
     */
    public function isWithJoin()
    {
        return $this->withJoin;
    }

    /**
     * @return OrmXCollection
     */
    public function withJoins()
    {
        $this->withJoin = true;

        return $this;
    }

    /**
     * @return array
     */
    public function getJoinMapping()
    {
        /** @var OrmXAbstract $class */
        $class = $this->getClass();
        $mapping = $class::$mapping;
        /** @var OrmXAbstract $joinModel */
        foreach ($class::$joins as $joinModel => $joinType) {
            $mapping = array_merge($mapping, $joinModel::$mapping);
        }

        return $mapping;
    }

    /**
     * @param null|string $class
     * @param bool $invoke
     * @return array|ArrayObject
     * @throws \Application\Util\Exception\InvocationException
     * @throws \Application\Util\Exception\NotFoundException
     * @throws \Exception
     */
    protected function fetch($class = null, $invoke = false)
    {
        $collection = [];
        if ($class !== null) {
            $this->setClass($class);
        }
        /** @var OrmXAbstract $class */
        $class = $this->getClass();
        $select = $this->prepareSelect();
        if ($this->isWithJoin()) {
            $select = $this->addJoins($select);
        }
        $select = $this->addConditions($select);

        if (isset($this->order)) {
            $select->order($this->getOrder());
        }

        //this has to be done last, because if it's oracle we have to swap out the selects and make the main select
        //a subselect
        if (isset($this->limit)) {
            $platform = $this->getAdapter()
                             ->getPlatform();
            if ($platform->getName() == 'Oracle') {
                $sql = '';
                if (!isset($this->offset)) {
                    $sql = 'SELECT * FROM (' .
                        $select->getSqlString($platform)
                        . ') WHERE ROWNUM <= ' . $this->getLimit();
                }

                //fucking oracle, have to get all above then do another sub select to trim it
                if (isset($this->offset)) {
                    $sql = 'SELECT temp.*, ROWNUM rnum FROM (' .
                        $select->getSqlString($platform)
                        . ') temp WHERE ROWNUM <= ' . (string)($this->getOffset() + $this->getLimit());

                    $sql = "SELECT * FROM ($sql) WHERE rnum > " . $this->getOffset();
                }

                $select = $sql;

            } else {
                $select->limit($this->getLimit());
                if (isset($this->offset)) {
                    $select->offset($this->getOffset());
                }
            }
        }

        if ($this->debug) {
            if ($select instanceof Select) {
                $queryString = $select->getSqlString($this->getAdapter()
                                                          ->getPlatform());
                echo "<script>console.log('$queryString')</script>";
            } else {
                echo "<script>console.log('$select')</script>";
            }
        }

        $results = $this->execute($select);

        if ($results->count() > 0) {
            /** @var ArrayObject $row */
            foreach ($results as $row) {
                if ($invoke) {
                    if ($results->count() === 1) {
                        return $row;
                    } else {
                        throw new InvocationException('Unable to bind Invoke to 1 result.', 400);
                    }
                }

                /** @var OrmXAbstract $newObject */
                $newObject = new $class();
                if ($this->isWithJoin()) {
                    $newObject->set($row, $this->getJoinMapping());
                } else {
                    $newObject->set($row);
                }
                $newObject->setAdapter($this->getAdapter());
                if (!empty($this->childrenToLoad)) {
                    foreach ($this->childrenToLoad as $name) {
                        if (\is_array($name)) {
                            foreach ($name as $child => $subChild) {
                                $newObject->getChildren($child, $subChild);
                            }
                        } else {
                            $newObject->getChildren($name);
                        }
                    }
                }

                if ($this->index) {
                    if ($this->indexOn !== null) {
                        $getter = Util::makeGetter($this->indexOn);
                        $id = $newObject->$getter();
                    } else {
                        if (\is_array($newObject->getId())) {
                            $id = implode(array_values($newObject->getId()));
                        } else {
                            $id = $newObject->getId();
                        }
                    }
                    $collection[$id] = $newObject;
                } else {
                    $collection[] = $newObject;
                }
            }
        } else {
            if ($this->isAllowEmpty() === false) {
                throw new NotFoundException('Unable to create object', 404);
            }
        }

        return $collection;
    }

    /**
     * @return Select
     */
    protected function prepareSelect()
    {
        /** @var OrmXAbstract $class */
        $class = $this->getClass();
        $select = new Select();
        $schema = ($class::$schema === '') ? null : $class::$schema;
        /** @noinspection ExceptionsAnnotatingAndHandlingInspection */
        $select->from(new TableIdentifier($class::$tableName, $schema));

        $mapping = $class::$mapping;

        $mapped = [];

        if (!empty($class::$sqlMapping)) {
            foreach ($mapping as $key => $value) {
                if (isset($class::$sqlMapping[$key])) {
                    $newValue = $class::$sqlMapping[$key];
                    $mapped[$value] = new Expression(str_replace(':?', self::atSc($class, $key), $newValue));
                } else {
                    $mapped[] = $value;
                }
            }
        } else {
            $mapped = array_values($mapping);
        }
        $select->columns($mapped);

        return $select;
    }

    /**
     * Alias for attachSchema
     *
     * @param $class
     * @param $variable
     * @return mixed
     */
    public static function atSc($class, $variable)
    {
        return static::attachSchema($class, $variable);
    }

    /**
     * @param OrmXAbstract ::class $class
     * @param string $variable
     * @return mixed
     */
    public static function attachSchema($class, $variable)
    {
        /** @var OrmXAbstract $class */
        /** @var array $mapping */

        $return = $class::$schema ? $class::$schema . '.' . $class::$tableName . '.' : '';
        $return .= $class::$mapping[$variable];

        return $return;
    }

    /**
     * @param Select $select
     * @return Select
     */
    protected function addJoins(Select $select)
    {
        /** @var OrmXAbstract $class */
        $class = $this->getClass();
        $onString = null;
        $i = 0;
        $and = null;

        /** @var OrmXAbstract $class */
        /** @var string $joinModel */
        foreach ($class::$joins as $joinModel => $joinType) {
            /** @var OrmXAbstract $class */
            /** @var array $foreignKeys */
            foreach ($class::$foreignKeys[$joinModel] as $foreignKey) {
                /** @var array $primaryKeys */
                $onString = $and . self::attachSchema($class,
                        $class::$primaryKeys[$i]) . ' = ' . self::attachSchema($joinModel, $foreignKey);
                $and = ' AND '; //if multiple it will add AND to it
                $i++;
            }
            /** @var OrmXAbstract $joinModel */
            $tableNameToJoin = $joinModel::$tableName;

            //currently only joins on 1 table
            $joinTableIdentifier = new TableIdentifier($tableNameToJoin, $joinModel::$schema);

            //remove any duplicate columns
            $columns = array_diff(array_values($joinModel::$mapping), array_values($class::$mapping));
            $select->join($joinTableIdentifier, $onString, $columns, $joinType);

        }

        return $select;
    }

    /**
     * @param Select $select
     * @return Select
     */
    protected function addConditions($select)
    {
        if (isset($this->conditions)) {
            /** @var Select $select */
            $select->where($this->getConditions());
        }

        return $select;
    }

    public function execute($query, $asArray = false)
    {
        if ($query instanceof PreparableSqlInterface) {
            $sql = new Sql($this->getAdapter());
            $statement = $sql->prepareStatementForSqlObject($query);
        } else {
            $statement = $this->getAdapter()
                              ->createStatement($query);
        }

        try {
            $results = $statement->execute();
        } catch (\Exception $e) {
            if ($this->debug) {
                echo "<script>console.log('" . $e->getCode() . '::' . $e->getMessage() . "')</script>";
            }
            throw $e;
        }

        $tempResult = [];

        if ($results->isQueryResult()) {
            $resultSet = new ResultSet();
            foreach ($results as $row) {
                $tempResult[] = $row;
            }
            $results = $tempResult;
            if ($asArray === true) {
                return $results;
            }
            $resultSet->initialize($results);

            return $resultSet;
        } elseif ($results->getGeneratedValue() > 0) {
            return $results->getGeneratedValue();
        } elseif ($results) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * @return bool
     */
    public function isAllowEmpty()
    {
        return $this->allowEmpty;
    }

    /**
     * @param string $sql
     * @return mixed
     */
    public function executeSQL($sql)
    {
        /** @var Adapter $adapater */
        $adapater = $this->getAdapter();
        $statement = $adapater->createStatement();
        $statement->prepare($sql);
        $result = $statement->execute();

        return $result;
    }

    /**
     * @param bool $allowEmpty
     * @return $this
     */
    public function setAllowEmpty($allowEmpty)
    {
        $this->allowEmpty = $allowEmpty;

        return $this;
    }

    /**
     * @param int $limit
     * @return $this
     */
    public function setLimit($limit)
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @param int $offset
     * @return $this
     */
    public function setOffset($offset)
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @param array|string $order
     * @return $this
     */
    public function setOrder($order)
    {
        $this->order = $order;

        return $this;
    }

    public function getSelect()
    {
        return $this->prepareSelect();
    }

    /**
     * @param $array
     * @return $this
     */
    public function withChildren($array)
    {
        $this->childrenToLoad = $array;

        return $this;
    }
}
