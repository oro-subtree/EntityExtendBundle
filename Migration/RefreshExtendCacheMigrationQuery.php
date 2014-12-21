<?php

namespace Oro\Bundle\EntityExtendBundle\Migration;

use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Psr\Log\LoggerInterface;
use Oro\Bundle\EntityConfigBundle\Tools\CommandExecutor;
use Oro\Bundle\MigrationBundle\Migration\MigrationQuery;

class RefreshExtendCacheMigrationQuery implements MigrationQuery
{
    /**
     * @var CommandExecutor
     */
    protected $commandExecutor;

    /**
     * @param CommandExecutor $commandExecutor
     */
    public function __construct(CommandExecutor $commandExecutor)
    {
        $this->commandExecutor = $commandExecutor;
    }

    /**
     * {@inheritdoc}
     */
    public function getDescription()
    {
        return 'Refresh extend entity cache';
    }

    /**
     * {@inheritdoc}
     */
    public function execute(LoggerInterface $logger)
    {
        $this->commandExecutor->runCommand(
            'oro:entity-extend:update-config',
            [sprintf('--skip-origin=%s', ExtendScope::ORIGIN_CUSTOM)],
            $logger
        );
        $this->commandExecutor->runCommand(
            'oro:entity-extend:update-schema',
            [],
            $logger
        );
        $this->commandExecutor->runCommand(
            'oro:entity-extend:cache:clear',
            [],
            $logger
        );
    }
}
