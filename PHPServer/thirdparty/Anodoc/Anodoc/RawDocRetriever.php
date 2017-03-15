<?php

namespace Anodoc;

class RawDocRetriever {

	public $methodFilter = array(
	    'ext' => 'ext',
	    'log' => 'log',	
	);
	
  private
    $classDoc,
    $attrDocs,
    $methodDocs;

  function __construct($className) {
    $reflection = new \ReflectionClass($className);
    $this->classDoc = $reflection->getDocComment();
    $this->attrDocs = $this->getAttrDocs($reflection);
    $this->methodDocs = $this->getMethodDocs($reflection);
    unset($reflection);
  }

  function rawClassDoc() {
    return $this->classDoc;
  }

  function rawAttrDocs() {
    return $this->attrDocs;
  }

  function rawMethodDocs() {
    return $this->methodDocs;
  }

  private function getAttrDocs($reflection) {
    $properties = $reflection->getProperties();
    $docs = array();
    foreach ($properties as $property) {
      $docs[$property->getName()] = preg_replace(
        "/\n\s\s+/", "\n ", $property->getDocComment()
      );
    }
    return $docs;
  }

  private function getMethodDocs($reflection) {
    $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
    $docs = array();
    foreach ($methods as $method) {
      $method_name = $method->getName();
      if(isset($this->methodFilter[$method_name]))
      {
      	continue;
      }
      $docs[$method_name] = preg_replace(
        "/\n\s\s+/", "\n ", $method->getDocComment()
      );
    }
    return $docs;
  }

}