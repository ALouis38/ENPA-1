<?php

namespace Claroline\CoreBundle\Controller;

use \Exception;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Claroline\CoreBundle\Entity\Resource\AbstractResource;
use Claroline\CoreBundle\Entity\Resource\ResourceShortcut;
use Claroline\CoreBundle\Entity\Resource\Directory;
use Claroline\CoreBundle\Library\Resource\ResourceCollection;
use Claroline\CoreBundle\Library\Resource\Event\CreateResourceEvent;
use Claroline\CoreBundle\Library\Resource\Event\CreateFormResourceEvent;
use Claroline\CoreBundle\Library\Resource\Event\CustomActionResourceEvent;
use Claroline\CoreBundle\Library\Logger\Event\ResourceLogEvent;
use Claroline\CoreBundle\Library\Resource\Event\OpenResourceEvent;

class ResourceController extends Controller
{
    const THUMB_PER_PAGE = 12;

    /**
     * Renders the creation form for a given resource type.
     *
     * @param string $resourceType the resource type
     *
     * @return Response
     */
    public function creationFormAction($resourceType)
    {
        $eventName = $this->get('claroline.resource.utilities')
            ->normalizeEventName('create_form', $resourceType);
        $event = new CreateFormResourceEvent();
        $this->get('event_dispatcher')->dispatch($eventName, $event);

        return new Response($event->getResponseContent());
    }

    /**
     * Creates a resource.
     *
     * @param string  $resourceType the resource type
     * @param integer $parentId     the parent id
     *
     * @return Response
     */
    public function createAction($resourceType, $parentId)
    {
        $parent = $this->getDoctrine()
            ->getEntityManager()
            ->getRepository('ClarolineCoreBundle:Resource\AbstractResource')
            ->find($parentId);
        $collection = new ResourceCollection(array($parent));
        $collection->setAttributes(array('type' => $resourceType));
        $this->checkAccess('CREATE', $collection);

        $eventName = $this->get('claroline.resource.utilities')
            ->normalizeEventName('create', $resourceType);
        $event = new CreateResourceEvent($resourceType);
        $this->get('event_dispatcher')->dispatch($eventName, $event);
        $response = new Response();

        if (($resource = $event->getResource()) instanceof AbstractResource) {
            $manager = $this->get('claroline.resource.manager');
            $resource = $manager->create($resource, $parentId, $resourceType);
            $response->headers->set('Content-Type', 'application/json');
            $response->setContent(
                $this->get('claroline.resource.converter')
                    ->toJson($resource, $this->get('security.context')->getToken())
            );
        } else {
            if ($event->getErrorFormContent() != null) {
                $response->setContent($event->getErrorFormContent());
            } else {
                throw new \Exception('creation failed');
            }
        }

        return $response;
    }

    /**
     * Opens a resource.
     *
     * @param integer $resourceId  the resource id
     * @param string $resourceType the resource type
     *
     * @return Response
     *
     * @throws AccessDeniedException
     * @throws \Exception
     */
    public function openAction($resourceId, $resourceType)
    {
        $em = $this->getDoctrine()->getEntityManager();
        $resource = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
            ->find($resourceId);
        $collection = new ResourceCollection(array($resource));
        //If it's a link, the resource will be its target.
        $resource = $this->getResource($resource);
        $this->checkAccess('OPEN', $collection);
        $resource = $this->getResource($resource);
        $event = new OpenResourceEvent($resource);
        $eventName = $this->get('claroline.resource.utilities')
            ->normalizeEventName('open', $resourceType);
        $this->get('event_dispatcher')->dispatch($eventName, $event);
        $resource = $this->getResource($resource);

        if (!$event->getResponse() instanceof Response) {
            throw new \Exception(
                "Open event '{$eventName}' didn't return any Response."
            );
        }

        $resource = $this->getResource($resource);
        $logEvent = new ResourceLogEvent($resource, 'open');
        $this->get('event_dispatcher')->dispatch('log_resource', $logEvent);

        return $event->getResponse();
    }

    /**
     * Removes a many resources from a workspace.
     * Takes an array of ids as parameters (query string: "ids[]=1&ids[]=2" ...).
     *
     * @return Response
     */
    public function deleteAction()
    {
        $ids = $this->container->get('request')->query->get('ids', array());
        $em = $this->getDoctrine()->getEntityManager();
        $collection = new ResourceCollection();

        foreach ($ids as $id) {
            $resource = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
                ->find($id);

            if ($resource != null) {
                $collection->addResource($resource);
            }
        }

        $this->checkAccess('DELETE', $collection);

        foreach ($collection->getResources() as $resource) {
            $this->get('claroline.resource.manager')->delete($resource);
        }

        return new Response('Resource deleted', 204);
    }

