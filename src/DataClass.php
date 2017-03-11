<?php
namespace Skel;

abstract class DataClass extends Component implements Interfaces\DataClass {
  use ErrorHandlerTrait;
  use ObservableTrait;

  const TABLE_NAME = null;
  const PRIMARY_KEY = 'id';

  protected $definedFields = array();
  protected $changes = array();
  protected $setBySystem = array();





  // Constructors
  public function __construct(array $elements=array(), Interfaces\Template $t=null) {
    $this->addDefinedFields(array('id'));
    $this->set('id', null, true);
    parent::__construct($elements, $t);
  }

  public function updateFromUserInput(array $data) {
    foreach($this->getDefinedFields() as $field) {
      if (!array_key_exists($field, $data)) continue;
      $this->set($field, $this->convertDataToField($field, $data[$field]), false);
    }
    return $this;
  }

  // alias to make this work with Factory class
  public static function create(array $data) {
    return static::restoreFromData($data);
  }

  public static function restoreFromData(array $data) {
    if (array_key_exists('setBySystem', $data)) {
      $setBySystem = json_decode($data['setBySystem'], true);
      unset($data['setBySystem']);
    } else {
      $setBySystem = array();
    }

    $o = new static();
    $o->updateFromUserInput($data);
    $o->setBySystem = $setBySystem;
    $o->changes = array();
    return $o;
  }







  // Data Handling

  public function get($field) {
    return $this->convertDataToField($field, $this->elements[$field]);
  }

  public function getData() {
    $data = array();
    foreach($this->getDefinedFields() as $field) {
      $data[$field] = $this->getRaw($field);
    }
    return $data;
  }

  public function getRaw(string $field) {
    if ($this->elements[$field] instanceof Interfaces\DataClass) {
      $obj = $this->elements[$field];
      return $obj[$obj::PRIMARY_KEY];
    }
    return $this->elements[$field];
  }

  public function set(string $field, $val, bool $setBySystem=false) {
    if ($val instanceof DataCollection) {
      $this->elements[$field] = $val;
      return $this;
    }

    $val = $this->typecheckAndConvertInput($field, $val);
    $prevVal = $this[$field];
    $newField = !array_key_exists($field, $this->elements);

    $this->elements[$field] = $val;
    $this->setBySystem[$field] = $setBySystem;
    $this->validateField($field);

    if ($field != 'id' && ($val != $prevVal || $newField)) {
      if (!array_key_exists($field, $this->changes)) $this->changes[$field] = array();
      $this->changes[$field][] = $prevVal;
      $this->notifyListeners('Change', array('field' => $field, 'prevVal' => $prevVal, 'newVal' => $val));
    }

    return $this;
  }







  // Utility


  
  // Public

  public function addDefinedFields(array $fields) {
    foreach($fields as $f) {
      if (array_search($f, $this->definedFields) === false) $this->definedFields[] = $f;
      $this->registerArrayKey($f);
      if (!array_key_exists($f, $this->elements)) $this->set($f, null, true);
    }
  }
  public function fieldSetBySystem(string $field) { return (bool)$this->setBySystem[$field]; }
  public function fieldHasChanged(string $field) { return array_key_exists($field, $this->changes); }
  public function fieldIsDefined(string $field) { return array_search($field, $this->definedFields) !== false; }
  public function getChanges() { return $this->changes; }
  public function getDefinedFields() { return $this->definedFields; }
  public function getFieldsSetBySystem() {
    $fields = array();
    foreach($this->setBySystem as $field => $set) {
      if ($set) $fields[] = $field;
    }
    return $fields;
  }
  public function removeDefinedFields(array $fields) {
    foreach($fields as $f) {
      while (($k = array_search($f, $this->definedFields)) !== false) unset($this->definedFields[$k]);
    }
  }




  // Internal

  protected function convertDataToField(string $field, $dataVal) {
    return $dataVal;
  }

  public static function getNormalizedClassName() {
    $str = explode('\\', static::class);
    $str = array_pop($str);
    $str = preg_replace(array('/([A-Z])/', '/_-/'), array('-\1','_'), $str);
    return trim(strtolower($str), '-');
  }

  protected function typecheckAndConvertInput(string $field, $val) {
    if ($val === null) return $val;

    if ($field == 'id') {
      if ($this->get($field) !== null) throw new InvalidDataFieldException("You can't change an id that's already been set!");
      return $val;
    }
    throw new UnknownFieldException("`$field` is not a known field for this object! All known fields must be type-checked and converted on input using the `typecheckAndConvertInput` function.");
  }




  // Overrides

  public function offsetGet($key) {
    if (array_search($key, $this->definedFields) === false) return parent::offsetGet($key);
    else return $this->get($key);
  }
  public function offsetSet($key, $val) {
    if (array_search($key, $this->definedFields) === false) return parent::offsetSet($key, $val);
    else {
      if (array_search($key, $this->keys) === false) $this->keys[] = $key;
      $this->set($key, $val);
    }
    return;
  }
  public function offsetUnset($key) {
    if (array_search($key, $this->definedFields) === false) return parent::offsetUnset($key);
    else $this->set($key, null);
    return;
  }





  // Abstract

  abstract protected function validateField(string $field);
  abstract public function validateObject(Interfaces\Db $db);
}


