<?php
abstract class TwitterModel extends CModel {
    private $data;
    protected $properties = array();

    static public function factory(array $json, $class, $scenario = '') {
        $obj = new $class($scenario);
        $obj->data = $json;
        //TODO: ここでイベント起こす？
        return $obj;
    }

    public function __construct($scenario = '') {
        $this->setScenario($scenario);
        $this->init();
        $this->attachBehaviors($this->behaviors());
        $this->afterConstruct();
    }

    public function init() {
    }

    // FIXME
    public function attributeNames() {
        return array();
    }

    public function __get($name) {
        $name_ = strtolower($name);
        foreach($this->properties as $propnames) {
            foreach($propnames as $propname) {
                if($name_ === strtolower($propname)) {
                    return call_user_func(array($this, 'get' . str_replace('_', '', $propname)));
                }
            }
        }
        return parent::__get($name);
    }

    public function __call($name, array $args) {
        if(preg_match('/^get(.+)$/', strtolower($name), $match)) {
            foreach($this->properties as $proptype => $propnames) {
                foreach($propnames as $propname) {
                    if(str_replace('_', '', strtolower($propname)) === $match[1]) {
                        switch($proptype) {
                        case 'id':   return $this->fetchString($propname . '_str'); // id の代わりに id_str を使う
                        case 'int':  return $this->fetchInteger($propname);
                        case 'bool': return $this->fetchBoolean($propname);
                        case 'str':  return $this->fetchString($propname);
                        case 'time': return $this->fetchDatetime($propname);
                        default: throw new CException('BUG: Unknown property type: ' . $proptype);
                        }
                    }
                }
            }
        }
        return parent::__call($name, $args);
    }

    protected function fetchString($key) {
        return $this->fetch($key, function($v){ return Normalizer::normalize((string)$v, Normalizer::FORM_C); });
    }

    protected function fetchInteger($key) {
        return $this->fetch($key, function($v) { return (int)$v; });
    }

    protected function fetchBoolean($key) {
        return $this->fetch($key, function($v) { return (bool)$v; });
    }

    protected function fetchDatetime($key) {
        if(is_null($str = $this->fetchString($key))) {
            return null;
        }
        if(!$ts = @strtotime($str)) { //FIXME
            return null;
        }
        return new Zend_Date($ts);
    }

    protected function fetchObject($key, $class, $create_if_empty = false) {
        if(!is_array($data = $this->fetch($key))) {
            if(!$create_if_empty) {
                return null;
            }
            $data = array();
        }
        return call_user_func(array($class, 'factory'), $data, $class);
    }

    protected function fetchArrayOfObject($key, $class) {
        if(!is_array($data = $this->fetch($key))) {
            return null;
        }
        $ret = array();
        foreach($data as $datum) {
            if(!is_array($datum)) {
                return null;
            }
            $ret[] = call_user_func(array($class, 'factory'), $datum, $class);
        }
        return $ret;
    }

    private function fetch($key, $filter = null) {
        if(!isset($this->data[$key])) {
            return null;
        }
        return is_callable($filter) ? call_user_func($filter, $this->data[$key]) : $this->data[$key];
    }
}
