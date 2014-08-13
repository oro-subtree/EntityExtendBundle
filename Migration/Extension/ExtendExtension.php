<?php

namespace Oro\Bundle\EntityExtendBundle\Migration\Extension;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\SchemaException;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;

use Oro\Bundle\EntityConfigBundle\Config\ConfigModelManager;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Migration\EntityMetadataHelper;
use Oro\Bundle\EntityExtendBundle\Migration\ExtendOptionsManager;
use Oro\Bundle\EntityExtendBundle\Migration\OroOptions;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendConfigDumper;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendDbIdentifierNameGenerator;
use Oro\Bundle\EntityExtendBundle\Tools\ExtendHelper;
use Oro\Bundle\MigrationBundle\Migration\ParametrizedSqlMigrationQuery;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;
use Oro\Bundle\MigrationBundle\Tools\DbIdentifierNameGenerator;
use Oro\Bundle\MigrationBundle\Migration\Extension\NameGeneratorAwareInterface;

class ExtendExtension implements NameGeneratorAwareInterface
{
    const AUTO_GENERATED_ID_COLUMN_NAME = 'id';

    /**
     * @var ExtendOptionsManager
     */
    protected $extendOptionsManager;

    /**
     * @var EntityMetadataHelper
     */
    protected $entityMetadataHelper;

    /**
     * @var ExtendDbIdentifierNameGenerator
     */
    protected $nameGenerator;

    /**
     * @param ExtendOptionsManager $extendOptionsManager
     * @param EntityMetadataHelper $entityMetadataHelper
     */
    public function __construct(
        ExtendOptionsManager $extendOptionsManager,
        EntityMetadataHelper $entityMetadataHelper
    ) {
        $this->extendOptionsManager = $extendOptionsManager;
        $this->entityMetadataHelper = $entityMetadataHelper;
    }

    /**
     * @inheritdoc
     */
    public function setNameGenerator(DbIdentifierNameGenerator $nameGenerator)
    {
        $this->nameGenerator = $nameGenerator;
    }

    /**
     * Creates a table for a custom entity.
     * The custom entity is an entity which has no PHP class in any bundle. The definition of such entity is
     * created automatically in Symfony cache
     *
     * @param Schema $schema
     * @param string $entityName
     * @param array  $options
     *
     * @return Table
     *
     * @throws \InvalidArgumentException
     */
    public function createCustomEntityTable(
        Schema $schema,
        $entityName,
        array $options = []
    ) {
        $className = ExtendConfigDumper::ENTITY . $entityName;
        $tableName = $this->nameGenerator->generateCustomEntityTableName($className);
        $table     = $schema->createTable($tableName);

        $options = new OroOptions($options);
        // set options
        $options->setAuxiliary(ExtendOptionsManager::ENTITY_CLASS_OPTION, $className);
        if ($options->has('extend', 'owner')) {
            if ($options->get('extend', 'owner') !== ExtendScope::OWNER_CUSTOM) {
                throw new \InvalidArgumentException(
                    sprintf('The "extend.owner" option for a custom entity must be "%s".', ExtendScope::OWNER_CUSTOM)
                );
            }
        } else {
            $options->set('extend', 'owner', ExtendScope::OWNER_CUSTOM);
        }
        if ($options->has('extend', 'is_extend')) {
            if ($options->get('extend', 'is_extend') !== true) {
                throw new \InvalidArgumentException(
                    'The "extend.is_extend" option for a custom entity must be TRUE.'
                );
            }
        } else {
            $options->set('extend', 'is_extend', true);
        }
        $table->addOption(OroOptions::KEY, $options);

        // add a primary key
        $table->addColumn(self::AUTO_GENERATED_ID_COLUMN_NAME, 'integer', ['autoincrement' => true]);
        $table->setPrimaryKey([self::AUTO_GENERATED_ID_COLUMN_NAME]);

        return $table;
    }

