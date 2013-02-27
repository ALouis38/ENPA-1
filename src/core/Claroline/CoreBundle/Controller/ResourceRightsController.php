<?php

namespace Claroline\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Claroline\CoreBundle\Library\Resource\ResourceCollection;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class ResourceRightsController extends Controller
{
    /**
     * Displays the resource rights form.
     *
     * @param integer $resourceId the resource id
     * @return Response
     * @throws AccessDeniedException if the current user is not allowed to edit the resource
     */
    public function rightFormAction($resourceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $resource = $em->getRepository('ClarolineCoreBundle:Resource\AbstractResource')
            ->find($resourceId);
        $collection = new ResourceCollection(array($resource));
        $this->checkAccess('EDIT', $collection);
        $roleRights = $em->getRepository('ClarolineCoreBundle:Resource\ResourceRights')
            ->findNonAdminRights($resource);

        $template = $resource->getResourceType()->getName() === 'directory' ?
            'ClarolineCoreBundle:Resource:rights_form_directory.html.twig' :
            'ClarolineCoreBundle:Resource:rights_form_resource.html.twig';

        return $this->render(
            $template,
            array('roleRights' => $roleRights, 'resource' => $resource)
        );
    }

    /**
     * Handles the submission of the resource rights form. Expects an array of permissions
     * by role to be passed by POST method. Permissions are set to false when not passed
     * in the request.
     *
     * @param integer $resourceId the resource id
     * @return Response
     * @throws AccessDeniedException if the current user is not allowed to edit the resource
     */
    public function editRightsAction($resourceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $resourceRepo = $em->getRepository('ClarolineCoreBundle:Resource\AbstractResource');
        $rightsRepo = $em->getRepository('ClarolineCoreBundle:Resource\ResourceRights');
        $resource = $resourceRepo->find($resourceId);
        $this->checkAccess('EDIT', new ResourceCollection(array($resource)));
        $parameters = $this->get('request')->request->all();
        $permissions = array('open', 'copy', 'delete', 'edit', 'export');
        $referenceRights = array();
        $targetResources = isset($parameters['isRecursive']) ?
            $resourceRepo->findDescendants($resource, true) :
            array($resource);

        for ($i = 0, $targetCount = count($targetResources); $i < $targetCount; ++$i) {
            $roleRights = $rightsRepo->findNonAdminRights($targetResources[$i]);

            for ($j = 0, $rightsCount = count($roleRights); $j < $rightsCount; ++$j) {
                foreach ($permissions as $permission) {
                    $i === 0 && $referenceRights[$j][$permission]
                        = isset($parameters['roleRights'][$roleRights[$j]->getId()][$permission]);
                    $setter = 'setCan' . ucfirst($permission);
                    $roleRights[$j]->{$setter}($referenceRights[$j][$permission]);
                }
            }
        }

        $em->flush();

        return new Response('success');
    }

    /**
     * Displays the form for resource creation rights (i.e the right to create a
     * type of resource in a directory). Show the different resource types already
     * allowed for creation.
     *
     * @param integer $resourceId the resource id
     * @param integer $roleId     the role for which the form is displayed
     * @return Response
     * @throws AccessDeniedException if the current user is not allowed to edit the resource
     */
    public function rightCreationFormAction($resourceId, $roleId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $resource = $em->getRepository('ClarolineCoreBundle:Resource\AbstractResource')
            ->find($resourceId);
        $collection = new ResourceCollection(array($resource));
        $this->checkAccess('EDIT', $collection);
        $role = $em->getRepository('ClarolineCoreBundle:Role')
            ->find($roleId);
        $config = $em->getRepository('ClarolineCoreBundle:Resource\ResourceRights')
            ->findOneBy(array('resource' => $resourceId, 'role' => $role));
        $resourceTypes = $em->getRepository('ClarolineCoreBundle:Resource\ResourceType')
            ->findBy(array('isVisible' => true));

        return $this->render(
            'ClarolineCoreBundle:Resource:rights_creation.html.twig',
            array(
                'configs' => array($config),
                'resourceTypes' => $resourceTypes,
                'resourceId' => $resourceId,
                'roleId' => $roleId
            )
        );
    }

    /**
     * Handles the submission of the resource rights creation form. Expects an
     * array of resource type ids to be passed by POST method. Only the types
     * passed in the request will be allowed.
     *
     * @param integer $resourceId the resource id
     * @param integer $roleId     the role for which the form is displayed
     * @return Response
     * @throws AccessDeniedException if the current user is not allowed to edit the resource
     */
    public function editCreationRightsAction($resourceId, $roleId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $resourceRepo = $em->getRepository('ClarolineCoreBundle:Resource\AbstractResource');
        $resource = $resourceRepo->find($resourceId);
        $collection = new ResourceCollection(array($resource));
        $this->checkAccess('EDIT', $collection);
        $parameters = $this->get('request')->request->all();
        $targetResources = isset($parameters['isRecursive']) ?
            $resourceRepo->findDescendants($resource, true) :
            array($resource);
        $resourceTypeIds = isset($parameters['resourceTypes']) ?
            array_keys($parameters['resourceTypes']) :
            array();

        $resourceTypes = $em->getRepository('ClarolineCoreBundle:Resource\ResourceType')
            ->findByIds($resourceTypeIds);

        foreach ($targetResources as $targetResource) {
            $resourceRights = $em->getRepository('ClarolineCoreBundle:Resource\ResourceRights')
                ->findOneBy(array('resource' => $targetResource, 'role' => $roleId));
            $resourceRights->setCreatableResourceTypes($resourceTypes);
        }

        $em->flush();

        $response = new Response();
        $response->headers->set('Content-Type', 'application/json');
        $response->setContent(
            $this->get('claroline.resource.converter')->toJson(
                $resource,
                $this->get('security.context')->getToken()
            )
        );

        return $response;
    }

    /**
     * Checks if the current user has the right to perform an action on a ResourceCollection.
     * Be careful, ResourceCollection may need some aditionnal parameters.
     *
     * - for CREATE: $collection->setAttributes(array('type' => $resourceType))
     *  where $resourceType is the name of the resource type.
     * - for MOVE / COPY $collection->setAttributes(array('parent' => $parent))
     *  where $parent is the new parent entity.
     *
     * @param string                $permission
     * @param ResourceCollection    $collection
     * @throws AccessDeniedException if the current user is not allowed to edit the resource
     */
    private function checkAccess($permission, ResourceCollection $collection)
    {
        if (!$this->get('security.context')->isGranted($permission, $collection)) {
            throw new AccessDeniedException($collection->getErrorsForDisplay());
        }
    }
}