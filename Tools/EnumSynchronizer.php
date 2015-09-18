<?php

namespace Oro\Bundle\EntityExtendBundle\Tools;

use Doctrine\Common\Persistence\ManagerRegistry;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;

use Symfony\Component\Translation\TranslatorInterface;

use Gedmo\Translatable\TranslatableListener;

use Oro\Bundle\EntityConfigBundle\Config\ConfigInterface;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue;
use Oro\Bundle\EntityExtendBundle\Entity\Repository\EnumValueRepository;
use Oro\Bundle\EntityExtendBundle\Exception\EnumDuplicateException;
use Oro\Bundle\TranslationBundle\Entity\Repository\TranslationRepository;
use Oro\Bundle\TranslationBundle\Entity\Translation;
use Oro\Bundle\TranslationBundle\Translation\DynamicTranslationMetadataCache;

class EnumSynchronizer
{
    /** @var ConfigManager */
    protected $configManager;

    /** @var ManagerRegistry */
    protected $doctrine;

    /** @var TranslatorInterface */
    protected $translator;

    /** @var DynamicTranslationMetadataCache */
    protected $dbTranslationMetadataCache;

    /**
     * @param ConfigManager                   $configManager
     * @param ManagerRegistry                 $doctrine
     * @param TranslatorInterface             $translator
     * @param DynamicTranslationMetadataCache $dbTranslationMetadataCache
     */
    public function __construct(
        ConfigManager $configManager,
        ManagerRegistry $doctrine,
        TranslatorInterface $translator,
        DynamicTranslationMetadataCache $dbTranslationMetadataCache
    ) {
        $this->configManager              = $configManager;
        $this->doctrine                   = $doctrine;
        $this->translator                 = $translator;
        $this->dbTranslationMetadataCache = $dbTranslationMetadataCache;
    }

    /**
     * Synchronizes enum related data for new enums
     */
    public function sync()
    {
        $extendConfigProvider = $this->configManager->getProvider('extend');
        $enumConfigProvider   = $this->configManager->getProvider('enum');
        $entityConfigs        = $extendConfigProvider->getConfigs();
        foreach ($entityConfigs as $entityConfig) {
            if (!$entityConfig->is('is_extend') || $entityConfig->is('is_deleted')) {
                continue;
            }

            $fieldConfigs = $extendConfigProvider->getConfigs($entityConfig->getId()->getClassName());
            foreach ($fieldConfigs as $fieldConfig) {
                /** @var FieldConfigId $fieldConfigId */
                $fieldConfigId = $fieldConfig->getId();
                if ($fieldConfig->is('is_deleted')
                    || !in_array($fieldConfigId->getFieldType(), ['enum', 'multiEnum'])
                ) {
                    continue;
                }

                $enumFieldConfig = $enumConfigProvider->getConfig(
                    $fieldConfigId->getClassName(),
                    $fieldConfigId->getFieldName()
                );

                $enumCode = $enumFieldConfig->get('enum_code');
                if (!empty($enumCode)) {
                    $enumValueClassName = $fieldConfig->get('target_entity');
                    $enumName           = $enumFieldConfig->get('enum_name');
                    $locale             = $enumFieldConfig->get('enum_locale');
                    $isPublic           = $enumFieldConfig->get('enum_public');
                    $enumOptions        = $enumFieldConfig->get('enum_options');

                    if (empty($locale)) {
                        $locale = Translation::DEFAULT_LOCALE;
                    }

                    if (!empty($enumName)) {
                        $this->applyEnumNameTrans($enumCode, $enumName, $locale);
                    }
                    if (!empty($enumOptions)) {
                        $this->applyEnumOptions($enumValueClassName, $enumOptions, $locale);
                    }
                    if ($isPublic !== null) {
                        $this->applyEnumEntityOptions($enumValueClassName, $isPublic, false);
                    }

                    $this->updateEnumFieldConfig($enumFieldConfig);
                }
            }
        }

        $this->configManager->flush();
    }

