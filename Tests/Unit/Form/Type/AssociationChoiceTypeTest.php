<?php

namespace Oro\Bundle\EntityExtendBundle\Tests\Unit\Form\Type;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\PreloadedExtension;
use Symfony\Component\Form\Test\TypeTestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

use Oro\Bundle\EntityConfigBundle\Config\Config;
use Oro\Bundle\EntityConfigBundle\Config\Id\EntityConfigId;
use Oro\Bundle\EntityConfigBundle\Form\Extension\ConfigExtension;
use Oro\Bundle\EntityExtendBundle\EntityConfig\ExtendScope;
use Oro\Bundle\EntityExtendBundle\Form\Type\AssociationChoiceType;

class AssociationChoiceTypeTest extends TypeTestCase
{
    /** @var \PHPUnit_Framework_MockObject_MockObject */
    protected $configManager;

    /** @var AssociationChoiceType */
    protected $type;

    protected function setUp()
    {
        $this->configManager = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Config\ConfigManager')
            ->disableOriginalConstructor()
            ->getMock();

        $entityClassResolver = $this->getMockBuilder('Oro\Bundle\EntityBundle\ORM\EntityClassResolver')
            ->disableOriginalConstructor()
            ->getMock();
        $entityClassResolver->expects($this->any())
            ->method('getEntityClass')
            ->will($this->returnArgument(0));

        $this->type = new AssociationChoiceType($this->configManager, $entityClassResolver);

        parent::setUp();
    }

    protected function getExtensions()
    {
        $configExtension = new ConfigExtension();

        return [
            new PreloadedExtension(
                [],
                [$configExtension->getExtendedType() => [$configExtension]]
            )
        ];
    }

    public function testSetDefaultOptions()
    {
        $resolver = new OptionsResolver();
        $this->type->setDefaultOptions($resolver);

        $this->assertEquals(
            [
                'empty_value'       => false,
                'choices'           => ['No', 'Yes'],
                'association_class' => null
            ],
            $resolver->resolve([])
        );
    }

    /**
     * @dataProvider submitProvider
     */
    public function testSubmit($newVal, $oldVal, $state, $isSetStateExpected)
    {
        $configId = new EntityConfigId('test', 'Test\Entity');
        $config = new Config($configId);
        $config->set('enabled', $oldVal);
        $extendConfigId = new EntityConfigId('extend', 'Test\Entity');
        $extendConfig = new Config($extendConfigId);
        $extendConfig->set('state', $state);
        $extendConfigProvider = $this->getMockBuilder('Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider')
            ->disableOriginalConstructor()
            ->getMock();
        $extendConfigProvider->expects($this->any())
            ->method('getConfig')
            ->with('Test\Entity')
            ->will($this->returnValue($extendConfig));
        $this->configManager->expects($this->once())
            ->method('getConfig')
            ->with($configId)
            ->will($this->returnValue($config));
        $this->configManager->expects($this->any())
            ->method('getProvider')
            ->with('extend')
            ->will($this->returnValue($extendConfigProvider));

        $expectedExtendConfig = new Config($extendConfigId);
        if ($isSetStateExpected) {
            $expectedExtendConfig->set('state', ExtendScope::STATE_UPDATED);
            $extendConfigProvider->expects($this->once())
                ->method('persist')
                ->with($expectedExtendConfig);
            $extendConfigProvider->expects($this->once())
                ->method('flush');
        } else {
            $expectedExtendConfig->set('state', $state);
            $extendConfigProvider->expects($this->never())
                ->method('persist');
            $extendConfigProvider->expects($this->never())
                ->method('flush');
        }

        $options  = [
            'config_id'         => new EntityConfigId('test', 'Test\Entity'),
            'association_class' => 'Test\AssocEntity'
        ];
        $form = $this->factory->createNamed('enabled', $this->type, $oldVal, $options);

        $form->submit($newVal);

        $this->assertTrue($form->isSynchronized());
        $this->assertEquals($expectedExtendConfig, $extendConfig);
    }

    public function submitProvider()
    {
        return [
            [false, false, ExtendScope::STATE_ACTIVE, false],
            [true, true, ExtendScope::STATE_ACTIVE, false],
            [false, true, ExtendScope::STATE_ACTIVE, false],
            [true, false, ExtendScope::STATE_ACTIVE, true],
            [true, false, ExtendScope::STATE_UPDATED, false],
        ];
    }

    public function testBuildView()
    {
        $view    = new FormView();
        $form    = new Form($this->getMock('Symfony\Component\Form\FormConfigInterface'));
        $options = [
            'config_id'         => new EntityConfigId('test', 'Test\Entity'),
            'association_class' => 'Test\AssocEntity'
        ];

        $this->type->buildView($view, $form, $options);

        $this->assertEquals(
            [
                'attr'  => [],
                'value' => null
            ],
            $view->vars
        );
    }

    public function testBuildViewForDisabled()
    {
        $view    = new FormView();
        $form    = new Form($this->getMock('Symfony\Component\Form\FormConfigInterface'));
        $options = [
            'config_id'         => new EntityConfigId('test', 'Test\Entity'),
            'association_class' => 'Test\Entity'
        ];

        $this->type->buildView($view, $form, $options);

        $this->assertEquals(
            [
                'disabled' => true,
                'attr'     => [
                    'class' => 'disabled-choice'
                ],
                'value'    => null
            ],
            $view->vars
        );
    }

    public function testBuildViewForDisabledWithCssClass()
    {
        $view    = new FormView();
        $form    = new Form($this->getMock('Symfony\Component\Form\FormConfigInterface'));
        $options = [
            'config_id'         => new EntityConfigId('test', 'Test\Entity'),
            'association_class' => 'Test\Entity'
        ];

        $view->vars['attr']['class'] = 'test-class';

        $this->type->buildView($view, $form, $options);

        $this->assertEquals(
            [
                'disabled' => true,
                'attr'     => [
                    'class' => 'test-class disabled-choice'
                ],
                'value'    => null
            ],
            $view->vars
        );
    }

    public function testGetName()
    {
        $this->assertEquals('oro_entity_extend_association_choice', $this->type->getName());
    }

    public function testGetParent()
    {
        $this->assertEquals('choice', $this->type->getParent());
    }
}
