<?php

namespace Oro\Bundle\EntityExtendBundle\Form\Extension;

use Oro\Bundle\EntityExtendBundle\Entity\ProxyEntityInterface;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;

use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use Oro\Bundle\EntityExtendBundle\Extend\ExtendManager;

class ExtendEntityExtension extends AbstractTypeExtension
{
    /**
     * @var ExtendManager
     */
    protected $extendManager;

    /**
     * @param ExtendManager $extendManager
     */
    public function __construct(ExtendManager $extendManager)
    {
        $this->extendManager = $extendManager;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $xm = $this->extendManager;

        $builder->addEventListener(
            FormEvents::PRE_SET_DATA,
            function (FormEvent $event) use ($xm) {
                $data = $event->getData();
                //TODO::check empty data end data_class
                if (is_object($data) && $xm->isExtend($data)) {
                    $event->setData($xm->createProxyObject($data));
                }
            }
        );

        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            function (FormEvent $event) use ($xm) {
                $data = $event->getForm()->getConfig()->getData();

                if (is_object($data) && $xm->isExtend($data)) {
                    if ($event->getData() instanceof ProxyEntityInterface) {
                        $event->getData()->__proxy__cloneToEntity($data);
                    }
                }
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }
}
