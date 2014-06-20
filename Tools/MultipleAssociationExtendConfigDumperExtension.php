<?php

namespace Oro\Bundle\EntityExtendBundle\Tools;

use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;

abstract class MultipleAssociationExtendConfigDumperExtension extends AbstractAssociationExtendConfigDumperExtension
{
    /**
     * {@inheritdoc}
     */
    protected function getAssociationType()
    {
        return 'manyToMany';
    }

    /**
     * {@inheritdoc}
     */
    protected function isTargetEntityApplicable(ConfigInterface $targetEntityConfig)
    {
        $entityClasses = $targetEntityConfig->get($this->getAssociationAttributeName());

        return !empty($entityClasses);
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdate(array &$extendConfigs)
    {
        $targetEntityConfigs = $this->getTargetEntityConfigs();
        foreach ($targetEntityConfigs as $targetEntityConfig) {
            $entityClasses = $targetEntityConfig->get($this->getAssociationAttributeName());
            if (!empty($entityClasses)) {
                foreach ($entityClasses as $entityClass) {
                    $this->createAssociation($entityClass, $targetEntityConfig->getId()->getClassName());
                }
            }
        }
    }
}
