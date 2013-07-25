<?php

namespace Oro\Bundle\EntityExtendBundle\Controller;

use Oro\Bundle\EntityExtendBundle\Command\BackupCommand;
use Oro\Bundle\EntityExtendBundle\Command\GenerateCommand;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Console\Input\ArgvInput;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\ConsoleOutput;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\UserBundle\Annotation\Acl;

use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;

use Oro\Bundle\EntityConfigBundle\Entity\ConfigEntity;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigField;

use Oro\Bundle\EntityExtendBundle\Tools\Schema;

/**
 * EntityExtendBundle controller.
 * @Route("/oro_entityextend")
 */
class ApplyController extends Controller
{
    /**
     * View Apply
     * @Route(
     *      "/apply/{id}",
     *      name="oro_entityextend_apply",
     *      requirements={"id"="\d+"},
     *      defaults={"id"=0}
     * )
     * @ Acl(
     *      id="oro_entityextend_apply",
     *      name="Apply changes",
     *      description="Apply entityconfig changes",
     *      parent="oro_entityextend"
     * )
     * @Template()
     */
    public function applyAction($id)
    {
        /** @var ConfigEntity $entity */
        $entity  = $this->getDoctrine()->getRepository(ConfigEntity::ENTITY_NAME)->find($id);

        /** @var ConfigProvider $entityConfigProvider */
        $entityConfigProvider = $this->get('oro_entity.config.entity_config_provider');

        /** @var ConfigProvider $extendConfigProvider */
        $extendConfigProvider = $this->get('oro_entity_extend.config.extend_config_provider');
        $extendConfig = $extendConfigProvider->getConfig($entity->getClassName());

        /** @var Schema $schemaTools */
        $schemaTools = $this->get('oro_entity_extend.tools.schema');

        /**
         * do Validations
         */
        $validation = array();

        $fields = $extendConfig->getFields();
        foreach ($fields as $code => $field) {
            $isSystem = $schemaTools->checkFieldIsSystem($field);

            if ($isSystem) {
                continue;
            }

            if (in_array($field->get('state'), array('New', 'Applied', 'Updated', 'To be deleted'))) {
                $isValid = $schemaTools->checkFieldCanDelete($field);
                if ($isValid) {
                    $validation['success'][] = sprintf(
                        "Field '%s(%s)' is valid. State -> %s",
                        $code,
                        $isSystem ? 'System' : 'Custom',
                        $field->get('state')
                    );
                } else {
                    $validation['error'][] = sprintf(
                        "Field '%s(%s)' has data, any schema changes can broke it. ",
                        $code,
                        $isSystem ? 'System' : 'Custom'
                    );
                }
            }
        }

        return array(
            'validations'   => $validation,
            'entity'        => $entity,
            'entity_config' => $entityConfigProvider->getConfig($entity->getClassName()),
            'entity_extend' => $extendConfig,
        );
    }

    /**
     * View Apply
     * @Route(
     *      "/update/{id}",
     *      name="oro_entityextend_update",
     *      requirements={"id"="\d+"},
     *      defaults={"id"=0}
     * )
     * @ Acl(
     *      id="oro_entityextend_update",
     *      name="Apply changes",
     *      description="Apply entityconfig changes",
     *      parent="oro_entityextend"
     * )
     * @Template()
     */
    public function updateAction($id)
    {
        /** @var ConfigEntity $entity */
        $entity  = $this->getDoctrine()->getRepository(ConfigEntity::ENTITY_NAME)->find($id);

        /** @var BackupCommand $backupCommand */
        $backupCommand = $this->get('oro_entity_extend.command.backup');

        /**
         * do Backup
         */
        $input = new ArrayInput(array('entity' => $entity->getClassName()));
        $output = new ConsoleOutput();
        $backupCommand->run($input, $output);

        /**
         * do Generation
         */

        /** @var GenerateCommand $generatorCommand */
        $generatorCommand = $this->get('oro_entity_extend.command.generate');

        $input = new ArgvInput();
        $output = new ConsoleOutput();
        $generatorCommand->run($input, $output);

        /**
         * do Schema update
         */
        $kernel = $this->get('kernel');
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        //Create de Schema
        $options = array('command' => 'doctrine:schema:update',"--force" => true);
        $application->run(new \Symfony\Component\Console\Input\ArrayInput($options));
        //Loading Fixtures
        //$options = array('command' => 'doctrine:fixtures:load',"--append" => true);
        //$application->run(new \Symfony\Component\Console\Input\ArrayInput($options));

        return $this->redirect($this->generateUrl('oro_entityconfig_view', array('id' => $id)));
    }
}
