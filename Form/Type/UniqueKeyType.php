<?php

namespace Oro\Bundle\EntityExtendBundle\Form\Type;

use Doctrine\Common\Collections\ArrayCollection;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;

use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;

class UniqueKeyType extends AbstractType
{
    /**
     * @var FieldConfigId[]
     */
    protected $fields;

    public function __construct($fields)
    {
        $this->fields = $fields;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $choices = array_map(
            function (FieldConfigId $field) {
                return ucfirst($field->getFieldName());
            },
            $this->fields
        );

        $builder->add(
            'name',
            'text',
            array(
                'required' => true,
            )
        );

        $builder->add(
            'key',
            'choice',
            array(
                'multiple' => true,
                'choices'  => $choices,
                'required' => true,
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'oro_entity_extend_unique_key_type';
    }
}