    /**
     * @param string $enumCode
     * @param string $enumName
     * @param string $locale
     *
     * @throws \InvalidArgumentException
     */
    public function applyEnumNameTrans($enumCode, $enumName, $locale)
    {
        if (empty($enumCode)) {
            throw new \InvalidArgumentException('$enumCode must not be empty.');
        }
        if (empty($enumName)) {
            throw new \InvalidArgumentException('$enumName must not be empty.');
        }
        if (empty($locale)) {
            throw new \InvalidArgumentException('$locale must not be empty.');
        }

        // add translations for an entity will be used to store enum values
        $labelsToBeUpdated = [];
        $labelKey          = ExtendHelper::getEnumTranslationKey('label', $enumCode);
        $pluralLabelKey    = ExtendHelper::getEnumTranslationKey('plural_label', $enumCode);
        $descriptionKey    = ExtendHelper::getEnumTranslationKey('description', $enumCode);
        $currentLabelTrans = $this->translator->trans($labelKey, [], null, $locale);
        if ($currentLabelTrans === $labelKey) {
            // labels initialization
            $labelsToBeUpdated[$labelKey]       = $enumName;
            $labelsToBeUpdated[$pluralLabelKey] = $enumName;
            if ($locale === Translation::DEFAULT_LOCALE) {
                // set empty description only for default locale
                $labelsToBeUpdated[$descriptionKey] = '';
            }
        } elseif ($enumName != $currentLabelTrans) {
            // update existing labels
            $labelsToBeUpdated[$labelKey]       = $enumName;
            $labelsToBeUpdated[$pluralLabelKey] = $enumName;
        }
        if (!empty($labelsToBeUpdated)) {
            /** @var EntityManager $em */
            $em = $this->doctrine->getManagerForClass(Translation::ENTITY_NAME);
            /** @var TranslationRepository $translationRepo */
            $translationRepo = $em->getRepository(Translation::ENTITY_NAME);
            $transValues     = [];
            foreach ($labelsToBeUpdated as $labelKey => $labelText) {
                // save into translation table
                $transValues[] = $translationRepo->saveValue(
                    $labelKey,
                    $labelText,
                    $locale,
                    TranslationRepository::DEFAULT_DOMAIN,
                    Translation::SCOPE_UI
                );
            }
            // flush translations to db
            $em->flush($transValues);
            // mark translation cache dirty
            $this->dbTranslationMetadataCache->updateTimestamp($locale);
        }
    }

    /**
     * @param string $enumValueClassName
     * @param bool   $isPublic
     * @param bool   $doFlush
     *
     * @throws \InvalidArgumentException
     */
    public function applyEnumEntityOptions($enumValueClassName, $isPublic, $doFlush = true)
    {
        if (empty($enumValueClassName)) {
            throw new \InvalidArgumentException('$enumValueClassName must not be empty.');
        }

        $enumConfigProvider = $this->configManager->getProvider('enum');
        $enumEntityConfig   = $enumConfigProvider->getConfig($enumValueClassName);

        if (!$enumEntityConfig->is('public', $isPublic)) {
            $enumEntityConfig->set('public', $isPublic);
            $this->configManager->persist($enumEntityConfig);
            if ($doFlush) {
                $this->configManager->flush();
            }
        }
    }

    /**
     * @param string $enumValueClassName
     * @param array  $options
     * @param string $locale
     *
     * @throws \InvalidArgumentException
     */
    public function applyEnumOptions($enumValueClassName, array $options, $locale)
    {
        if (empty($enumValueClassName)) {
            throw new \InvalidArgumentException('$enumValueClassName must not be empty.');
        }
        if (empty($locale)) {
            throw new \InvalidArgumentException('$locale must not be empty.');
        }

        /** @var EntityManager $em */
        $em = $this->doctrine->getManagerForClass($enumValueClassName);
        /** @var EnumValueRepository $enumRepo */
        $enumRepo = $em->getRepository($enumValueClassName);

        /** @var AbstractEnumValue[] $values */
        $values = $enumRepo->createQueryBuilder('o')
            ->getQuery()
            ->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale)
            ->getResult();

        /** @var AbstractEnumValue[] $changes */
        $changes = [];
        foreach ($values as $value) {
            $optionKey = $this->getEnumOptionKey($value->getId(), $options);
            if ($optionKey !== null) {
                if ($this->setEnumValueProperties($value, $options[$optionKey])) {
                    $changes[] = $value;
                }
                unset($options[$optionKey]);
            } else {
                $em->remove($value);
                $changes[] = $value;
            }
        }

