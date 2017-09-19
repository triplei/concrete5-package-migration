# concrete5-package-migration
Manage DB migrations for a package

This code will let you have migrations (either .sql or .php code) you can run to manage your database changes. 
Currently the migrations are one direction (only up, there is no rollback functionality)

Ideally this would be a composer package that could be installed alongside the concrete5 package however 
I haven't been able to get that to function correctly. As a result the process to use this is as follows:

## Installation:

* clone or download this repo (if you hadn't guess that already may stop here)
* copy the contents of the src/ directory into your package's src/ directory
* make sure the YOUR_PACKAGE/Entities ends up in the corresponding location
* update package/src/TripleI/Libraries/BaseModel.php:46 with your package handle (help generalizing this would be excellent)
* update the namespace for the `use` statement package/src/TripleI/Libraries/MigrationRunner:8
* update package/src/TripleI/Libraries/MigrationRunner:32 with your package handle
* update your package/controller.php with the following:

```php
use Console\Command\Migrate;
use Concrete\Core\Package\Package;
use TripleI\Libraries\MigrationRunner;

class Controller extends Package
{
    
    protected $pkgAutoloaderRegistries = [
        'src/Console/Command'       => 'Console\Command',
        'src/YOUR_PACKAGE/Entities' => '\YOUR_PACKAGE\Entities',
        'src/Migrations'            => '\Migrations',
        'src/TripleI/Libraries'     => '\TripleI\Libraries'
    ];
    
    public function on_start()
    {
        // set up our commands
        if (\Core::make('app')->isRunThroughCommandLineInterface()) {
            try {
                $app = \Core::make('console');
                $app->add(new Migrate());
            } catch (\Exception $e) {
                print $e->getMessage();
            }
        }
    }
    
    public function install()
    {
        $pkg = parent::install();
        $this->installOrUpgrade($pkg);

        return $pkg;
    }
    
    public function upgrade()
    {
        parent::upgrade();
        $pkg = Package::getByHandle('your_package_handle');
        $this->installOrUpgrade($pkg);
    }
    
    /**
     * any code that should run on installation or upgrade can go here
     *
     * this should be called after the parent install / upgrade method so that any DB column changes are picked up
     * prior to running any migrations
     */
    protected function installOrUpgrade($pkg)
    {
        MigrationRunner::migrate($pkg);
    }
}
```

## Usage:

Create migration files inside of your_package/src/Migrations/. Migrations should be named `yyyymmdd##-MigrationName.php|sql` so that they
run in a predictable order. The migrations can either contain raw SQL statements (for files ending in *.sql) or run php code (for *.php files)

PHP files must contain a class which implements the MigrationBase interface. All this does is force the class to have a public run method.
The class must be named the same as the file stripping off the initial serial number and - and end in Migration. IE `2017091801-UpdateMyRecords.php`
could look like:

```php
namespace Migrations;

use MyPackage\Entities\Model;
use TripleI\Libraries\MigrationBase;

class UpdateMyRecordsMigration implements MigrationBase
{

    public function run()
    {
        $models = Model::getAll();

        foreach ($models as $model) {
            // force a slug refresh
            $model->updateSlug($model->getName());
            $model->save();
        }
    }
}
```

Any outstanding migrations will be run when the package is upgrade. Alternately you can run them via CLI with a command such as:
`$ ./concrete/bin/concrete package:migrate --pkg-handle my_package`

Any pull requests / feature request are welcome, however I can't promise I can get to them. Constructive criticism is also
welcome.