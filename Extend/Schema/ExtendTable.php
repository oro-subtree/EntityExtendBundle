<?php

namespace Oro\Bundle\EntityExtendBundle\Extend\Schema;

use Doctrine\DBAL\Schema\Table;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;

class ExtendTable extends Table
{
    const EXTEND_OPTION_PREFIX_NAME = 'oro_options';
    const EXTEND_OPTION_PREFIX      = 'oro_options:';

    /**
     * @var ExtendOptionManager
     */
    protected $extendOptionManager;

    /** @var  ExtendSchema */
    protected $schema;

    /**
     * @param ExtendSchema        $schema
     * @param ExtendOptionManager $extendOptionManager
     * @param Table               $baseTable
     */
    public function __construct(ExtendSchema $schema, ExtendOptionManager $extendOptionManager, Table $baseTable)
    {
        $this->schema              = $schema;
        $this->extendOptionManager = $extendOptionManager;

        parent::__construct(
            $baseTable->getName(),
            $baseTable->getColumns(),
            $baseTable->getIndexes(),
            $baseTable->getForeignKeys(),
            false,
            $baseTable->getOptions()
        );
    }

    /**
     * {@inheritdoc}
     */
    public function addOption($name, $value)
    {
        if (0 === strpos($name, self::EXTEND_OPTION_PREFIX_NAME)) {
            if (strlen(self::EXTEND_OPTION_PREFIX_NAME) === strlen($name)) {
                $this->extendOptionManager->addTableOptions(
                    $this->getName(),
                    $value
                );

                return $this;
            } elseif (0 === strpos($name, self::EXTEND_OPTION_PREFIX)) {
                $this->extendOptionManager->addTableOption(
                    $this->getName(),
                    substr($name, strlen(self::EXTEND_OPTION_PREFIX)),
                    $value
                );

                return $this;
            }
        }

        return parent::addOption($name, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function addColumn($columnName, $typeName, array $options = array())
    {
        switch ($typeName) {
            case 'oneToMany':
                $selfColumnName     = ExtendConfigDumper::DEFAULT_PREFIX . $columnName . '_id';
                $selfTableName      = $this->getName();
                $selfClassName      =
                    $this->extendOptionManager->getEntityClassResolver()->getEntityClassByTableName($selfTableName);
                $selfClassNameParts = explode('\\', $selfClassName);

                $targetTableName = $options[self::EXTEND_OPTION_PREFIX_NAME]['extend']['target']['table_name'];
                if (!$this->schema->hasTable($targetTableName)) {
                    throw new \RuntimeException(sprintf('Table "%s" do NOT exists.', $targetTableName));
                }
                $targetTable      = $this->schema->getTable($targetTableName);
                $targetColumnName =
                    ExtendConfigDumper::FIELD_PREFIX .
                    strtolower(array_pop($selfClassNameParts)) .
                    '_' .
                    $columnName .
                    '_id';

                parent::addColumn($selfColumnName, 'integer', ['notnull' => false]);
                parent::addUniqueIndex([$selfColumnName], 'UNIQUE_' . $selfColumnName);
                parent::addForeignKeyConstraint(
                    $targetTable,
                    [$selfColumnName],
                    $targetTable->getPrimaryKey()->getColumns(),
                    ['onDelete' => 'SET NULL']
                );
                $targetTable->addColumn($targetColumnName, 'integer', ['notnull' => false]);
                $targetTable->addIndex([$targetColumnName], 'IDX_' . $targetColumnName);
                $targetTable->addForeignKeyConstraint(
                    $this,
                    [$targetColumnName],
                    [$selfColumnName],
                    ['onDelete' => 'SET NULL']
                );

                break;

            case 'manyToOne':
                $selfColumnName = ExtendConfigDumper::DEFAULT_PREFIX . $columnName . '_id';

                $targetTableName = $options[self::EXTEND_OPTION_PREFIX_NAME]['extend']['target']['table_name'];
                if (!$this->schema->hasTable($targetTableName)) {
                    throw new \RuntimeException(sprintf('Table "%s" do NOT exists.', $targetTableName));
                }
                $targetTable = $this->schema->getTable($targetTableName);

                parent::addColumn($selfColumnName, 'integer', ['notnull' => false]);
                parent::addIndex([$selfColumnName], 'IDX_' . $selfColumnName);
                parent::addForeignKeyConstraint(
                    $targetTable,
                    [$selfColumnName],
                    $targetTable->getPrimaryKey()->getColumns(),
                    ['onDelete' => 'SET NULL']
                );

                break;

            case 'manyToMany':
                $selfColumnName = ExtendConfigDumper::DEFAULT_PREFIX . $columnName . '_id';
                $selfTableName = $this->getName();
                $selfClassName =
                    $this->extendOptionManager->getEntityClassResolver()->getEntityClassByTableName($selfTableName);
                $selfClassNameParts = explode('\\', $selfClassName);
                $selfName = strtolower(array_pop($selfClassNameParts));

                $targetTableName = $options[self::EXTEND_OPTION_PREFIX_NAME]['extend']['target']['table_name'];
                if (!$this->schema->hasTable($targetTableName)) {
                    throw new \RuntimeException(sprintf('Table "%s" do NOT exists.', $targetTableName));
                }
                $targetTable = $this->schema->getTable($targetTableName);
                $targetClassName =
                    $this->extendOptionManager->getEntityClassResolver()->getEntityClassByTableName($targetTableName);
                $targetClassNameParts = explode('\\', $targetClassName);
                $targetName = strtolower(array_pop($targetClassNameParts));

                parent::addColumn($selfColumnName, 'integer', ['notnull' => false]);
                parent::addUniqueIndex([$selfColumnName], 'UNIQ_'.$selfColumnName);
                parent::addForeignKeyConstraint(
                    $targetTable,
                    [$selfColumnName],
                    $targetTable->getPrimaryKey()->getColumns(),
                    ['onDelete' => 'SET NULL']
                );
                parent::addIndex([$selfColumnName], 'IDX_' . $selfColumnName, ['KEY']);

                $relationsTableName = 'oro_' . $selfName. '_' . $targetName. '_' . $columnName;
                $relationsTable = $this->schema->createTable($relationsTableName);
                $relationsTable->addColumn($selfName . '_id', 'integer');
                $relationsTable->addIndex([$selfName . '_id'], 'IDX_'. $selfName . '_id');
                $relationsTable->addForeignKeyConstraint(
                    $this,
                    [$selfName . '_id'],
                    $this->getPrimaryKey()->getColumns(),
                    ['onDelete' => 'CASCADE']
                );
                $relationsTable->addColumn($targetName . '_id', 'integer');
                $relationsTable->addIndex([$targetName . '_id'], 'IDX_'. $targetName . '_id');
                $relationsTable->addForeignKeyConstraint(
                    $targetTable,
                    [$targetName . '_id'],
                    $targetTable->getPrimaryKey()->getColumns(),
                    ['onDelete' => 'CASCADE']
                );
                $relationsTable->setPrimaryKey([$selfName . '_id', $targetName . '_id']);

                break;
        }

        if (!isset($options[self::EXTEND_OPTION_PREFIX_NAME])
            && $this->extendOptionManager->isConfigurableEntity($this->getName())
        ) {
            $options[self::EXTEND_OPTION_PREFIX_NAME] = [];
        }

        foreach ($options as $name => $value) {
            if ($name === self::EXTEND_OPTION_PREFIX_NAME) {
                $this->extendOptionManager->addColumnOptions(
                    $this->getName(),
                    $columnName,
                    $typeName,
                    $value
                );
                unset($options[$name]);

                $columnName         = ExtendConfigDumper::FIELD_PREFIX . $columnName;
                $options['notnull'] = false;
            }
        }

        if (in_array($typeName, ['oneToMany', 'manyToOne', 'manyToMany', 'optionSet'])) {
            return null;
        }

        return parent::addColumn($columnName, $typeName, $options);
    }

    public function setSchema(ExtendSchema $schema)
    {
        $this->schema = $schema;
    }
}