        $enums = [];
        foreach ($options as $option) {
            $value = $enumRepo->createEnumValue(
                $option['label'],
                $option['priority'],
                $option['is_default']
            );
            $em->persist($value);
            $enums[]   = $value;
            $changes[] = $value;
        }

        $this->resolveEnumDuplicates($values, $enums);

        if (!empty($changes)) {
            if ($locale !== Translation::DEFAULT_LOCALE) {
                foreach ($changes as $value) {
                    $value->setLocale($locale);
                }
            }
            $em->flush($changes);
            // mark translation cache dirty
            $this->dbTranslationMetadataCache->updateTimestamp($locale);
        }
    }

    /**
     * @param AbstractEnumValue[] $savedEnums
     * @param AbstractEnumValue[] $newEnums
     *
     * @throws EnumDuplicateException
     */
    protected function resolveEnumDuplicates($savedEnums, $newEnums)
    {
        // Array with stored enum ids.
        $saved = array_map(
            function (AbstractEnumValue $enum) {
                return $enum->getId();
            },
            $savedEnums
        );

        // Array with new enum ids.
        $new =  array_map(
            function (AbstractEnumValue $enum) {
                return $enum->getId();
            },
            $newEnums
        );

        // Array with all enum ids.
        $all = array_merge($new, $saved);

        // Array with all unique enum ids.
        $unique = array_unique($all);

        $duplicates = array_diff_assoc($all, $unique);
        foreach ($duplicates as $duplicate) {
            // Flag for skip first enum.
            $skip = (array_search($duplicate, $saved) === false);
            foreach ($newEnums as $enum) {
                if ($enum->getId() == $duplicate) {
                    if ($skip) {
                        $skip = false;
                        continue;
                    }

                    // Try to generate unique id for new enum and change it.
                    for ($attempt = 1; $attempt <= 10; $attempt++) {
                        $id = ExtendHelper::buildEnumValueId($enum->getId() . $attempt);
                        if (array_search($id, $unique) === false) {
                            $enum->setId($id);
                            $unique[] = $id;
                            continue 2;
                        }
                    }

                    // Otherwise
                    throw new EnumDuplicateException(sprintf('Duplicate enum value: %s.', $enum->getId()));
                }
            }
        }
    }

    /**
     * @param string $enumValueClassName
     *
     * @return array
     */
    public function getEnumOptions($enumValueClassName)
    {
        /** @var EntityManager $em */
        $em       = $this->doctrine->getManagerForClass($enumValueClassName);
        $enumRepo = $em->getRepository($enumValueClassName);

        return $enumRepo->createQueryBuilder('e')
            ->select('e.id, e.priority, e.name as label, e.default as is_default')
            ->orderBy('e.priority')
            ->getQuery()
            ->setHint(
                Query::HINT_CUSTOM_OUTPUT_WALKER,
                'Gedmo\\Translatable\\Query\\TreeWalker\\TranslationWalker'
            )
            ->getArrayResult();
    }

    /**
     * @param string $id
     * @param array  $options
     *
     * @return int|null
     */
    protected function getEnumOptionKey($id, array $options)
    {
        foreach ($options as $optKey => $opt) {
            if ($id == $opt['id']) {
                return $optKey;
            }
        }

        return null;
    }

    /**
     * @param AbstractEnumValue $value
     * @param array             $option
     *
     * @return bool
     */
    protected function setEnumValueProperties(AbstractEnumValue $value, array $option)
    {
        $hasChanges = false;

        if ($value->getName() != $option['label']) {
            $value->setName($option['label']);
            $hasChanges = true;
        }
        if ($value->getPriority() != $option['priority']) {
            $value->setPriority($option['priority']);
            $hasChanges = true;
        }
        if ($value->isDefault() != $option['is_default']) {
            $value->setDefault($option['is_default']);
            $hasChanges = true;
        }

        return $hasChanges;
    }

    /**
     * @param ConfigInterface $enumFieldConfig
     *
     * @return bool
     */
    protected function updateEnumFieldConfig(ConfigInterface $enumFieldConfig)
    {
        $hasChanges = false;

        $attributes = ['enum_name', 'enum_locale', 'enum_public', 'enum_options'];
        foreach ($attributes as $code) {
            if ($enumFieldConfig->get($code) !== null) {
                $enumFieldConfig->remove($code);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->configManager->persist($enumFieldConfig);
        }

        return $hasChanges;
    }
}
