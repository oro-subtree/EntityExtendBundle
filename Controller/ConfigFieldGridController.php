<?php

namespace Oro\Bundle\EntityExtendBundle\Controller;

use FOS\Rest\Util\Codes;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;

use Oro\Bundle\EntityConfigBundle\Entity\ConfigField;
use Oro\Bundle\EntityConfigBundle\Entity\ConfigEntity;
use Oro\Bundle\EntityConfigBundle\ConfigManager;
use Oro\Bundle\EntityConfigBundle\Provider\ConfigProvider;

use Oro\Bundle\EntityExtendBundle\Extend\ExtendManager;
use Oro\Bundle\EntityExtendBundle\Form\Type\FieldType;

use Oro\Bundle\EntityExtendBundle\Tools\Schema;

/**
 * Class ConfigGridController
 * @package Oro\Bundle\EntityExtendBundle\Controller
 * @Route("/entityextend/field")
 */
class ConfigFieldGridController extends Controller
{
    /**
     * @Route(
     *      "/create/{id}",
     *      name="oro_entityextend_field_create",
     *      requirements={"id"="\d+"},
     *      defaults={"id"=0}
     * )
     * @Template
     */
    public function createAction(ConfigEntity $entity)
    {
        /** @var ExtendManager $extendManager */
        $extendManager = $this->get('oro_entity_extend.extend.extend_manager');

        if (!$extendManager->isExtend($entity->getClassName())) {
            $this->get('session')->getFlashBag()->add('error', $entity->getClassName() . 'isn\'t extend');

            return $this->redirect(
                $this->generateUrl('oro_entityconfig_fields', array('id' => $entity->getId()))
            );
        }

        $request = $this->getRequest();
        $data    = array(
            'options' => array(
                'extend' => array(
                    'owner' => 'Custom',
                )
            )
        );
        $form    = $this->createForm(new FieldType(), $data, array('class_name' => $entity->getClassName()));

        if ($request->getMethod() == 'POST') {
            $form->submit($request);

            if ($form->isValid()) {
                $data  = $form->getData();
                $error = false;


                if (!$data['code']) {
                    $error = true;
                    $form->get('code')->addError(new FormError(sprintf("Field '%s' should by set", $data['code'])));
                }
                if (!$data['type']) {
                    $error = true;
                    $form->get('type')->addError(new FormError(sprintf("Field '%s' should by set", $data['code'])));
                }

                if ($entity->getField($data['code'])) {
                    $error = true;
                    $form->get('code')->addError(
                        new FormError(
                            sprintf(
                                "Field '%s' already exist in entity '%s', ",
                                $data['code'],
                                $entity->getClassName()
                            )
                        )
                    );
                }

                if (!$error) {
                    /** @var ConfigManager $configManager */
                    $configManager = $this->get('oro_entity_config.config_manager');
                    $configManager->clearCache($entity->getClassName());

                    foreach ($data['options'] as $scope => $values) {
                        /** TODO:: remove this shit */
                        if ($scope == 'id') {
                            continue;
                        }

                        $configManager->getProvider($scope)->createFieldConfig($entity->getClassName(), $data['code'], $data['type'], $values);
                    }

                    $extendManager->getConfigFactory()->createFieldConfig($entity->getClassName(), $data);

                    $this->get('session')->getFlashBag()->add(
                        'success',
                        sprintf(
                            'field "%s" has been added to entity "%',
                            $data['code'],
                            $entity->getClassName()
                        )
                    );

                    return $this->redirect(
                        $this->generateUrl(
                            'oro_entityconfig_view',
                            array(
                                'id' => $entity->getId()
                            )
                        )
                    );
                }
            }
        }

        /** @var ConfigProvider $entityConfigProvider */
        $entityConfigProvider = $this->get('oro_entity.config.entity_config_provider');

        return array(
            'form'          => $form->createView(),
            'entity_id'     => $entity->getId(),
            'entity_config' => $entityConfigProvider->getConfig($entity->getClassName()),
        );
    }

    /**
     * @Route(
     *      "/remove/{id}",
     *      name="oro_entityextend_field_remove",
     *      requirements={"id"="\d+"},
     *      defaults={"id"=0}
     * )
     */
    public function removeAction(ConfigField $field)
    {
        if (!$field) {
            throw $this->createNotFoundException('Unable to find ConfigField entity.');
        }

        /** @var ExtendManager $extendManager */
        $extendManager = $this->get('oro_entity_extend.extend.extend_manager');

        /** @var Schema $schema */
        $schema = $this->get('oro_entity_extend.tools.schema');

        $fieldConfig = $extendManager->getConfigProvider()
            ->getFieldConfig($field->getEntity()->getClassName(), $field->getCode());

        if (!$fieldConfig->is('is_extend')) {
            return new Response('', Codes::HTTP_FORBIDDEN);
        }

        $this->getDoctrine()->getManager()->remove($field);
        $this->getDoctrine()->getManager()->flush($field);

        /** @var ConfigManager $configManager */
        $configManager = $this->get('oro_entity_config.config_manager');
        $configManager->clearCache($fieldConfig->getClassName());

        return new Response('', Codes::HTTP_NO_CONTENT);
    }
}
