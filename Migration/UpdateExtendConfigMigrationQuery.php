<?php

namespace Oro\Bundle\EntityExtendBundle\Migration;

use Oro\Bundle\MigrationBundle\Migration\MigrationQuery;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;

class UpdateExtendConfigMigrationQuery implements MigrationQuery
{
    /**
     * @var ExtendOptionsProviderInterface
     */
    protected $optionsProvider;

    /**
     * @var ExtendConfigProcessor
     */
    protected $configProcessor;

    /**
     * @var ExtendConfigDumper
     */
    protected $configDumper;

    /**
     * @param ExtendOptionsProviderInterface $optionsProvider
     * @param ExtendConfigProcessor          $configProcessor
     * @param ExtendConfigDumper             $configDumper
     */
    public function __construct(
        ExtendOptionsProviderInterface $optionsProvider,
        ExtendConfigProcessor $configProcessor,
        ExtendConfigDumper $configDumper
    ) {
        $this->optionsProvider = $optionsProvider;
        $this->configProcessor = $configProcessor;
        $this->configDumper    = $configDumper;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        $result = [];
        $options = $this->optionsProvider->getOptions();
        foreach ($options as $entityClassName => $entityOptions) {
            if (isset($entityOptions['configs'])) {
                $result[] = sprintf(
                    'CREATE EXTEND ENTITY %s',
                    $entityClassName
                );
            }
            if (isset($entityOptions['fields'])) {
                foreach ($entityOptions['fields'] as $fieldName => $fieldOptions) {
                    $result[] = sprintf(
                        'CREATE EXTEND FIELD %s FOR %s',
                        $fieldName,
                        $entityClassName
                    );
                }
            }
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function execute()
    {
        $this->configProcessor->processConfigs($this->optionsProvider->getOptions());
        $this->configDumper->updateConfig();
        $this->configDumper->dump();
    }
}
