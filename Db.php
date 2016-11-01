<?php
namespace Skel;

abstract class Db implements Interfaces\Db {
  const VERSION = 1;
  const SCHEMA_NAME = 'skel';
  const FRAMEWORK_TABLE = 'skel';
  protected $runningVersion;

  public function __construct(Interfaces\DbConfig $config) {
    $this->config = $config;
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
      $stm = $this->db->prepare('SELECT "version" FROM "'.static::FRAMEWORK_TABLE.'" WHERE "schemaName" = ? ORDER BY "version" DESC LIMIT 1');
      $this->runningVersion = $stm->execute(static::SCHEMA_NAME)->fetchColumn(0); 
      if ($this->runningVersion === false) $this->__registerVersionChange(0);
    } catch (\Exception $e) {
      // If the query failed, then the database has probably not been initialized yet
      $this->db->exec('CREATE TABLE "'.static::FRAMEWORK_TABLE.'" ("id" INTEGER PRIMARY KEY, "schemaName" STRING NOT NULL, "version" INTEGER NOT NULL, "install_date" INTEGER NOT NULL)');
      $this->__registerVersionChange(0);
    } 
  }

  protected function __registerVersionChange(int $newVersion) {
    $this->db->exec('INSERT INTO "'.static::FRAMEWORK_TABLE.'" ("schemaName", "install_date", "version") VALUES (\''.$this->db->escapeString(static::SCHEMA_NAME).'\', '.((new \DateTime())->getTimestamp()).', '.$newVersion.')');
    $this->runningVersion = $newVersion;
  }

  protected function __syncDatabase() {
    $this->db->beginTransaction();
    $v = $this->runningVersion;
    for ($v; $v < static::VERSION; $v++) {
      $this->upgradeDatabase($v+1, $v);
      $this->__registerVersionChange($v+1);
    }
    for ($v; $v > static::VERSION; $v--) {
      $this->downgradeDatabase($v-1, $v);
      $this->__registerVersionChange($v-1);
    }
    $this->db->commit();
  }

  abstract protected function upgradeDatabase(int $targetVersion, int $fromVersion);
}

?>

