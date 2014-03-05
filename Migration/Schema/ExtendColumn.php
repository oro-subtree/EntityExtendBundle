<?php

namespace Oro\Bundle\EntityExtendBundle\Migration\Schema;

use Doctrine\DBAL\Schema\Column;
use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;

class ExtendColumn extends Column
{
    const ORO_OPTIONS_NAME = 'oro_options';

    /**
     * @var ExtendOptionsManager
     */
    protected $extendOptionsManager;

    /**
     * @var string
     */
    protected $tableName;

    /**
     * @param ExtendOptionsManager $extendOptionsManager
     * @param string               $tableName
     * @param Column               $baseColumn
     */
    public function __construct(ExtendOptionsManager $extendOptionsManager, $tableName, Column $baseColumn)
    {
        $this->extendOptionsManager = $extendOptionsManager;
        $this->tableName            = $tableName;

        $optionNames = [
            'Length',
            'Precision',
            'Scale',
            'Unsigned',
            'Fixed',
            'Notnull',
            'Default',
            'Autoincrement',
            'Comment'
        ];
        $options     = [];
        foreach ($optionNames as $name) {
            $method = "get" . $name;
            $val    = $baseColumn->$method();
            if ($this->$method() !== $val) {
                $options[$name] = $val;
            }
        }
        parent::__construct($baseColumn->getName(), $baseColumn->getType(), $options);
        $this->setColumnDefinition($baseColumn->getColumnDefinition());
        $this->setPlatformOptions($baseColumn->getPlatformOptions());
        $this->setCustomSchemaOptions($baseColumn->getCustomSchemaOptions());
    }

    /**
     * {@inheritdoc}
     */
    public function setOptions(array $options)
    {
        if (isset($options[ExtendColumn::ORO_OPTIONS_NAME])) {
            $columnName = $this->getName();
            if (strpos($columnName, ExtendConfigDumper::FIELD_PREFIX) === 0) {
                $columnName = substr($columnName, strlen(ExtendConfigDumper::FIELD_PREFIX));
            }
            $this->extendOptionsManager->setColumnOptions(
                $this->tableName,
                $columnName,
                $this->getType()->getName(),
                $options[ExtendColumn::ORO_OPTIONS_NAME]
            );
            unset($options[ExtendColumn::ORO_OPTIONS_NAME]);
        }

        if (!empty($options)) {
            parent::setOptions($options);
        }
    }
}
