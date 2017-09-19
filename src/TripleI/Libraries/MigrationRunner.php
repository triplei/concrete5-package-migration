<?php
/**
 * @author Dan Klassen <dan@triplei.ca>
 */

namespace TripleI\Libraries;

use YOUR_PACKAGE\Entities\Migration;
use Package;

class MigrationRunner
{
    /* @var Package */
    protected $pkg;

    public static function migrate($pkg) {
        $runner = new static();
        $runner->pkg = $pkg;

        $pending = $runner->getPendingMigrations();
        foreach ($pending as $migration) {
            $migration->run();
        }
    }

    /**
     * get an instance of the EntityManager to work with
     * @return EntityManager
     */
    public static function getEntitymanager()
    {
        $pkg = \Package::getByHandle('your_package_handle');

        return $pkg->getEntityManager;
    }

    public function getPendingMigrations()
    {
        return Migration::getPendingMigrations($this->pkg);
    }




    public function getMigrationPath()
    {
        return DIR_PACKAGES . '/' . $this->pkg->getPackageHandle() .  '/src/Migrations/';
    }

    public function setPackage($pkg)
    {
        $this->pkg = $pkg;
    }
}