<?php

namespace Oro\Bundle\EntityExtendBundle\Extend\Schema;

use Doctrine\DBAL\Schema\Schema as BaseSchema;
use Doctrine\DBAL\Schema\SchemaConfig;

class Schema extends BaseSchema
{
    /**
     * @var ExtendOptionManager
     */
    protected $extendOptionManager;

    /**
     * @param ExtendOptionManager $extendOptionManager
     * @param array               $tables
     * @param array               $sequences
     * @param SchemaConfig        $schemaConfig
     */
    public function __construct(
        ExtendOptionManager $extendOptionManager,
        array $tables = [],
        array $sequences = [],
        SchemaConfig $schemaConfig = null
    ) {
        $this->extendOptionManager = $extendOptionManager;

        $extendTables = [];
        foreach ($tables as $table) {
            $extendTables[] = new Table($this->extendOptionManager, $table);
        }

        parent::__construct($extendTables, $sequences, $schemaConfig);
    }

    /**
     * {@inheritdoc}
     */
    public function createTable($tableName)
    {
        return new Table($this->extendOptionManager, parent::createTable($tableName));
    }
}
