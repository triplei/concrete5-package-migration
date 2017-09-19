<?php
/**
 * @author Dan Klassen <dan@triplei.ca>
 */

namespace TripleI\Libraries;
defined('C5_EXECUTE') or die("Access Denied.");

use Concrete\Core\Package\Package;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping as ORM;

abstract class BaseModel
{
    /**
     * @ORM\Id @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    protected $errors = [];

    /**
     * @return bool
     */
    abstract public function validate();

    abstract public function __toString();

    /**
     * @return static
     */
    public static function factory()
    {
        $class = get_called_class();

        return new $class();
    }

    /**
     * get an instance of the EntityManager to work with
     * @return EntityManager
     */
    public static function getEntitymanager()
    {
        $pkg = \Package::getByHandle('your_package_handle');
        $orm = \Core::make(\Concrete\Core\Support\Facade\DatabaseORM::getFacadeAccessor());

        return $orm->entityManager($pkg);
    }

    /**
     * @param integer $id
     *
     * @return static
     */
    public static function getByID($id)
    {
        if ( ! intval($id)) {
            return false;
        }
        $em  = static::getEntityManager();
        $obj = $em->find(get_called_class(), intval($id));
        if (is_object($obj)) {
            return $em->merge($obj);
        } else {
            return false;
        }
    }

    /**
     * @param array $criteria an array of criteria to find the instance by
     * @example ChildModel::findOneBy(['first_name' => 'Joe', 'last_name' => 'Smith'])
     * @example ChildModel::findOneBy(['parent_relation' => Parent::getByID(1), 'first_name' => 'Joe'])
     *
     * @return bool|static
     */
    public static function getOneBy($criteria)
    {
        if (!is_array($criteria)) {
            throw new \InvalidArgumentException('parameter must be an array of field => value');
        }
        $em = static::getEntitymanager();
        $obj = $em->getRepository(get_called_class())->findOneBy($criteria);
        if (is_object($obj)) {
            return $em->merge($obj);
        }
        return false;
    }

    /**
     * @param array $ids a flat array of the IDs of the records to load
     *
     * @return static[]
     */
    public static function getByIDs($ids)
    {
        $em         = static::getEntityManager();
        $repository = $em->getRepository(get_called_class());

        return $repository->findBy(['id' => $ids]);
    }

    /**
     * @param array $sort
     *
     * @return static[]
     */
    public static function getAll($sort = [])
    {
        $obj = static::factory();
        if (property_exists($obj, '_default_sort') && count($sort) == 0) {
            $sort = $obj->_default_sort;
        }
        if (is_string($sort)) {
            $sort = array($sort => 'ASC');
        }

        $em         = static::getEntityManager();
        $repository = $em->getRepository(get_called_class());

        return $repository->findBy(array(), $sort);
    }

    /**
     * clear out the ID of a record when cloning it
     */
    public function __clone()
    {
        $this->id = null;
    }

    /**
     * Set the data for this instance. If the setter methods exist for the model they will be used
     * otherwise the attribute will be directly set
     *
     * @param array $data
     */
    public function setData($data)
    {
        $skip_fields = array(
            'ccm_token',
            'id'
        );
        foreach ($data as $key => $value) {
            if (in_array($key, $skip_fields)) {
                continue;
            }
            $method = "set" . Helpers::underscoreToCamelCase($key);
            if (method_exists($this, $method)) {
                $this->$method($value);
            } else {
                $this->$key = $value;
            }
        }
    }

    /**
     * save the record to the database
     *
     * @param null|array $data data to set for the record
     *
     * @return bool
     */
    public function save($data = null, $skipValidation = false)
    {
        if (is_array($data)) {
            $this->setData($data);
        }
        if ($this->beforeSave() === false) {
            return false;
        }
        if ( ! $skipValidation && ! ( $this->validate() )) {
            return false;
        }
        $em = static::getEntityManager();
        if ($this->getId() > 0) {
            $em->merge($this);
        } else {
            if ($this->beforeCreate() === false) {
                return false;
            }
            $em->persist($this);
        }
        $em->flush($this);

        $this->afterSave();

        return true;
    }

    public function beforeSave()
    {
        return true;
    }

    public function beforeCreate()
    {
        return true;
    }

    public function afterSave()
    {
    }

    /**
     * remove the record from the database
     */
    public function destroy()
    {
        $em = static::getEntityManager();
        $em->remove($this);
        $em->flush();
    }

    /**
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     *
     * @return static
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * whether or not this item is new (not saved to the database yet)
     *
     * @return bool
     */
    public function isNewRecord()
    {
        return !(bool) $this->getId() > 0;
    }

    public function addError($key, $msg)
    {
        $this->errors[ $key ] = $msg;
    }

    public function hasError()
    {
        return count($this->errors) > 0;
    }

    /**
     * @param string $key
     *
     * @return array|string
     */
    public function getErrors($key = null)
    {
        if (is_null($key)) {
            return $this->errors;
        } elseif (array_key_exists($key, $this->errors)) {
            return $this->errors[ $key ];
        }
    }

    /**
     * @param string $attr attribute to load the file id from. Defaults to image_id
     *
     * @return \Concrete\Core\Entity\File\File
     */
    public function getImage($attr = 'image_id')
    {
        if (intval($this->$attr) > 0) {
            return \File::getByID($this->$attr);
        }
    }

    /**
     * @param $column
     *
     * @return mixed
     */
    public function get($column)
    {
        $method = "get" . camel_case($column);
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            return $this->$column;
        }
    }

    /**
     * @return array
     */
    public static function getAllSelectOptions()
    {
        $options = [];
        foreach (static::getAll() as $record) {
            $options[$record->getId()] = (string) $record;
        }
        return $options;
    }

    /**
     * @param string $attr
     *
     * @return string
     */
    public function getSlug($attr = null)
    {
        $string = null;
        if (is_null($attr)) {
            $string = (string) $this;
        } else {
            $string = $this->get($attr);
        }

        return \Core::make('helper/text')->urlify($string);
    }
}