    /**
     * Moves many resource (changes their parents). This function takes an array
     * of parameters which are the ids of the moved resources
     * (query string: "ids[]=1&ids[]=2" ...).
     *
     * @return Response
     */
    public function moveAction($newParentId)
    {
        $ids = $this->container->get('request')->query->get('ids', array());
        $em = $this->getDoctrine()->getEntityManager();
        $resourceRepo = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource');
        $newParent = $resourceRepo->find($newParentId);
        $resourceManager = $this->get('claroline.resource.manager');
        $movedResources = array();
        $collection = new ResourceCollection();

        foreach ($ids as $id) {
            $resource = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
                ->find($id);

            if ($resource !== null) {
                $collection->addResource($resource);
            }
        }

        $collection->addAttribute('parent', $newParent);

        $this->checkAccess('MOVE', $collection);

        foreach ($ids as $id) {
            $resource = $resourceRepo->find($id);

            if ($resource != null) {
                try {
                    $movedResource = $resourceManager->move($resource, $newParent);
                    $movedResources[] = $this->get('claroline.resource.converter')->toArray(
                        $movedResource,
                        $this->get('security.context')->getToken()
                    );
                } catch (\Gedmo\Exception\UnexpectedValueException $e) {
                    throw new \RuntimeException('Cannot move a resource into itself');
                }
            }
        }

        $response = new Response(json_encode($movedResources));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Handles any custom action (i.e. not defined in this controller) on a
     * resource of a given type.
     *
     * @param string $resourceType the resource type
     * @param string $action       the action
     * @param integer $resourceId  the resourceId
     *
     * @return Response
     */
    public function customAction($resourceType, $action, $resourceId)
    {
        $eventName = $this->get('claroline.resource.utilities')
            ->normalizeEventName($action, $resourceType);
        $em = $this->get('doctrine.orm.entity_manager');
        $resource = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
            ->find($resourceId);
        //$collection = new ResourceCollection(array($resource));

        $event = new CustomActionResourceEvent($resource);
        $this->get('event_dispatcher')->dispatch($eventName, $event);

        if (!$event->getResponse() instanceof Response) {
            throw new \Exception(
                "Custom event '{$eventName}' didn't return any Response."
            );
        }

        $ri = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
            ->find($resourceId);
        $logevent = new ResourceLogEvent($ri, $action);
        $this->get('event_dispatcher')->dispatch('log_resource', $logevent);

        return $event->getResponse();
    }

    /**
     * This function takes an array of parameters. Theses parameters are the ids
     * of the resources which are going to be downloaded
     * (query string: "ids[]=1&ids[]=2" ...).
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function exportAction()
    {
        $ids = $this->container->get('request')->query->get('ids', array());

        $collection = new ResourceCollection();

        foreach ($ids as $id) {
            $resource = $this->get('doctrine.orm.entity_manager')
                ->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
                ->find($id);

            if ($resource != null) {
                $collection->addResource($resource);
            }
        }

        $this->checkAccess('EXPORT', $collection);

        $file = $this->get('claroline.resource.exporter')->exportResources($ids);
        $response = new StreamedResponse();

        $response->setCallBack(
            function () use ($file) {
                readfile($file);
            }
        );

        $response->headers->set('Content-Transfer-Encoding', 'octet-stream');
        $response->headers->set('Content-Type', 'application/force-download');
        $response->headers->set('Content-Disposition', 'attachment; filename=archive');
        $response->headers->set('Content-Type', 'application/zip');
        $response->headers->set('Connection', 'close');

        return $response;
    }

    /**
     * Returns a json representation of a directory, containing the following items :
     * - The path of the directory
     * - The resource types the user is allowed to create in the directory
     * - The immediate children resources of the directory which are visible for the user
     *
     * If the directory id is '0', a pseudo-directory containing the root directories
     * of the workspaces whose the user is a member is returned.
     * If the directory id is a shortcut id, the directory targeted by the shortcut
     * is returned.
     *
     * @param integer $directoryId the directory id
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws Exception if the id doesnt't match any existing directory
     */
    public function openDirectoryAction($directoryId)
    {
        $path = array();
        $creatableTypes = array();
        $resources = array();
        $em = $this->getDoctrine()->getEntityManager();
        $user = $this->get('security.context')->getToken()->getUser();
        $resourceRepo = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource');
        $directoryId = (integer) $directoryId;
        $currentRoles = $this->get('claroline.security.utilities')
            ->getRoles($this->get('security.context')->getToken());

        if ($directoryId === 0) {
            $resources = $resourceRepo->findWorkspaceRootsByUser($user);
        } else {
            $directory = $this->getResource($resourceRepo->find($directoryId));

            if (null === $directory || !$directory instanceof Directory) {
                throw new Exception("Cannot find any directory with id '{$directoryId}'");
            }

            $path = $resourceRepo->findAncestors($directory);
            $resources = $resourceRepo->findChildren($directory->getId(), $currentRoles, 0, true);

            $creationRights = $em->getRepository('Claroline\CoreBundle\Entity\Resource\ResourceRights')
                ->findCreationRights($currentRoles, $directory);

            if (count($creationRights) != 0) {
                $translator = $this->get('translator');

                foreach ($creationRights as $type) {
                    $creatableTypes[$type['name']] = $translator->trans($type['name'], array(), 'resource');
                }
            }
        }

        $response = new Response(
            json_encode(
                array(
                    'path' => $path,
                    'creatableTypes' => $creatableTypes,
                    'resources' => $resources
                )
            )
        );
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Adds multiple resource resource to a workspace.
     * Needs an array of ids to be functionnal (query string: "ids[]=1&ids[]=2" ...).
     *
     * @param integer $resourceDestinationId the new parent id.
     *
     * @return Response
     */
    public function copyAction($resourceDestinationId)
    {
        $ids = $this->container->get('request')->query->get('ids', array());
        $token = $this->get('security.context')->getToken();
        $em = $this->getDoctrine()->getEntityManager();
        $parent = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
            ->find($resourceDestinationId);
        $newNodes = array();
        $resources = array();

        foreach ($ids as $id) {
            $resource = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource')
                ->find($id);

            if ($resource != null) {
                $resources[] = $resource;
            }
        }

        $collection = new ResourceCollection($resources);
        $collection->addAttribute('parent', $parent);

        $this->checkAccess('COPY', $collection);

        foreach ($resources as $resource) {
            $newNode = $this->get('claroline.resource.manager')->copy($resource, $parent);
            $em->persist($newNode);
            $em->flush();
            $em->refresh($parent);
            $newNodes[] = $this->get('claroline.resource.converter')->toArray($newNode, $token);
        }

        $response = new Response(json_encode($newNodes));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Returns a json representation of a resource search result.
     *
     * @param integer $directoryId The id of the directory from which the search was started
     *
     * @return Response
     */
    public function filterAction($directoryId)
    {
        $queryParameters = $this->container->get('request')->query->all();
        $criteria = $this->buildSearchArray($queryParameters);

        isset($criteria['roots']) || $criteria['roots'] = array();
        $resourceRepo = $this->get('doctrine.orm.entity_manager')
            ->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource');
        $directoryId = (integer) $directoryId;
        $path = array();

        if ($directoryId !== 0) {
            $directory = $this->getResource($resourceRepo->find($directoryId));

            if (null === $directory || !$directory instanceof Directory) {
                throw new Exception("Cannot find any directory with id '{$directoryId}'");
            }

            $path = $resourceRepo->findAncestors($directory);
        }

        $user = $this->get('security.context')->getToken()->getUser();
        $resources = $resourceRepo->findUserResourceByCriteria($user, $criteria);
        $response = new Response(json_encode(array('resources' => $resources, 'path' => $path)));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Creates (one or several) shortcuts.
     * Takes an array of ids to be functionnal (query string: "ids[]=1&ids[]=2" ...).
     *
     * @param $newParentId the shortcut parent id
     *
     * @return Response
     */
    public function createShortcutAction($newParentId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $repo = $em->getRepository('Claroline\CoreBundle\Entity\Resource\AbstractResource');
        $ids = $this->container->get('request')->query->get('ids', array());
        $parent = $repo->find($newParentId);

        foreach ($ids as $resourceId) {
            $resource = $repo->find($resourceId);
            $shortcut = new ResourceShortcut();
            $shortcut->setParent($parent);
            $creator = $this->get('security.context')->getToken()->getUser();
            $shortcut->setCreator($creator);
            $shortcut->setIcon($resource->getIcon()->getShortcutIcon());
            $shortcut->setName($resource->getName());
            $shortcut->setName($this->get('claroline.resource.utilities')->getUniqueName($shortcut, $parent));
            $shortcut->setWorkspace($parent->getWorkspace());
            $shortcut->setResourceType($resource->getResourceType());

            if (get_class($resource) !== 'Claroline\CoreBundle\Entity\Resource\ResourceShortcut') {
                $shortcut->setResource($resource);
            } else {
                $shortcut->setResource($resource->getResource());
            }

            $this->get('claroline.resource.manager')->setResourceRights($shortcut->getParent(), $shortcut);
//            $this->get('claroline.resource.manager')->setResourceRights($shortcut->getParent(), $resource);

            $em->persist($shortcut);
            $em->flush();
            $em->refresh($parent);

            $links[] = $this->get('claroline.resource.converter')->toArray(
                $shortcut,
                $this->get('security.context')->getToken()
            );
        }

        $response = new Response(json_encode($links));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    private function getResource($resource)
    {
        if (get_class($resource) === 'Claroline\CoreBundle\Entity\Resource\ResourceShortcut') {
            $resource = $resource->getResource();
        }

        return $resource;
    }

    private function buildSearchArray($queryParameters)
    {
        $allowedStringCriteria = array('name', 'dateFrom', 'dateTo');
        $allowedArrayCriteria = array('roots', 'types');
        $criteria = array();

        foreach ($queryParameters as $parameter => $value) {
            if (in_array($parameter, $allowedStringCriteria) && is_string($value)) {
                $criteria[$parameter] = $value;
            } elseif (in_array($parameter, $allowedArrayCriteria) && is_array($value)) {
                $criteria[$parameter] = $value;
            }
        }

        return $criteria;
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
     * @param string $permission
     * @param ResourceCollection $collection
     *
     * @throws AccessDeniedException
     */
    private function checkAccess($permission, ResourceCollection $collection)
    {
        if (!$this->get('security.context')->isGranted($permission, $collection)) {
            throw new AccessDeniedException($collection->getErrorsForDisplay());
        }
    }
}