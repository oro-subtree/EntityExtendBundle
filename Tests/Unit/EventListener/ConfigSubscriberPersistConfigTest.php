<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\EventListener;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Config\Id\FieldConfigId;
use Oro\Bundle\EntityConfigBundle\Event\PersistConfigEvent;
use Oro\Bundle\EntityConfigBundle\Event\Events;

use Oro\Bundle\EntityExtendBundle\EventListener\ConfigSubscriber;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;

class ConfigSubscriberPersistConfigTest extends \PHPUnit_Framework_TestCase
{
    /** @var  ConfigSubscriber */
    protected $configSubscriber;

    /** @var PersistConfigEvent */
    protected $event;

    public function testGetSubscribedEvents()
    {
        $this->assertEquals(
            [
                Events::PRE_PERSIST_CONFIG   => 'persistConfig',
                Events::NEW_ENTITY_CONFIG    => 'updateEntityConfig',
                Events::UPDATE_ENTITY_CONFIG => 'updateEntityConfig',
                Events::NEW_FIELD_CONFIG     => 'newFieldConfig',
                Events::RENAME_FIELD         => 'renameField',
            ],
            ConfigSubscriber::getSubscribedEvents()
        );
    }

    /**
     * Test that persistConfig called with event
     * that has config id something other than FieldConfigId
     */
    public function testWrongConfigId()
    {
        $configProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $configProvider->expects($this->never())
            ->method($this->anything());

        $configManager = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()
            ->getMock();
        $configManager->expects($this->never())
            ->method($this->anything());

        $entityConfigId = new EntityConfigId('extend', 'TestClass');
        $eventConfig    = new Config($entityConfigId);

        $event = new PersistConfigEvent($eventConfig, $configManager);
        $configSubscriber = new ConfigSubscriber($configProvider);

        $configSubscriber->persistConfig($event);
    }

    /**
     *  Test create new field (entity state is 'NEW', owner - Custom)
     *  Nothing should be persisted
     */
    public function testScopeExtendNewFieldNewEntity()
    {
        $this->runPersistConfig(
            $this->getEventConfigNewField(),
            $this->getEntityConfig(),
            $this->getChangeSet()
        );

        /** @var ConfigManager $cm */
        $cm = $this->event->getConfigManager();

        $this->assertAttributeSame(null, 'persistConfigs', $cm);
    }

    /**
     *  Test create new field (entity state is 'Active')
     *  ConfigManager should have persisted 'extend_TestClass' with state 'Requires update'
     */
    public function testScopeExtendNewFieldActiveEntity()
    {
        $this->runPersistConfig(
            $this->getEventConfigNewField(),
            $this->getEntityConfig(['state' => ExtendScope::STATE_ACTIVE]),
            $this->getChangeSet()
        );

        /** @var ConfigManager $cm */
        $cm = $this->event->getConfigManager();
        $this->assertAttributeEquals(
            ['extend_TestClass' => $this->getEntityConfig(['state' => ExtendScope::STATE_UPDATED])],
            'persistConfigs',
            $cm
        );
    }

    /**
     *  Test create new field (relation type [1:*])
     */
    public function testScopeExtendRelationTypeCreateSelfRelationOneToMany()
    {
        $this->runPersistConfig(
            $this->getEventConfigNewField(
                [
                    'state' => ExtendScope::STATE_NEW,
                    'target_entity' => 'Oro\Bundle\UserBundle\Entity\User',
                ],
                'oneToMany'
            ),
            $this->getEntityConfig(['state' => ExtendScope::STATE_ACTIVE]),
            ['state' => [0 => null, 1 => ExtendScope::STATE_NEW]]
        );

        /** @var ConfigManager $cm */
        $cm = $this->event->getConfigManager();

        $this->assertAttributeEquals(
            [
                'extend_TestClass' => $this->getEntityConfig(
                    [
                        'state' => ExtendScope::STATE_UPDATED,
                        'relation' => [
                            'oneToMany|TestClass|Oro\Bundle\UserBundle\Entity\User|testFieldName' => [
                                'assign' => false,
                                'field_id' => new FieldConfigId(
                                    'extend',
                                    'Oro\Bundle\UserBundle\Entity\User',
                                    'testclass_testFieldName',
                                    'manyToOne'
                                ),
                                'owner' => true,
                                'target_entity' => 'TestClass',
                                'target_field_id' => new FieldConfigId(
                                    'extend',
                                    'TestClass',
                                    'testFieldName',
                                    'oneToMany'
                                ),
                            ]
                        ]
                    ]
                )
            ],
            'persistConfigs',
            $cm
        );
    }

