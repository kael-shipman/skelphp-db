<?php
namespace Skel;

class DataCollection extends ComponentCollection implements Interfaces\DataCollection {
  /**
   * For something like pages to tags (m2m), this might be `pagesTags`. For something like cities to citizens (m2o), it might just be `people`.
   */
  public $linkTableName;

  /**
   * When we're building a tags collection for a page, this would be `pageId`; for a list of the citizens in a given city, it would be `cityId`. It's the id column associated with the **parent** of the collection in the link table.
   */
  public $parentLinkKey;

  /**
   * For the tags collection of a page, this would be `tags`; for the citizens of a city, it's not necessary, just leave it null.
   */
  public $childTableName;

  /**
   * For the tags of a page, `tagId`; for the citizens of a city, leave it blank.
   */
  public $childLinkKey;

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

