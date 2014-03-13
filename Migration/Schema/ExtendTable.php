<?php

namespace Oro\Bundle\EntityExtendBundle\Migration\Schema;

use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use Oro\Bundle\MigrationBundle\Migration\Schema\TableWithNameGenerator;

class ExtendTable extends TableWithNameGenerator
{
    const COLUMN_CLASS = 'Oro\Bundle\EntityExtendBundle\Migration\Schema\ExtendColumn';

    /**
     * @var ExtendOptionsManager
     */
    protected $extendOptionsManager;

    /**
     * @var ExtendSchema
     */
    protected $schema;

    /**
     * @param array $args
     */
    public function __construct(array $args)
    {
        $this->extendOptionsManager = $args['extendOptionsManager'];
        $this->schema               = $args['schema'];

        parent::__construct($args);
    }

    /**
     * {@inheritdoc}
     */
    protected function createColumnObject(array $args)
    {
        $args['tableName']            = $this->getName();
        $args['extendOptionsManager'] = $this->extendOptionsManager;
        $args['schema']               = $this->schema;

        return parent::createColumnObject($args);
    }

    /**
     * Sets a schema this table belongs
     *
     * @param ExtendSchema $schema
     */
    public function setSchema(ExtendSchema $schema)
    {
        $this->schema = $schema;
    }

    /**
     * {@inheritdoc}
     */
    public function addOption($name, $value)
    {
        if ($name === ExtendColumn::ORO_OPTIONS_NAME) {
            $this->extendOptionsManager->setTableOptions($this->getName(), $value);

            return $this;
        }

        return parent::addOption($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn($columnName, $typeName, array $options = [])
    {
        $oroOptions = null;
        if (isset($options[ExtendColumn::ORO_OPTIONS_NAME])) {
            $oroOptions = $options[ExtendColumn::ORO_OPTIONS_NAME];
            unset($options[ExtendColumn::ORO_OPTIONS_NAME]);
        }

        if (null !== $oroOptions && isset($oroOptions['extend'])) {
            if (!isset($oroOptions['extend']['extend'])) {
                $oroOptions['extend']['extend'] = true;
            }
            $options['notnull'] = false;
        }

        $column = parent::addColumn($columnName, $typeName, $options);

        if (null !== $oroOptions) {
            $oroOptions[ExtendOptionsManager::TYPE_OPTION] = $column->getType()->getName();
            $column->setOptions([
                ExtendColumn::ORO_OPTIONS_NAME => $oroOptions
            ]);
        }

        return $column;
    }

    //public function __clone(){}
}
