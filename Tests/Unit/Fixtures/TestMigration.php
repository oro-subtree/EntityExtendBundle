<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Fixtures;

use Doctrine\DBAL\Schema\Schema;

use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtension;
use Oro\Bundle\EntityExtendBundle\Migration\Extension\ExtendExtensionAwareInterface;
use Oro\Bundle\MigrationBundle\Migration\Migration;
use Oro\Bundle\MigrationBundle\Migration\QueryBag;

class TestMigration implements Migration, ExtendExtensionAwareInterface
{
    /** @var ExtendExtension */
    protected $extend;

    /**
     * @inheritdoc
     */
    public function setExtendExtension(ExtendExtension $extend)
    {
        $this->extend = $extend;
    }

    /**
     * @inheritdoc
     */
    public function up(Schema $schema, QueryBag $queries)
    {
        $table = $schema->getTable('oro_user_access_role');

        $table->addColumn(
            'test_column',
            'integer',
            []
        );

        $this->extend->addManyToOneRelation(
            $schema,
            $table,
            'rel_m2o',
            'oro_user',
            'username',
            ['extend' => ['owner' => 'Custom', 'is_extend' => true]]
        );
    }
}
