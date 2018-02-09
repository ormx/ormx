<?php
/**
 * Created by PhpStorm.
 * User: Xav
 * Date: 10-Apr-15
 * Time: 16:30
 */

namespace Application\Util;

use Zend\Db\Adapter\Adapter;


/**
 * Interface ObjectCollectionInterface
 * @package Application\Model
 */
interface OrmXCollectionInterface
{

    /**
     * @return string
     */
    public function getClass();

    /**
     * @param string $class
     * @return $this
     */
    public function setClass($class);

    /**
     * @return \Zend\Db\Sql\Where
     */
    public function getConditions();


    /**
     * @return boolean
     */
    public function hasConditions();

    /**
     * @return int
     */
    public function getLimit();

    /**
     * @return int
     */
    public function getOffset();

    /**
     * @return string|array
     */
    public function getOrder();

    /**
     * @param null|string $class
     * @return array
     * @throws \Exception
     */
    public function get($class = null);

    /**
     * @param null|string $class
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getInvoke($class = null);

    /**
     * @return null
     */
    public function destroy();

    /**
     * @param array $values
     */
    public function update(array $values);

    /**
     * @param array $values
     * @return bool|mixed|null|\Zend\Db\ResultSet\ResultSet
     */
    public function insert(array $values);

    /**
     * @param \Zend\Db\Sql\Where $conditions
     * @return $this
     */
    public function setConditions($conditions);

    /**
     * @return int
     */
    public function total();


    public function setAdapter(Adapter $adapter);

    /**
     * @return Adapter
     */
    public function getAdapter();

    /**
     * @param $index
     * @param null $on
     * @return mixed
     */
    public function setIndex($index, $on = null);

    /**
     * @return mixed
     */
    public function allowEmpty();

    /**
     * @param boolean $debug
     * @return OrmXCollectionInterface
     */
    public function debug($debug);
}