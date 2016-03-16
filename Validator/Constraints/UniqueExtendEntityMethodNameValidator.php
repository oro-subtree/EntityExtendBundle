<?php

namespace Oro\Bundle\EntityExtendBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

use Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel;

/**
 * Validates method name for uniqueness for field name.
 */
class UniqueExtendEntityMethodNameValidator extends AbstractFieldValidator
{
    const ALIAS = 'oro_entity_extend.validator.unique_extend_entity_method_name';

    /**
     * {@inheritdoc}
     */
    public function validate($value, Constraint $constraint)
    {
        if (!$value instanceof FieldConfigModel) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Oro\Bundle\EntityConfigBundle\Entity\FieldConfigModel supported only, %s given',
                    is_object($value) ? get_class($value) : gettype($value)
                )
            );
        }

        $className = $value->getEntity()->getClassName();
        $fieldName = $value->getFieldName();
        $camelized = $this->camelize($fieldName);
        $reaching = [
            'get' . $camelized,
            'set' . $camelized,
            'is' . $camelized,
            'has' . $camelized
        ];

        $class_methods = get_class_methods($className);

        foreach ($reaching as $methodName) {
            if (in_array($methodName, $class_methods)) {
                $this->addViolation($constraint->sameMethodMessage, $fieldName, $methodName);
            }
        }
    }

    /**
     * Camelizes a given string.
     *
     * @param string $string Some string
     *
     * @return string The camelized version of the string
     */
    protected function camelize($string)
    {
        return strtr(ucwords(strtr($string, ['_' => ' '])), [' ' => '']);
    }
}
