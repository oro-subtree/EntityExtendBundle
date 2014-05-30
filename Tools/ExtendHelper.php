<?php

namespace Oro\Bundle\EntityExtendBundle\Tools;

use Doctrine\Common\Inflector\Inflector;

class ExtendHelper
{
    /**
     * @param $type
     * @return string
     */
    public static function getReversRelationType($type)
    {
        switch ($type) {
            case 'oneToMany':
                return 'manyToOne';
            case 'manyToOne':
                return 'oneToMany';
            case 'manyToMany':
                return 'manyToMany';
            default:
                return $type;
        }
    }

    /**
     * @param string $targetEntityClassName
     * @return string
     */
    public static function buildAssociationName($targetEntityClassName)
    {
        return Inflector::tableize(
            ExtendHelper::getShortClassName($targetEntityClassName)
        );
    }

    /**
     * @param string $entityClassName
     * @param string $fieldName
     * @param string $fieldType
     * @param string $targetEntityClassName
     * @return string
     */
    public static function buildRelationKey($entityClassName, $fieldName, $fieldType, $targetEntityClassName)
    {
        return implode('|', [$fieldType, $entityClassName, $targetEntityClassName, $fieldName]);
    }

    /**
     * Checks if an entity is a custom one
     * The custom entity is an entity which has no PHP class in any bundle. The definition of such entity is
     * created automatically in Symfony cache
     *
     * @param string $className
     * @return bool
     */
    public static function isCustomEntity($className)
    {
        return strpos($className, ExtendConfigDumper::ENTITY) === 0;
    }

    /**
     * Gets the short name of the class, the part without the namespace.
     *
     * @param string $className The full name of a class
     * @return string
     */
    public static function getShortClassName($className)
    {
        /*
         * @todo: in future to prevent collisions we should do the following
         * For ORO classes this method returns the part without the namespace.
         * For other classes this method returns the vendor name + the part without the namespace.
        $vendor = substr($className, 0, strpos($className, '\\'));
        $name   = substr($className, strrpos($className, '\\') + 1);

        return in_array(strtolower($vendor), ['oro', 'orocrm'], true)
            ? $name
            : sprintf('%s_%s', $vendor, $name);
        */

        return substr($className, strrpos($className, '\\') + 1);
    }
}