    /**
     * Creates new enum entity.
     * This method adds all necessary entities and data to register new enum type.
     * It includes:
     *  - add a record to oro_enum table
     *  - create a table that is used to store enum values for the given enum.
     *
     * @param Schema   $schema
     * @param QueryBag $queries
     * @param string   $enumCode
     * @param bool     $isPublic Indicates whether this enum can be used by any entity or
     *                           it is designed to use in one entity only
     *
     * @return Table A table that is used to store enum values
     *
     * @throws \InvalidArgumentException
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     */
    public function createEnum(
        Schema $schema,
        QueryBag $queries,
        $enumCode,
        $isPublic = false
    ) {
        // add a record to oro_enum table
        $queries->addQuery(
            new ParametrizedSqlMigrationQuery(
                'INSERT INTO oro_enum (code, is_public) VALUES (:code, :is_public)',
                ['code' => $enumCode, 'is_public' => $isPublic],
                ['code' => Type::STRING, 'is_public' => Type::BOOLEAN]
            )
        );

        // create a table to store enum values
        $tableName = $this->nameGenerator->generateEnumTableName($enumCode);
        $className = ExtendConfigDumper::ENTITY . ExtendHelper::buildEnumValueShortClassName($enumCode);
        $table     = $schema->createTable($tableName);
        $table->addOption(
            OroOptions::KEY,
            [
                ExtendOptionsManager::MODE_OPTION         => ConfigModelManager::MODE_READONLY,
                ExtendOptionsManager::ENTITY_CLASS_OPTION => $className,
                'entity'                                  => [
                    'label'        => ExtendHelper::getEnumTranslationKey('label', $enumCode),
                    'plural_label' => ExtendHelper::getEnumTranslationKey('plural_label', $enumCode),
                    'description'  => ExtendHelper::getEnumTranslationKey('description', $enumCode)
                ],
                'extend'                                  => [
                    'owner'     => ExtendScope::OWNER_SYSTEM,
                    'is_extend' => true,
                    'table'     => $tableName,
                    'inherit'   => 'Oro\Bundle\EntityExtendBundle\Entity\AbstractEnumValue',
                ],
                'grouping'                                => [
                    'groups' => ['enum', 'dictionary']
                ],
                'dictionary'                              => [
                    'virtual_fields' => ['code', 'name']
                ]
            ]
        );

        $table->addColumn(
            'code',
            'string',
            [
                'length'      => 32,
                'oro_options' => [
                    'entity' => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'code'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'code')
                    ]
                ]
            ]
        );
        $table->addColumn(
            'enum_id',
            'integer',
            [
                'oro_options' => [
                    ExtendOptionsManager::FIELD_NAME_OPTION => 'enum',
                    ExtendOptionsManager::TYPE_OPTION       => 'ref-one',
                    'entity'                                => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'enum'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'enum')
                    ]
                ]
            ]
        );
        $table->addColumn(
            'name',
            'string',
            [
                'length'      => 255,
                'oro_options' => [
                    'entity' => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'name'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'name')
                    ]
                ]
            ]
        );
        $table->addColumn(
            'priority',
            'integer',
            [
                'oro_options' => [
                    'entity' => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'priority'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'priority')
                    ]
                ]
            ]
        );
        $table->addColumn(
            'is_default',
            'boolean',
            [
                'oro_options' => [
                    ExtendOptionsManager::FIELD_NAME_OPTION => 'default',
                    'entity'                                => [
                        'label'       => ExtendHelper::getEnumTranslationKey('label', $enumCode, 'default'),
                        'description' => ExtendHelper::getEnumTranslationKey('description', $enumCode, 'default')
                    ]
                ]
            ]
        );
        $table->setPrimaryKey(['code']);
        $table->addUniqueIndex(
            ['enum_id', 'code'],
            $this->nameGenerator->generateIndexName($table->getName(), ['enum_id', 'code'], true)
        );
        $table->addIndex(
            ['enum_id'],
            $this->nameGenerator->generateIndexName($table->getName(), ['enum_id'])
        );
        $table->addForeignKeyConstraint(
            $schema->getTable('oro_enum'),
            ['enum_id'],
            ['id'],
            ['onDelete' => 'CASCADE']
        );

        return $table;
    }

    /**
     * Adds OptionSet column
     *
     * @param Schema       $schema
     * @param Table|string $table A Table object or table name
     * @param string       $optionSetName
     * @param array        $options
     *
     * @deprecated since 1.4. Will be removed in 2.0
     */
    public function addOptionSet(
        Schema $schema,
        $table,
        $optionSetName,
        array $options = []
    ) {
        $this->ensureExtendFieldSet($options);

        $options[ExtendOptionsManager::TYPE_OPTION] = 'optionSet';
        $this->extendOptionsManager->setColumnOptions(
            $this->getTableName($table),
            $optionSetName,
            $options
        );
    }

    /**
     * Adds one-to-many relation
     *
     * @param Schema       $schema
     * @param Table|string $table                     A Table object or table name
     * @param string       $associationName           A relation name
     * @param Table|string $targetTable               A Table object or table name
     * @param string[]     $targetTitleColumnNames    Column names are used to show a title of related entity
     * @param string[]     $targetDetailedColumnNames Column names are used to show detailed info about related entity
     * @param string[]     $targetGridColumnNames     Column names are used to show related entity in a grid
     * @param array        $options
     */
    public function addOneToManyRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        array $targetTitleColumnNames,
        array $targetDetailedColumnNames,
        array $targetGridColumnNames,
        array $options = []
    ) {
        $this->ensureExtendFieldSet($options);

        $selfTableName            = $this->getTableName($table);
        $selfTable                = $this->getTable($table, $schema);
        $selfClassName            = $this->getEntityClassByTableName($selfTableName);
        $selfPrimaryKeyColumnName = $this->getPrimaryKeyColumnName($selfTable);
        $selfPrimaryKeyColumn     = $selfTable->getColumn($selfPrimaryKeyColumnName);

        $targetTableName            = $this->getTableName($targetTable);
        $targetTable                = $this->getTable($targetTable, $schema);
        $targetColumnName           = $this->nameGenerator
            ->generateOneToManyRelationColumnName($selfClassName, $associationName);
        $targetPrimaryKeyColumnName = $this->getPrimaryKeyColumnName($targetTable);
        $this->checkColumnsExist($targetTable, $targetTitleColumnNames);
        $this->checkColumnsExist($targetTable, $targetDetailedColumnNames);
        $this->checkColumnsExist($targetTable, $targetGridColumnNames);

        if (!isset($options['extend']['without_default']) || !$options['extend']['without_default']) {
            $selfColumnName         = $this->nameGenerator->generateRelationDefaultColumnName($associationName);
            $targetPrimaryKeyColumn = $targetTable->getColumn($targetPrimaryKeyColumnName);
            $this->addRelationColumn($selfTable, $selfColumnName, $targetPrimaryKeyColumn, ['notnull' => false]);
            $selfTable->addUniqueIndex([$selfColumnName]);
            $selfTable->addForeignKeyConstraint(
                $targetTable,
                [$selfColumnName],
                [$targetPrimaryKeyColumnName],
                ['onDelete' => 'SET NULL']
            );
        }

        $this->addRelationColumn($targetTable, $targetColumnName, $selfPrimaryKeyColumn, ['notnull' => false]);
        $targetTable->addIndex([$targetColumnName]);
        $targetTable->addForeignKeyConstraint(
            $selfTable,
            [$targetColumnName],
            [$selfPrimaryKeyColumnName],
            ['onDelete' => 'SET NULL']
        );

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name' => $targetTableName,
            'columns'    => [
                'title'    => $targetTitleColumnNames,
                'detailed' => $targetDetailedColumnNames,
                'grid'     => $targetGridColumnNames,
            ],
        ];

        $options[ExtendOptionsManager::TYPE_OPTION] = 'oneToMany';
        $this->extendOptionsManager->setColumnOptions(
            $selfTableName,
            $associationName,
            $options
        );
    }

    /**
     * Adds many-to-many relation
     *
     * @param Schema       $schema
     * @param Table|string $table                     A Table object or table name
     * @param string       $associationName           A relation name
     * @param Table|string $targetTable               A Table object or table name
     * @param string[]     $targetTitleColumnNames    Column names are used to show a title of related entity
     * @param string[]     $targetDetailedColumnNames Column names are used to show detailed info about related entity
     * @param string[]     $targetGridColumnNames     Column names are used to show related entity in a grid
     * @param array        $options
     */
    public function addManyToManyRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        array $targetTitleColumnNames,
        array $targetDetailedColumnNames,
        array $targetGridColumnNames,
        array $options = []
    ) {
        $this->ensureExtendFieldSet($options);

        $selfTableName            = $this->getTableName($table);
        $selfTable                = $this->getTable($table, $schema);
        $selfClassName            = $this->getEntityClassByTableName($selfTableName);
        $selfRelationName         = $this->nameGenerator->generateManyToManyRelationColumnName($selfClassName);
        $selfPrimaryKeyColumnName = $this->getPrimaryKeyColumnName($selfTable);
        $selfPrimaryKeyColumn     = $selfTable->getColumn($selfPrimaryKeyColumnName);

        $targetTableName            = $this->getTableName($targetTable);
        $targetTable                = $this->getTable($targetTable, $schema);
        $targetClassName            = $this->getEntityClassByTableName($targetTableName);
        $targetRelationName         = $this->nameGenerator->generateManyToManyRelationColumnName($targetClassName);
        $targetPrimaryKeyColumnName = $this->getPrimaryKeyColumnName($targetTable);
        $targetPrimaryKeyColumn     = $targetTable->getColumn($targetPrimaryKeyColumnName);

        $this->checkColumnsExist($targetTable, $targetTitleColumnNames);
        $this->checkColumnsExist($targetTable, $targetDetailedColumnNames);
        $this->checkColumnsExist($targetTable, $targetGridColumnNames);

        if (!isset($options['extend']['without_default']) || !$options['extend']['without_default']) {
            $selfColumnName = $this->nameGenerator->generateRelationDefaultColumnName($associationName);
            $this->addRelationColumn($selfTable, $selfColumnName, $targetPrimaryKeyColumn, ['notnull' => false]);
            $selfTable->addUniqueIndex([$selfColumnName]);
            $selfTable->addForeignKeyConstraint(
                $targetTable,
                [$selfColumnName],
                [$targetPrimaryKeyColumnName],
                ['onDelete' => 'SET NULL']
            );
        }

        $relationsTableName = $this->nameGenerator->generateManyToManyJoinTableName(
            $selfClassName,
            $associationName,
            $targetClassName
        );
        $relationsTable     = $schema->createTable($relationsTableName);
        $this->addRelationColumn($relationsTable, $selfRelationName, $selfPrimaryKeyColumn);
        $relationsTable->addIndex([$selfRelationName]);
        $relationsTable->addForeignKeyConstraint(
            $selfTable,
            [$selfRelationName],
            [$selfPrimaryKeyColumnName],
            ['onDelete' => 'CASCADE']
        );
        $this->addRelationColumn($relationsTable, $targetRelationName, $targetPrimaryKeyColumn);
        $relationsTable->addIndex([$targetRelationName]);
        $relationsTable->addForeignKeyConstraint(
            $targetTable,
            [$targetRelationName],
            [$targetPrimaryKeyColumnName],
            ['onDelete' => 'CASCADE']
        );
        $relationsTable->setPrimaryKey([$selfRelationName, $targetRelationName]);

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name' => $targetTableName,
            'columns'    => [
                'title'    => $targetTitleColumnNames,
                'detailed' => $targetDetailedColumnNames,
                'grid'     => $targetGridColumnNames,
            ],
        ];

        $options[ExtendOptionsManager::TYPE_OPTION] = 'manyToMany';
        $this->extendOptionsManager->setColumnOptions(
            $selfTableName,
            $associationName,
            $options
        );
    }

    /**
     * Adds many-to-one relation
     *
     * @param Schema       $schema
     * @param Table|string $table            A Table object or table name
     * @param string       $associationName  A relation name
     * @param Table|string $targetTable      A Table object or table name
     * @param string       $targetColumnName A column name is used to show related entity
     * @param array        $options
     */
    public function addManyToOneRelation(
        Schema $schema,
        $table,
        $associationName,
        $targetTable,
        $targetColumnName,
        array $options = []
    ) {
        $this->ensureExtendFieldSet($options);

        $selfTableName  = $this->getTableName($table);
        $selfTable      = $this->getTable($table, $schema);
        $selfColumnName = $this->nameGenerator->generateManyToOneRelationColumnName($associationName);

        $targetTableName            = $this->getTableName($targetTable);
        $targetTable                = $this->getTable($targetTable, $schema);
        $targetPrimaryKeyColumnName = $this->getPrimaryKeyColumnName($targetTable);
        $targetPrimaryKeyColumn     = $targetTable->getColumn($targetPrimaryKeyColumnName);
        $this->checkColumnsExist($targetTable, [$targetColumnName]);

        $this->addRelationColumn($selfTable, $selfColumnName, $targetPrimaryKeyColumn, ['notnull' => false]);
        $selfTable->addIndex([$selfColumnName]);
        $selfTable->addForeignKeyConstraint(
            $targetTable,
            [$selfColumnName],
            [$targetPrimaryKeyColumnName],
            ['onDelete' => 'SET NULL']
        );

        $options[ExtendOptionsManager::TARGET_OPTION] = [
            'table_name' => $targetTableName,
            'column'     => $targetColumnName,
        ];

        $options[ExtendOptionsManager::TYPE_OPTION] = 'manyToOne';
        $this->extendOptionsManager->setColumnOptions(
            $selfTableName,
            $associationName,
            $options
        );
    }

    /**
     * Gets an entity full class name by a table name
     *
     * @param string $tableName
     * @return string|null
     */
    public function getEntityClassByTableName($tableName)
    {
        return $this->entityMetadataHelper->getEntityClassByTableName($tableName);
    }

    /**
     * @param Table|string $table A Table object or table name
     * @return string
     */
    protected function getTableName($table)
    {
        return $table instanceof Table ? $table->getName() : $table;
    }

    /**
     * @param Table|string $table A Table object or table name
     * @param Schema       $schema
     * @return Table
     */
    protected function getTable($table, Schema $schema)
    {
        return $table instanceof Table ? $table : $schema->getTable($table);
    }

    /**
     * @param Table    $table
     * @param string[] $columnNames
     * @throws \InvalidArgumentException if $columnNames array is empty
     * @throws SchemaException if any column is not exist
     */
    protected function checkColumnsExist($table, array $columnNames)
    {
        if (empty($columnNames)) {
            throw new \InvalidArgumentException('At least one column must be specified.');
        }
        foreach ($columnNames as $columnName) {
            $table->getColumn($columnName);
        }
    }

    /**
     * @param Table $table
     * @return string
     * @throws SchemaException if valid primary key does not exist
     */
    protected function getPrimaryKeyColumnName(Table $table)
    {
        if (!$table->hasPrimaryKey()) {
            throw new SchemaException(
                sprintf('The table "%s" must have a primary key.', $table->getName())
            );
        }
        $primaryKeyColumns = $table->getPrimaryKey()->getColumns();
        if (count($primaryKeyColumns) !== 1) {
            throw new SchemaException(
                sprintf('A primary key of "%s" table must include only one column.', $table->getName())
            );
        }

        return array_pop($primaryKeyColumns);
    }

    /**
     * @param Table  $table
     * @param string $columnName
     * @param Column $targetColumn
     * @param array  $options
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    protected function addRelationColumn(Table $table, $columnName, Column $targetColumn, array $options = [])
    {
        $columnTypeName = $targetColumn->getType()->getName();
        if (!in_array($columnTypeName, [Type::INTEGER, Type::SMALLINT, Type::BIGINT])) {
            throw new SchemaException(
                sprintf('A relation column type must be an integer. "%s" type is not supported.', $columnTypeName)
            );
        }

        $table->addColumn($columnName, $columnTypeName, $options);
    }

    /**
     * @param array $options
     */
    protected function ensureExtendFieldSet(array &$options)
    {
        if (!isset($options['extend'])) {
            $options['extend'] = [];
        }
        if (!isset($options['extend']['extend'])) {
            $options['extend']['extend'] = true;
        }
    }
}
