<?php
namespace Skel;

abstract class Db implements Interfaces\Db, Interfaces\Orm {
  use ErrorHandlerTrait;

  const VERSION = 1;
  const SCHEMA_NAME = 'skel';
  const FRAMEWORK_TABLE = 'skel';
  protected $runningVersion;
  protected $__cache = array();

  public function __construct(Interfaces\DbConfig $config) {
    $this->config = $config;
    if (!($config->getDbPdo() instanceof \PDO)) throw new InvalidConfigException("`Config::getDbPdo` MUST return a valid PDO instance.");
    $this->db = $config->getDbPdo();
    $this->initializeDatabase();
    if ($this->runningVersion != static::VERSION) $this->__syncDatabase();
  }

  abstract protected function downgradeDatabase(int $targetVersion, int $fromVersion);

  protected function getContentDir() {
    if (!$this->contentDir) $this->contentDir = new Uri('file://'.$this->config->getDbContentRoot());
    return $this->contentDir;
  }

  public function getString(string $key, string $default='') { return $this->getStrings()[$key] ?: $default; }
  public function getStrings() {
    if (!$this->strings) $this->strings = include $this->config->getDbContentRoot().'/strings.php';
    return $this->strings;
  }

  protected function initializeDatabase() {
    try {
      ($stm = $this->db->prepare('SELECT "targetVersion" FROM "'.static::FRAMEWORK_TABLE.'" WHERE "schemaName" = ? ORDER BY "installDate" DESC LIMIT 1'))->execute(array(static::SCHEMA_NAME));
      $this->runningVersion = $stm->fetchColumn(0); 
      if ($this->runningVersion === false) $this->__registerVersionChange(0,0);
    } catch (\PDOException $e) {
      // It might have failed for other reasons...
      if (strpos($e->getMessage(), 'readonly') !== false) throw $e;

      // If the query failed, then the database has probably not been initialized yet
      $this->db->exec('CREATE TABLE "'.static::FRAMEWORK_TABLE.'" ("id" INTEGER PRIMARY KEY, "schemaName" STRING NOT NULL, "targetVersion" INTEGER NOT NULL, "previousVersion" INTEGER NOT NULL, "installDate" INTEGER NOT NULL)');
      $this->db->exec('CREATE INDEX "installed_versions_index" ON "'.static::FRAMEWORK_TABLE.'" ("installDate","targetVersion")');
      $this->__registerVersionChange(0, 0);
    } 
  }

  protected function __registerVersionChange(int $newVersion, int $oldVersion) {
    $this->db->exec('INSERT INTO "'.static::FRAMEWORK_TABLE.'" ("schemaName", "installDate", "targetVersion", "previousVersion") VALUES ('.$this->db->quote(static::SCHEMA_NAME).', '.((new \DateTime())->getTimestamp()).', '.$newVersion.', '.$oldVersion.')');
    $this->runningVersion = $newVersion;
  }

  protected function __syncDatabase() {
    $this->db->beginTransaction();
    if ($this->runningVersion < static::VERSION) $this->upgradeDatabase(static::VERSION, $this->runningVersion);
    else $this->downgradeDatabase(static::VERSION, $this->runningVersion);
    $this->__registerVersionChange(static::VERSION, $this->runningVersion);
    $this->db->commit();
  }

  abstract protected function upgradeDatabase(int $targetVersion, int $fromVersion);







  // Data handling methods
  
  public function deleteObject(Interfaces\DataClass $obj) {
    if ($obj[$obj::PRIMARY_KEY] === null) return;

    $this->db->beginTransaction();
    foreach($obj as $k => $v) {
      if ($v instanceof Interfaces\DataCollection) $this->deleteAssociatedCollection($obj, $v);
    }
    $this->db->prepare('DELETE FROM "'.$obj::TABLE_NAME.'" WHERE "'.$obj::PRIMARY_KEY.'" = ?')->execute(array($obj[$obj::PRIMARY_KEY]));
    $this->db->commit();
  }

  protected function getPrimaryChanges(Interfaces\DataClass $obj) {
    $primaryFields = array();
    foreach($obj as $field => $val) {
      if ($obj->fieldIsDefined($field)) {
        if ($obj->fieldHasChanged($field)) $primaryFields[$field] = $obj->getRaw($field);
      }
    }
    return $primaryFields;
  }

  public function saveObject(Interfaces\DataClass $obj) {
    $obj->validateObject($this);
    if (($errcount = $obj->numErrors()) > 0) throw new InvalidDataObjectException("You have $errcount errors to fix: ".implode("; ", $obj->getErrors()).";");

    $primaryFields = $this->getPrimaryChanges($obj);
    if (count($primaryFields) > 0) {
      if ($id = $obj['id']) {
        $stm = $this->db->prepare('UPDATE "'.$obj::TABLE_NAME.'" SET "'.implode('" = ?, "', array_keys($primaryFields)).'" = ? WHERE "id" = ?');
        $stm->execute(array_merge(array_values($primaryFields), array($id)));
      } else {
        $placeholders = array();
        for($i = 0; $i < count($primaryFields); $i++) $placeholders[] = '?';

        $stm = $this->db->prepare('INSERT INTO "'.$obj::TABLE_NAME.'" ("'.implode('", "', array_keys($primaryFields)).'") VALUES ('.implode(',',$placeholders).')');
        $stm->execute(array_values($primaryFields));
        $id = $this->db->lastInsertId();
        $obj['id'] = $id;
      }
    }

    $this->saveExtraFields($obj);
  }

