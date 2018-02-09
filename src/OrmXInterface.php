<?php
/**
 * Created by PhpStorm.
 * User: Xavier Amella
 * Date: 14-Apr-14
 * Time: 14:12
 */

namespace Application\Util;

use Zend\Db\Adapter\Adapter;


/**
 * Interface OrmXInterface
 * @package Application\Model
 */
interface OrmXInterface
{
    /**
     * @return array
     */
    public function getId();

    /**
     * @param array|mixed $id
     * @return $this
     */
    public function setId($id);

    /**
     * @return int
     */
//    public function getParentId();

    /**
     * @param int $id
     * @return $this
     */
//    public function setParentId($id);

    /**
     * @return OrmXInterface
     */

    /**
     * @param OrmXInterface $parent
     * @return $this
     */
//    public function setParent(OrmXInterface $parent);

    /**
     * @param array|string $string $string
     * @return OrmXInterface
     */
    public function get(array $string);

    /**
     * @return mixed
     */
    public function store();

    /**
     * @return mixed
     */
    public function destroy();

    /**
     * @param OrmXInterface $child
     * @return $this
     * @throws \Exception
     */
    public function add(OrmXInterface $child);

    /**
     * @param string $name
     * @return OrmXInterface
     */
    public function getOnlyChild($name);

    /**
     * @param string $name
     * @param array|int ...$id
     * @return OrmXInterface
     */
    public function getOneChild($name, ...$id);

    /**
     * @param string $childName
     * @param string $objectVar
     * @param mixed $value
     * @return OrmXInterface
     */
    public function find($childName, $objectVar, $value);

    /**
     * Set the values of the object, with the values of the passed in object
     * @param $object
     * @param array $mapping
     * @return
     */
    public function set($object, $mapping);

    /**
     * @param $object
     * @return mixed
     */
    public function update($object);

    /**
     * @param $name
     * @return array
     */
    public function getChildren($name);

    /**
     * @param Adapter $adapter
     * @return mixed
     */
    public function setAdapter(Adapter $adapter);

    /**
     * @return Adapter
     */
    public function getAdapter();

    /**
     * @param $name
     * @return mixed
     */
    public function getValue($name);

    /**
     * @param $name
     * @param $value
     * @return mixed
     */
    public function setValue($name, $value);

    /**
     * @param $debug
     * @return OrmXAbstract
     */
    public function debug($debug);
}