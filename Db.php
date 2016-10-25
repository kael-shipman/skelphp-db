<?php
namespace Skel;

abstract class Db extends \PDO implements Interfaces\Db {
  const VERSION = 1;
  const FRAMEWORK_TABLE = 'skel';
  protected $runningVersion;

  public function __construct(string $dsn, string $username=null, string $password=null, array $options=array()) {
    parent::__construct($dsn, $username, $password, $options);
    $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    $this->initializeDatabase();
    if ($this->runningVersion != static::VERSION) $this->__syncDatabase();
  }

  abstract protected function downgradeDatabase(int $targetVersion, int $fromVersion);

  protected function initializeDatabase() {
    try {
      $this->runningVersion = $this->querySingle('SELECT "version" FROM "'.static::FRAMEWORK_TABLE.'" ORDER BY "version" DESC LIMIT 1');
    } catch (\Exception $e) {
      // If the query failed, then the database has probably not been initialized yet
      $this->exec('CREATE TABLE "'.static::FRAMEWORK_TABLE.'" ("version" INTEGER PRIMARY KEY NOT NULL, "install_date" INTEGER NOT NULL)');
      $this->__registerVersionChange(0);
    } 
  }

  public function querySingle(string $query) {
    $values = func_get_args();
    array_shift($values);
    $stm = $this->prepare($query);
    $stm->execute($values);
    $res = $stm->fetch(\PDO::FETCH_ASSOC);
    if (count($res) == 1) $res = current($res);
    return $res;
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
    $this->beginTransaction();
    $v = $this->runningVersion;
    for ($v; $v < static::VERSION; $v++) {
      $this->upgradeDatabase($v+1, $v);
      $this->__registerVersionChange($v+1);
    }
    for ($v; $v > static::VERSION; $v--) {
      $this->downgradeDatabase($v-1, $v);
      $this->__registerVersionChange($v-1);
    }
    $this->commit();
  }

  abstract protected function upgradeDatabase(int $targetVersion, int $fromVersion);
}

?>

