<?php

namespace Oro\Bundle\EntityExtendBundle\Migration;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\EntityExtendBundle\Extend\Schema\ExtendOptionManager;
use Oro\Bundle\EntityExtendBundle\Extend\Schema\ExtendSchema;
use Oro\Bundle\EntityExtendBundle\Extend\Schema\ExtendTable;
use Oro\Bundle\MigrationBundle\Migration\MigrationQueryBuilder;

class ExtendMigrationQueryBuilder extends MigrationQueryBuilder
{
    /**
     * @var ExtendOptionManager
     */
    protected $extendOptionManager;

    public function setExtendOptionManager(ExtendOptionManager $extendOptionManager)
    {
        $this->extendOptionManager = $extendOptionManager;
    }

    /**
     * {@inheritdoc}
     */
    protected function getSchema()
    {
        $sm        = $this->connection->getSchemaManager();
        $platform  = $this->connection->getDatabasePlatform();
        $sequences = array();
        if ($platform->supportsSequences()) {
            $sequences = $sm->listSequences();
        }
        $tables = $sm->listTables();

        return new ExtendSchema(
            $this->extendOptionManager,
            $tables,
            $sequences,
            $sm->createSchemaConfig()
        );
    }

    protected function cloneSchema(Schema $schema)
    {
        /** @var ExtendSchema $result */
        $result = parent::cloneSchema($schema);

        /** @var ExtendTable[] $tables */
        $tables = $result->getTables();
        foreach ($tables as $table) {
            $table->setSchema($result);
        }

        return $result;
    }
}