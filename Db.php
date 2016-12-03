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
      ($stm = $this->db->prepare('SELECT "targetVersion" FROM "'.static::FRAMEWORK_TABLE.'" WHERE "schemaName" = ? ORDER BY "installDate" DESC LIMIT 1'))->execute(array(static::SCHEMA_NAME));
      $this->runningVersion = $stm->fetchColumn(0); 
      if ($this->runningVersion === false) $this->__registerVersionChange(0,0);
    } catch (\PDOException $e) {
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
}

?>

