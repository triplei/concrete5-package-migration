<?php
/**
 * @author Dan Klassen <dan@triplei.ca>
 */

namespace YOUR_PACKAGE\Entities;

use Doctrine\ORM\Mapping as ORM;
use Package;
use TripleI\Libraries\BaseModel;
use TripleI\Libraries\MigrationBase;

/**
 * @ORM\Entity
 * @ORM\Table(name="TripleIMigration")
 */
class Migration extends BaseModel
{

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $filename;

    /**
     * @var bool
     * @ORM\Column(type="boolean")
     */
    protected $completed;

    /**
     * @var string
     * @ORM\Column(type="datetime", nullable=true)
     */
    protected $completed_at;

    /**
     * @var string
     * @ORM\Column(type="string")
     */
    protected $package;


    public static function getOrCreateByFileName($filename, $pkg)
    {
        $em = static::getEntityManager();
        $rep = $em->getRepository(get_called_class());
        $migration = $rep->findOneBy(['filename' => $filename]);
        if (!is_object($migration) || !$migration->getId()) {
            $migration = new Migration();
            $migration->completed = false;
            $migration->filename = $filename;
            $migration->package = $pkg->getPackageHandle();
            $migration->save();
        }

        return $migration;
    }

    public function run()
    {
        if (substr($this->getFilename(), -4) == '.sql') {
            $sql = file_get_contents($this->getFilepath());
            if (empty($sql)) {
                return;
            }
            $db = \Database::connection();
            $db->execute($sql);
        } else {
            try {
                require_once $this->getFilePath();
            } catch(\Exception $e) {
                print $e->getMessage();
                return;
            }
            $class = '\Migrations\\' . substr(substr(preg_replace('#^\d+#', '', $this->filename), 1), 0, -4) . 'Migration';
            try {
                /* @var $mig MigrationBase */
                $mig = new $class();
                $mig->run();
            } catch (\Exception $e) {
                print $e->getMessage();
                return;
            }
        }

        $this->completed = true;
        $this->completed_at = new \DateTime("now");
        $this->save();
    }

    /**
     * @return Migration[]
     */
    public static function getCompletedMigrations($pkg)
    {
        $em = static::getEntityManager();
        $repository = $em->getRepository(get_called_class());
        return $repository->findBy(['completed' => 1, 'package' => $pkg->getPackageHandle()]);
    }

    /**
     * @return Migration[]
     */
    public static function getPendingMigrations($pkg)
    {
        static::recordNewMigrations($pkg);
        $em = static::getEntityManager();
        $repository = $em->getRepository(get_called_class());
        return $repository->findBy(['completed' => 0, 'package' => $pkg->getPackageHandle()], ['filename' => 'ASC']);
    }

    public static function recordNewMigrations($pkg)
    {
        $migrations = static::getMigrationFileNames($pkg);
        $completed = static::getCompletedMigrations($pkg);

        $completedNames = [];
        foreach ($completed as $migration) {
            $completedNames[] = $migration->getFilename();
        }

        $not_run = array_diff($migrations, $completedNames);

        foreach ($not_run as $filename) {
            Migration::getOrCreateByFileName($filename, $pkg);
        }
    }

    /**
     * an array containing the filenames of all of the migrations
     * @return array
     */
    public static function getMigrationFileNames($pkg)
    {
        $migrations = [];
        if ($handle = opendir(static::getMigrationPath($pkg))) {
            while (false !== $file = readdir($handle)) {
                if ($file != '.' && $file != '..') {
                    $migrations[] = $file;
                }
            }
        }
        return $migrations;
    }

    public static function getMigrationPath($pkg)
    {
        return DIR_PACKAGES . '/' . $pkg->getPackageHandle() .  '/src/Migrations/';
    }

    public function getFilePath()
    {
        return DIR_PACKAGES . '/' . $this->package .  '/src/Migrations/' . $this->getFilename();
    }

    /**
     * @return string
     */
    public function getFilename()
    {
        return $this->filename;
    }

    /**
     * @param string $filename
     */
    public function setFilename($filename)
    {
        $this->filename = $filename;
    }

    /**
     * @return bool
     */
    public function validate()
    {
        return true;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return sprintf("%s - %s", $this->getFilename(), $this->package);
    }
}