  protected function saveExtraFields(Interfaces\DataClass $obj) {
    // This method can be overridden to handle saving of nonstandard fields
    // By Default, we make sure all objects in associated collections are saved
    foreach($obj as $field => $value) {
      if ($value instanceof Interfaces\DataCollection) $this->saveAssociatedCollection($obj, $value);
    }
  }

  /**
   * AAAAAAAAAAAAAAAAAAAAAAAAAHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHHH!!!!
   */
  protected function saveAssociatedCollection(Interfaces\DataClass $obj, Interfaces\DataCollection $collection) {
    // Save any changes to each item in the collection
    foreach($collection as $c) $this->saveObject($c);
    $childPk = $collection[0] ? $collection[0]::PRIMARY_KEY : 'id';
    $parentPk = $obj::PRIMARY_KEY ?: 'id';
    if (!$collection->childLinkKey) $collection->childLinkKey = $childPk;

    if (!$collection->linkTableName || !$collection->parentLinkKey) throw new UnsaveableAssociatedCollectionException("Any DataCollection object that you wish to save must have at least a `linkTableName` and a `parentLinkKey`, and in the case of a many-to-many relationship, also a `childTableName`. These attributes should be set on the collection when it is converted from data to a collection and associated with a DataClass object. This usually happens in a Db object or derivative (like Cms).");

    // Get all items currently associated with the parent object
    $currentSelect = 'SELECT * FROM "'.$collection->linkTableName.'"'.
      ($collection->childTableName ? ' JOIN "'.$collection->childTableName.'" ON ("'.$collection->childTableName.'"."'.$childPk.'" = "'.$collection->linkTableName.'"."'.$collection->childLinkKey.'")' : '').
      ' WHERE "'.$collection->parentLinkKey.'" = ?';
    ($stm = $this->db->prepare($currentSelect))->execute(array($obj[$parentPk]));
    $current = $stm->fetchAll(\PDO::FETCH_ASSOC);

    // Delete current items that are no longer associated
    foreach($current as $v) {
      if (!$collection->contains($collection->childLinkKey, $v[$collection->childLinkKey])) {
        if ($collection->childTableName) {
          $stm = 'DELETE FROM "'.$collection->linkTableName.'" WHERE "'.$collection->parentLinkKey.'" = ? and "'.$collection->childLinkKey.'" = ?';
          $args = array($v[$collection->parentLinkKey], $v[$collection->childLinkKey]);
        } else {
          $stm = 'UPDATE "'.$collection->linkTableName.'" SET "'.$collection->parentLinkKey.'" = null WHERE "'.$collection->childLinkKey.'" = ?';
          $args = array($v[$collection->childLinkKey]);
        }
        $this->db->prepare($stm)->execute($args);
      }
    }

    // Add new items that are not already associated
    foreach($collection as $v) {
      $found = false;
      foreach($current as $c) {
        if ($c[$collection->childLinkKey] == $v[$childPk]) {
          $found = true;
          break;
        }
      }
      if ($found) continue;

      if ($collection->childTableName) {
        $stm = 'INSERT INTO "'.$collection->linkTableName.'" ("'.$collection->childLinkKey.'", "'.$collection->parentLinkKey.'") VALUES (?, ?)';
        $args = array($v[$childPk], $obj[$parentPk]);
      } else {
        $stm = 'UPDATE "'.$collection->linkTableName.'" SET "'.$collection->parentLinkKey.'" = ? WHERE "'.$childPk.'" = ?';
        $args = array($obj[$parentPk], $v[$childPk]);
      }
      $this->db->prepare($stm)->execute($args);
    }
    return true;
  }

  protected function deleteAssociatedCollection(Interfaces\DataClass $obj, Interfaces\DataCollection $collection) {
    $childPk = $collection[0] ? $collection[0]::PRIMARY_KEY : 'id';
    $parentPk = $obj::PRIMARY_KEY ?: 'id';
    foreach ($collection as $v) {
      if ($collection->childTableName) {
        $stm = 'DELETE FROM "'.$collection->linkTableName.'" WHERE "'.$collection->childLinkKey.'" = ? and "'.$collection->parentLinkKey.'" = ?';
        $this->db->prepare($stm)->execute(array($v[$childPk], $obj[$parentPk]));
      } else {
        $stm = 'UPDATE "'.$collection->linkTableName.'" SET "'.$collection->parentLinkKey.'" = null WHERE "'.$collection->childLinkKey.'" = ?';
        $this->db->prepare($stm)->execute(array($v[$childPk]));
      }
    }
  }



  protected function getCached(string $key) { return $this->__cache[$key]; }
  protected function cacheValue(string $key, $val) { return $this->__cache[$key] = $val; }
  protected function invalidateCache(string $key=null) {
    if (!$key) $this->__cache = array();
    else unset($this->__cache[$key]);
  }
}

?>