    /**
     *  Test create new field (relation type [*:1])
     */
    public function testScopeExtendRelationTypeCreateSelfRelationManyToOne()
    {
        $this->runPersistConfig(
            $this->getEventConfigNewField(
                [
                    'state' => ExtendScope::STATE_NEW,
                    'target_entity' => 'Oro\Bundle\UserBundle\Entity\User',
                ],
                'manyToOne'
            ),
            $this->getEntityConfig(['state' => ExtendScope::STATE_ACTIVE]),
            ['state' => [0 => null, 1 => ExtendScope::STATE_NEW]]
        );

        /** @var ConfigManager $cm */
        $cm = $this->event->getConfigManager();

        $this->assertAttributeEquals(
            [
                'extend_TestClass' => $this->getEntityConfig(
                    [
                        'state' => ExtendScope::STATE_UPDATED,
                        'relation' => [
                            'manyToOne|TestClass|Oro\Bundle\UserBundle\Entity\User|testFieldName' => [
                                'assign' => false,
                                'field_id' => false,
                                'owner' => false,
                                'target_entity' => 'TestClass',
                                'target_field_id' => new FieldConfigId(
                                    'extend',
                                    'TestClass',
                                    'testFieldName',
                                    'manyToOne'
                                ),
                            ]
                        ]
                    ]
                )
            ],
            'persistConfigs',
            $cm
        );
    }

    /**
     *  Test create new field (relation type [*:*])
     */
    public function testScopeExtendRelationTypeCreateSelfRelationManyToMany()
    {
        $this->runPersistConfig(
            $this->getEventConfigNewField(
                [
                    'target_entity' => 'Oro\Bundle\UserBundle\Entity\User',
                ],
                'manyToMany'
            ),
            $this->getEntityConfig(['state' => ExtendScope::STATE_ACTIVE]),
            ['state' => [0 => null, 1 => ExtendScope::STATE_NEW]]
        );

        /** @var ConfigManager $cm */
        $cm = $this->event->getConfigManager();

        $this->assertAttributeEquals(
            [
                'extend_TestClass' => $this->getEntityConfig(
                    [
                        'state' => ExtendScope::STATE_UPDATED,
                        'relation' => [
                            'manyToMany|TestClass|Oro\Bundle\UserBundle\Entity\User|testFieldName' => [
                                'assign' => false,
                                'field_id' => new FieldConfigId(
                                    'extend',
                                    'Oro\Bundle\UserBundle\Entity\User',
                                    'testclass_testFieldName',
                                    'manyToMany'
                                ),
                                'owner' => false,
                                'target_entity' => 'TestClass',
                                'target_field_id' => new FieldConfigId(
                                    'extend',
                                    'TestClass',
                                    'testFieldName',
                                    'manyToMany'
                                ),
                            ]
                        ]
                    ]
                )
            ],
            'persistConfigs',
            $cm
        );
    }

    /**
     * Test create new field (relation type [*:*])
     */
    public function testScopeExtendRelationTypeCreateTargetRelationManyToMany()
    {
        $relationKey   = 'manyToMany|TestClass|Oro\Bundle\UserBundle\Entity\User|testFieldName';
        $this->runPersistConfig(
            $this->getEventConfigNewField(
                [
                    'target_entity' => 'Oro\Bundle\UserBundle\Entity\User',
                    'target_title'    => ['username'],
                    'target_grid'     => ['username'],
                    'target_detailed' => ['username'],
                    'relation_key'    => $relationKey,
                ],
                'manyToMany'
            ),
            $this->getEntityConfig(
                [
                    'state' => ExtendScope::STATE_ACTIVE,
                    'relation'    => [
                        'manyToMany|TestClass|Oro\Bundle\UserBundle\Entity\User|testFieldName' => [
                            'assign'          => true,
                            'owner'           => true,
                            'target_entity'   => 'TestClass',
                            'field_id'        => new FieldConfigId(
                                'extend',
                                'TestClass',
                                'testFieldName',
                                'manyToMany'
                            )
                        ]
                    ],
                ]
            ),
            ['state' => [0 => ExtendScope::STATE_ACTIVE, 1 => ExtendScope::STATE_UPDATED ]]
        );

        /** @var ConfigManager $cm */
        $cm = $this->event->getConfigManager();

        $this->assertAttributeEquals(
            [
                'extend_TestClass' => $this->getEntityConfig(
                    [
                        'state'    => ExtendScope::STATE_UPDATED,
                        'relation' => [
                            'manyToMany|TestClass|Oro\Bundle\UserBundle\Entity\User|testFieldName' => [
                                'assign'          => true,
                                'field_id'        => new FieldConfigId(
                                    'extend',
                                    'TestClass',
                                    'testFieldName',
                                    'manyToMany'
                                ),
                                'owner'           => true,
                                'target_entity'   => 'TestClass',
                            ]
                        ]
                    ]
                )
            ],
            'persistConfigs',
            $cm
        );
    }

