<?php

class JSXElement {
    private $tag;
    private $selfClosing;
    private $attributes;
    private $children;

    function __construct($tag, $selfClosing, $attributes, $children) {
        $this->tag = $tag;
        $this->selfClosing = $selfClosing;
        $this->attributes = $attributes;
        $this->children = $children;
    }

    function preRender() {
        print "<" . $this->tag . " ";

        $attrList = array();
        foreach ($this->attributes as $key=>$value) {
            $attrList[] = "$key=\"$value\"";
        }
        print join(" ", $attrList) . " ";
        if ($this->selfClosing) {
            print "/";
        }

        print ">";
    }

    function getChildren() {
        return $this->children;
    }

    function postRender() {
        if ($this->selfClosing) {
            return;
        }

        print "</" . $this->tag . ">";
    }
}

?>