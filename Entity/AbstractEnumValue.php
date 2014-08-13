<?php

namespace Oro\Bundle\EntityExtendBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Gedmo\Mapping\Annotation as Gedmo;

/**
 * The base class for all entities represent values for a particular enum
 *
 * @ORM\MappedSuperclass
 */
abstract class AbstractEnumValue
{
    /**
     * @var string
     *
     * @ORM\Column(name="code", type="string", length=32)
     * @ORM\Id
     */
    private $code;

    /**
     * @var string
     *
     * @ORM\Column(name="name", type="string", length=255)
     * @Gedmo\Translatable
     */
    private $name;

    /**
     * @var integer
     *
     * @ORM\Column(name="priority", type="integer")
     */
    private $priority = 0;

    /**
     * @var boolean
     *
     * @ORM\Column(name="is_default", type="boolean")
     */
    private $default = false;

    /**
     * @Gedmo\Locale
     */
    private $locale;

    /**
     * @param string  $code
     * @param string  $name
     * @param int     $priority
     * @param boolean $default
     */
    public function __construct($code, $name, $priority = 0, $default = false)
    {
        $this->code     = $code;
        $this->name     = $name;
        $this->priority = $priority;
        $this->default  = $default;
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * @param string $name
     *
     * @return AbstractEnumValue
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param int $priority
     *
     * @return AbstractEnumValue
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param boolean $default
     *
     * @return AbstractEnumValue
     */
    public function setDefault($default)
    {
        $this->default = $default;

        return $this;
    }

    /**
     * @return boolean
     */
    public function isDefault()
    {
        return $this->default;
    }

    /**
     * @param string $locale
     *
     * @return AbstractEnumValue
     */
    public function setLocale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * Get a human-readable representation of this object.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->code;
    }
}