    /**
     *  Test create new 1:* relation field to same entity
     *  Should NOT be persisted
     */
    public function testScopeExtendRelationTypeOwnEntity()
    {
        $this->runPersistConfig(
            $this->getEventConfigNewField([], 'oneToMany'),
            $test = $this->getEntityConfig(
                [
                    'state' => ExtendScope::STATE_NEW,
                    'relation' => [
                        'oneToMany|TestClass|TestClass|testFieldName' => [
                            'assign' => false,
                            'field_id' => new FieldConfigId('extend', 'TestClass', 'testFieldName', 'oneToMany'),
                            'owner' => true,
                            'target_entity' => 'Oro\Bundle\UserBundle\Entity\User',
                        ]
                    ],

                ]
            ),
            [
                'state' => [0 => null, 1 => ExtendScope::STATE_NEW]
            ]
        );

        /** @var ConfigManager $cm */
        $cm = $this->event->getConfigManager();
        $this->assertAttributeEquals(null, 'persistConfigs', $cm);
    }

    /**
     * FieldConfig
     *
     * @param array $values
     * @param string $type
     * @param string $scope
     *
     * @return Config
     */
    protected function getEventConfigNewField($values = [], $type = 'string', $scope = 'extend')
    {
        $resultValues = [
            'owner'      => ExtendScope::OWNER_CUSTOM,
            'state'      => ExtendScope::STATE_NEW,
            'is_extend'  => true,
            'is_deleted' => false,
        ];

        if (count($values)) {
            $resultValues = array_merge($resultValues, $values);
        }

        $fieldConfigId = new FieldConfigId($scope, 'TestClass', 'testFieldName', $type);
        $eventConfig   = new Config($fieldConfigId);
        $eventConfig->setValues($resultValues);

        return $eventConfig;
    }

    /**
     * EntityConfig
     *
     * @param array $values
     * @param string $scope
     * @return Config
     */
    protected function getEntityConfig($values = [], $scope = 'extend')
    {
        $resultValues = [
            'owner'       => ExtendScope::OWNER_CUSTOM,
            'is_extend'   => true,
            'state'       => ExtendScope::STATE_NEW,
            'is_deleted'  => false,
            'upgradeable' => false,
            'relation'    => [],
            'schema'      => [],
            'index'       => []
        ];

        if (count($values)) {
            $resultValues = array_merge($resultValues, $values);
        }

        $entityConfigId = new EntityConfigId($scope, 'TestClass');
        $entityConfig   = new Config($entityConfigId);
        $entityConfig->setValues($resultValues);

        return $entityConfig;
    }

    protected function getChangeSet($values = [])
    {
        return array_merge(
            [
                'owner'     => [0 => null, 1 => ExtendScope::OWNER_CUSTOM],
                'is_extend' => [0 => null, 1 => true],
                'state'     => [0 => null, 1 => ExtendScope::STATE_NEW]
            ],
            $values
        );
    }

    protected function runPersistConfig($eventConfig, $entityConfig, $changeSet)
    {
        $configProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();

        $configProvider
            ->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($entityConfig));
        $configProvider
            ->expects($this->any())
            ->method('getConfigById')
            ->will($this->returnValue($eventConfig));

        $configManager = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()
            ->setMethods(['getProviderBag', 'getProvider', 'getConfigChangeSet'])
            ->getMock();
        $configManager
            ->expects($this->any())
            ->method('getProvider')
            ->with('extend')
            ->will($this->returnValue($configProvider));
        $configManager
            ->expects($this->any())
            ->method('getConfigChangeSet')
            ->with($eventConfig)
            ->will($this->returnValue($changeSet));

        $this->event = new PersistConfigEvent($eventConfig, $configManager);

        $extendConfigProvider = clone $configProvider;
        $extendConfigProvider
            ->expects($this->any())
            ->method('getConfig')
            ->will($this->returnValue($eventConfig));

        $this->configSubscriber = new ConfigSubscriber($extendConfigProvider);
        $this->configSubscriber->persistConfig($this->event);
    }
}
