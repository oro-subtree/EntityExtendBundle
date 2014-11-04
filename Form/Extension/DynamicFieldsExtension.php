<?php

namespace Oro\Bundle\EntityExtendBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Translation\TranslatorInterface;

use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProviderInterface;
use Oro\Bundle\EntityConfigBundle\Tools\FieldAccessor;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;

/**
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class DynamicFieldsExtension extends AbstractTypeExtension
{
    /** @var ConfigManager */
    protected $configManager;

    /** @var RouterInterface */
    protected $router;

    /** @var TranslatorInterface */
    protected $translator;

    /**
     * @param ConfigManager       $configManager
     * @param RouterInterface     $router
     * @param TranslatorInterface $translator
     */
    public function __construct(
        ConfigManager $configManager,
        RouterInterface $router,
        TranslatorInterface $translator
    ) {
        $this->configManager = $configManager;
        $this->router        = $router;
        $this->translator    = $translator;
    }

    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        if ($options['dynamic_fields_disabled'] || empty($options['data_class'])) {
            return;
        }

        $className = $options['data_class'];
        if (!$this->configManager->getProvider('extend')->hasConfig($className)) {
            return;
        }

        $extendConfigProvider = $this->configManager->getProvider('extend');
        $formConfigs          = $this->configManager->getProvider('form')->getConfigs($className);

        foreach ($formConfigs as $formConfig) {
            if (!$formConfig->is('is_enabled')) {
                continue;
            }

            /** @var FieldConfigId $fieldConfigId */
            $fieldConfigId = $formConfig->getId();
            $fieldName     = $fieldConfigId->getFieldName();
            $extendConfig  = $extendConfigProvider->getConfig($className, $fieldName);

            if (!$this->isApplicableField($extendConfig, $extendConfigProvider)) {
                continue;
            }

            $builder->add($fieldName);
        }
    }

    /**
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     *
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        if ($options['dynamic_fields_disabled'] || empty($options['data_class'])) {
            return;
        }

        $className = $options['data_class'];
        if (!$this->configManager->getProvider('extend')->hasConfig($className)) {
            return;
        }

        $data = $form->getData();

        $extendConfigProvider = $this->configManager->getProvider('extend');
        $formConfigProvider   = $this->configManager->getProvider('form');

        $formConfigs = $formConfigProvider->getConfigs($className);
        foreach ($formConfigs as $formConfig) {
            if (!$formConfig->is('is_enabled')) {
                continue;
            }

            /** @var FieldConfigId $fieldConfigId */
            $fieldConfigId = $formConfig->getId();
            $fieldName     = $fieldConfigId->getFieldName();

            $extendConfig = $extendConfigProvider->getConfig($className, $fieldName);
            if (!$this->isApplicableField($extendConfig, $extendConfigProvider)) {
                continue;
            }

            $view->children[$fieldName]->vars['extra_field'] = true;

            if (in_array($fieldConfigId->getFieldType(), ['oneToMany', 'manyToMany'])) {
                $dataId = 0;
                if ($data->getId()) {
                    $dataId = $data->getId();
                }
                $view->children[$fieldName]->vars['grid_url'] =
                    $this->router->generate(
                        'oro_entity_relation',
                        [
                            'id'         => $dataId,
                            'entityName' => str_replace('\\', '_', $className),
                            'fieldName'  => $fieldName
                        ]
                    );

                $defaultEntity = null;
                if (!$extendConfig->is('without_default')) {
                    $defaultEntity = FieldAccessor::getValue(
                        $data,
                        ExtendConfigDumper::DEFAULT_PREFIX . $fieldName
                    );
                }
                $selectedCollection = FieldAccessor::getValue($data, $fieldName);

                if ($data->getId()) {
                    $view->children[$fieldName]->vars['initial_elements'] =
                        $this->getInitialElements($selectedCollection, $defaultEntity, $extendConfig);
                }
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(
            [
                'dynamic_fields_disabled' => false,
            ]
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getExtendedType()
    {
        return 'form';
    }

    /**
     * @param ConfigInterface         $extendConfig
     * @param ConfigProviderInterface $extendConfigProvider
     *
     * @return bool
     */
    protected function isApplicableField(ConfigInterface $extendConfig, ConfigProviderInterface $extendConfigProvider)
    {
        return
            !$extendConfig->is('is_deleted')
            && $extendConfig->is('owner', ExtendScope::OWNER_CUSTOM)
            && !$extendConfig->is('state', ExtendScope::STATE_NEW)
            && !in_array($extendConfig->getId()->getFieldType(), ['ref-one', 'ref-many'])
            && (
                !$extendConfig->has('target_entity')
                || !$extendConfigProvider->getConfig($extendConfig->get('target_entity'))->is('is_deleted')
            );
    }

    /**
     * @param object[]        $entities
     * @param object|null     $defaultEntity
     * @param ConfigInterface $extendConfig
     *
     * @return array
     */
    protected function getInitialElements($entities, $defaultEntity, ConfigInterface $extendConfig)
    {
        $result = [];
        foreach ($entities as $entity) {
            $extraData = [];
            foreach ($extendConfig->get('target_grid') as $fieldName) {
                $label = $this->configManager->getProvider('entity')
                    ->getConfig($extendConfig->get('target_entity'), $fieldName)
                    ->get('label');

                $extraData[] = [
                    'label' => $this->translator->trans($label),
                    'value' => FieldAccessor::getValue($entity, $fieldName)
                ];
            }

            $title = [];
            foreach ($extendConfig->get('target_title') as $fieldName) {
                $title[] = FieldAccessor::getValue($entity, $fieldName);
            }

            $result[] = [
                'id'        => $entity->getId(),
                'label'     => implode(' ', $title),
                'link'      => $this->router->generate(
                    'oro_entity_detailed',
                    [
                        'id'         => $entity->getId(),
                        'entityName' => str_replace('\\', '_', $extendConfig->getId()->getClassName()),
                        'fieldName'  => $extendConfig->getId()->getFieldName()
                    ]
                ),
                'extraData' => $extraData,
                'isDefault' => ($defaultEntity != null && $defaultEntity->getId() == $entity->getId())

            ];
        }

        return $result;
    }
}
