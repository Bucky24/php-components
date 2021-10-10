<?php

class Component {
    private $attributes;
    private $children;

    function __construct($attributes = array(), $children = array()) {
        $this->attributes = $attributes;
        $this->children = $children;
    }

    public function __get($name) {
        if ($name === "children") {
            return $this->children;
        }

        if (array_key_exists($name, $this->attributes)) {
            return $this->attributes[$name];
        }

        return null;
    }
}

?>