<?php
/*+***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 *************************************************************************************/

class Vtiger_GuardedArray implements \ArrayAccess, \Iterator {
    private $data;

    function __construct($data = null) {
        $this->data = is_null($data) || $data === false || $data === '' ? array() : $data;
    }

    #[\ReturnTypeWillChange]
    function offsetExists($key) {
        return isset($this->data[$key]) && array_key_exists($key, $this->data);
    }

    #[\ReturnTypeWillChange]
    function offsetGet($key) {
        if ($this->offsetExists($key)) {
            return $this->data[$key];
        }
        return null;
    }

    #[\ReturnTypeWillChange]
    function offsetSet($key, $value) {
        $this->data[$key] = $value;
    }

    #[\ReturnTypeWillChange]
    function offsetUnset($key) {
        unset($this->data[$key]);
    }

    // Iterator methods
    #[\ReturnTypeWillChange]
    public function rewind() {
        $this->keys = array_keys($this->data);
        $this->position = 0;
    }

    #[\ReturnTypeWillChange]
    public function current() {
        if (!$this->valid()) {
            return null;
        }
        $key = $this->keys[$this->position];
        return $this->data[$key];
    }

    #[\ReturnTypeWillChange]
    public function key() {
        if (!$this->valid()) {
            return null;
        }
        return $this->keys[$this->position];
    }

    #[\ReturnTypeWillChange]
    public function next() {
        ++$this->position;
    }

    #[\ReturnTypeWillChange]
    public function valid() {
        return isset($this->keys[$this->position]);
    }

}
