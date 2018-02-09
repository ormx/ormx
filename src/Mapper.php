<?php
/**
 * Created by PhpStorm.
 * User: Xav
 * Date: 23-Jan-17
 * Time: 12:15
 */

namespace Application\Util;

class Mapper
{
    protected $inputMap  = [];
    protected $outputMap = [];
    protected $data      = null;
    protected $defaults  = [];
    protected $reversed  = false;
    protected $map       = [];

    /**
     * Mapper constructor.
     * @param array $inputMap
     * @param array $outputMap
     */
    public function __construct(array $inputMap = [], array $outputMap = [])
    {
        $this->setInputMap($inputMap);
        $this->setOutputMap($outputMap);
    }

    /**
     * @param array $inputMap
     * @return $this
     */
    public function setInputMap($inputMap)
    {
        $this->inputMap = $inputMap;
        $this->generateMap();

        return $this;
    }

    private function generateMap()
    {
        if (\count($this->inputMap) === \count($this->outputMap)) {
            $this->map = array_combine($this->inputMap, $this->outputMap);
        }
    }

    /**
     * @param array $outputMap
     * @return $this
     */
    public function setOutputMap($outputMap)
    {
        $this->outputMap = $outputMap;
        $this->generateMap();

        return $this;
    }

    /**
     * @param $data
     * @return mixed
     */
    public function mapOne($data)
    {
        $temp = $this->map([$data]);

        return reset($temp);
    }

    /**
     * @param null|mixed|array $data
     * @return array
     */
    public function map($data = null)
    {
        if ($data !== null) {
            $this->setData($data);
        }
        $data = $this->getData();
        $returnData = [];
        if ($data === null) {
            return $returnData;
        }

        if (!is_array($data)) {
            $data = [$data];
        }

        $map = $this->getMap();

        foreach ($data as $object) {
            $newObject = new \stdClass();
            foreach ($object as $key => $value) {
                if (isset($map[$key])) {
                    $newObject->{$map[$key]} = $value;
                }
            }

            //todo handle being reversed
            foreach ($this->defaults as $key => $value) {
                if (!isset($newObject->{$key})) {
                    if (!\is_string($value) && \is_callable($value)) {
                        $value = $value($newObject, $object);
                    }
                    $newObject->{$key} = $value;
                }
            }

            $returnData[] = $newObject;
        }

        return $returnData;
    }

    /**
     * @param $data
     * @return $this
     */
    public function setData($data)
    {
        $this->data = $data;

        return $this;
    }

    /**
     * @return null
     */
    public function getData()
    {
        return $this->data;
    }

    public function getMap()
    {
        return $this->map;
    }

    /**
     * @param array $map
     * @return Mapper
     */
    public function setMap(array $map)
    {
        $this->setInputMap(array_keys($map));
        $this->setOutputMap(array_values($map));

        return $this;
    }

    /**
     * Reverse the mapping
     * @return $this
     */
    public function reverse()
    {
        $temp = $this->getInputMap();
        $this->setInputMap($this->getOutputMap());
        $this->setOutputMap($temp);
        $this->reversed = !$this->reversed;

        return $this;
    }

    /**
     * @return array
     */
    public function getInputMap()
    {
        return $this->inputMap;
    }

    /**
     * @return array
     */
    public function getOutputMap()
    {
        return $this->outputMap;
    }

    /**
     * @param array $defaults
     * @return Mapper
     */
    public function setDefaults($defaults)
    {
        $this->defaults = $defaults;

        return $this;
    }

    /**
     * @param $name
     * @param $value
     * @return $this
     */
    public function add($name, $value)
    {
        $this->defaults[$name] = $value;

        return $this;
    }

    /**
     * @param $input
     * @param $output
     * @return $this
     */
    public function addMap($input, $output)
    {
        $this->map[$input] = $output;

        return $this;
    }


}