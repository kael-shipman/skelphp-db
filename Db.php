<?php
namespace Skel;

abstract class Db extends \SQLite3 implements Interfaces\Db {
  const VERSION = 1;
  const FRAMEWORK_TABLE = 'skel';
  protected $runningVersion;

  public function __construct($filename) {
    parent::__construct($filename);
    $this->enableExceptions(true);
    $this->initializeDatabase();
    if ($this->runningVersion != static::VERSION) $this->__syncDatabase();
  }

  abstract protected function downgradeDatabase(int $targetVersion);

  protected function initializeDatabase() {
    try {
      $v = $this->querySingle('SELECT "version" FROM "'.static::FRAMEWORK_TABLE.'" ORDER BY "installed" DESC LIMIT 1');
      $this->runningVersion = $v;
    } catch (\Exception $e) {
      // If the query failed, then the database has probably not been initialized yet
      $this->exec('CREATE TABLE "'.static::FRAMEWORK_TABLE.'" ("install_date" INTEGER PRIMARY KEY NOT NULL, "version" INTEGER NOT NULL)');
      $this->__registerVersionChange(0);
    } 
  }

  protected function __registerVersionChange(int $newVersion) {
    $this->exec('INSERT INTO "'.static::FRAMEWORK_TABLE.'" ("install_date", "version") VALUES ('.((new \DateTime())->getTimestamp()).', '.$newVersion.')');
    $this->runningVersion = $newVersion;
  }

  public function setValue(string $table, string $key, $newValue) {
    //TODO: Implement setValue
  }

  public function save(string $objectName, array $data) {
    //TODO: Implement save()
  }

  protected function __syncDatabase() {
    $v = $this->runningVersion;
    for ($v; $v < static::VERSION; $v++) {
      $this->upgradeDatabase($v+1);
      $this->__registerVersionChange($v+1);
    }
    for ($v; $v > static::VERSION; $v--) {
      $this->downgradeDatabase($v-1);
      $this->__registerVersionChange($v-1);
    }
  }

  abstract protected function upgradeDatabase(int $targetVersion);
}

?>

