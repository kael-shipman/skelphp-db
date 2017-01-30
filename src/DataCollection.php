<?php
namespace Skel;

class DataCollection extends ComponentCollection implements Interfaces\DataCollection {
  protected $linkTableName;
  protected $parentLinkKey;
  protected $childTableName;
  protected $childLinkKey;

  public function getLinkTableName() { return $this->linkTableName; }
  public function getParentLinkKey() { return $this->parentLinkKey; }
  public function getChildTableName() { return $this->childTableName; }
  public function getChildLinkKey() { return $this->childLinkKey; }

  public function setLinkTableName(string $name) { $this->linkTableName = $name; return $this; }
  public function setParentLinkKey(string $name) { $this->parentLinkKey = $name; return $this; }
  public function setChildTableName(string $name) { $this->childTableName = $name; return $this; }
  public function setChildLinkKey(string $name) { $this->childLinkKey = $name; return $this; }

  public function indexOf(Interfaces\Component $c) {
    foreach($this as $k => $colComp) {
      if ($colComp['id'] && $c['id']) {
        if ($colComp['id'] == $c['id']) return $k;
      } else {
        if ($colComp == $c) return $k;
      }
    }
    return null;
  }
}

