<?php

namespace Claroline\CoreBundle\Controller\Tool;

use Doctrine\ORM\EntityRepository;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;

class UserController extends Controller
{
    /*******************/
    /* USER MANAGEMENT */
    /*******************/
    const ABSTRACT_WS_CLASS = 'ClarolineCoreBundle:Workspace\AbstractWorkspace';
    const NUMBER_USER_PER_ITERATION = 25;

    /**
     * @Route(
     *     "/{workspaceId}/users/unregistered",
     *     name="claro_workspace_unregistered_users_list",
     *     requirements={"workspaceId"="^(?=.*[1-9].*$)\d*$" }
     * )
     * @Method("GET")
     *
     * Renders the unregistered user list layout for a workspace.
     *
     * @param integer $workspaceId the workspace id
     *
     * @return Response
     */
    public function unregiseredUsersListAction($workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)->find($workspaceId);
        $this->checkRegistration($workspace);

        return $this->render(
            'ClarolineCoreBundle:Tool\workspace\user_management:unregistered_user_list_layout.html.twig',
            array('workspace' => $workspace)
        );
    }

    /**
     * @Route(
     *     "/{workspaceId}/user/{userId}",
     *     name="claro_workspace_tools_show_user_parameters",
     *     requirements={"workspaceId"="^(?=.*[1-9].*$)\d*$", "userId"="^(?=.*[1-9].*$)\d*$" },
     *     options={"expose"=true}
     * )
     *
     * @Route(
     *     "/{workspaceId}/user/{userId}",
     *     name="claro_workspace_tools_edit_user_parameters",
     *     requirements={"workspaceId"="^(?=.*[1-9].*$)\d*$", "userId"="^(?=.*[1-9].*$)\d*$" },
     *     options={"expose"=true}
     * )
     * @Method({"POST", "GET"})
     *
     * Renders the user parameter page with its layout and
     * edit the user parameters for the selected workspace.
     *
     * @param integer $workspaceId the workspace id
     * @param integer $userId     the user id
     *
     * @return Response
     */
    public function userParametersAction($workspaceId, $userId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)
            ->find($workspaceId);
        $this->checkRegistration($workspace);
        $user = $em->getRepository('ClarolineCoreBundle:User')
            ->find($userId);
        $roleRepo = $em->getRepository('ClarolineCoreBundle:Role');
        $role = $roleRepo->findWorkspaceRoleForUser($user, $workspace);
        $defaultData = array('role' => $role);
        $form = $this->createFormBuilder($defaultData, array('translation_domain' => 'platform'))
            ->add(
                'role',
                'entity',
                array(
                    'class' => 'Claroline\CoreBundle\Entity\Role',
                    'property' => 'translationKey',
                    'query_builder' => function (EntityRepository $er) use ($workspaceId) {
                        return $er->createQueryBuilder('wr')
                            ->select('role')
                            ->from('Claroline\CoreBundle\Entity\Role', 'role')
                            ->leftJoin('role.workspace', 'workspace')
                            ->where('workspace.id = :workspaceId')
                            ->andWhere("role.name != 'ROLE_ANONYMOUS'")
                            ->setParameter('workspaceId', $workspaceId);
                    }
                )
            )
            ->getForm();

        if ($this->getRequest()->getMethod() === 'POST') {
            $request = $this->getRequest();
            $parameters = $request->request->all();
            //cannot bind request: why ?
            $newRole = $roleRepo->find($parameters['form']['role']);

            if ($newRole->getId() != $roleRepo->findManagerRole($workspace)->getId()) {
                $this->checkRemoveManagerRoleIsValid(array ($userId), $workspace);
            }

            $user->removeRole($role);
            $user->addRole($newRole);
            $em->persist($user);
            $em->flush();
            $route = $this->get('router')->generate(
                'claro_workspace_open_tool',
                array('workspaceId' => $workspaceId, 'toolName' => 'user_management')
            );

            return new RedirectResponse($route);
        }

        return $this->render(
            'ClarolineCoreBundle:Tool\workspace\user_management:user_parameters.html.twig',
            array(
                'workspace' => $workspace,
                'user' => $user,
                'form' => $form->createView()
            )
        );
    }

    /**
     * @Route(
     *     "/{workspaceId}/user/search/{search}/registered/{offset}",
     *     name="claro_workspace_search_registered_users",
     *     requirements={"workspaceId"="^(?=.*[1-9].*$)\d*$", "offset"="^(?=.*[0-9].*$)\d*$"},
     *     options={"expose"=true}
     * )
     * @Method("GET")
     *
     * Returns a partial json representation of the registered users of a workspace.
     * It'll search every users whose name match $search.
     *
     * @param string  $search      the search string
     * @param integer $workspaceId the workspace id
     * @param integer $offset      the offset
     *
     * @return Response
     */
    public function searchRegisteredUsersAction($search, $workspaceId, $offset)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)
            ->find($workspaceId);
        $this->checkRegistration($workspace);
        // TODO: quick fix (force doctrine to reload only the concerned roles
        // -- otherwise all the roles loaded by the security context are returned)
        $em->detach($this->get('security.context')->getToken()->getUser());
        $paginatorUsers = $em->getRepository('ClarolineCoreBundle:User')
            ->findByWorkspaceAndName(
                $workspace,
                $search,
                $offset,
                self::NUMBER_USER_PER_ITERATION
            );
        $users = $this->paginatorToArray($paginatorUsers);
        $response = new Response($this->get('claroline.resource.converter')->jsonEncodeUsers($users));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @Route(
     *     "/{workspaceId}/user/search/{search}/unregistered/{offset}",
     *     name="claro_workspace_search_unregistered_users",
     *     requirements={"workspaceId"="^(?=.*[1-9].*$)\d*$", "offset"="^(?=.*[0-9].*$)\d*$" },
     *     options={"expose"=true}
     * )
     * @Method("GET")
     *
     * Returns a partial json representation of the unregistered users of a workspace.
     * It'll search every users whose name match $search.
     *
     * @param string  $search      the search string
     * @param integer $workspaceId the workspace id
     * @param integer $offset      the offset
     *
     * @return Response
     */
    public function searchUnregisteredUsersAction($search, $workspaceId, $offset)
    {
        $em = $this->getDoctrine()->getManager();
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)
            ->find($workspaceId);
        $this->checkRegistration($workspace);
        // TODO: quick fix (force doctrine to reload only the concerned roles -
        // - otherwise all the roles loaded by the security context are returned)
        $em->detach($this->get('security.context')->getToken()->getUser());
        $paginatorUsers = $em->getRepository('ClarolineCoreBundle:User')
            ->findWorkspaceOutsidersByName(
                $search,
                $workspace,
                $offset,
                self::NUMBER_USER_PER_ITERATION
            );
        $users = $this->paginatorToArray($paginatorUsers);
        $response = new Response($this->get('claroline.resource.converter')->jsonEncodeUsers($users));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @Route(
     *     "/{workspaceId}/add/user",
     *     name="claro_workspace_multiadd_user",
     *     options={"expose"=true},
     *     requirements={"workspaceId"="^(?=.*[1-9].*$)\d*$"}
     * )
     * @Method("PUT")
     *
     * Adds many users to a workspace.
     * It uses a query string of userIds as parameter (userIds[]=1&userIds[]=2)
     *
     * @param integer $workspaceId the workspace id
     *
     * @return Response
     */
    public function addUsersAction($workspaceId)
    {
        $params = $this->get('request')->query->all();
        $users = array();
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)
            ->find($workspaceId);
        $this->checkRegistration($workspace);
        $roleRepo = $em->getRepository('ClarolineCoreBundle:Role');

        if (isset($params['ids'])) {

            foreach ($params['ids'] as $userId) {
                $user = $em->find('ClarolineCoreBundle:User', $userId);
                //We only add the role if the user isn't already registered.
                $userRole = $roleRepo->findWorkspaceRoleForUser($user, $workspace);
                if ($userRole === null) {
                    $users[] = $user;
                    $user->addRole($roleRepo->findCollaboratorRole($workspace));
                }
            }
            $em->flush();
        }

        $response = new Response($this->get('claroline.resource.converter')->jsonEncodeUsers($users));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @Route(
     *     "/{workspaceId}/users/{offset}/registered",
     *     name="claro_workspace_registered_users_paginated",
     *     options={"expose"=true},
     *     requirements={"workspaceId"="^(?=.*[0-9].*$)\d*$", "offset"="^(?=.*[0-9].*$)\d*$"}
     * )
     * @Method("GET")
     *
     * Returns a partial json representation of the registered users of a workspace.
     *
     * @param integer $workspaceId the workspace id
     * @param integer $offset      the offset
     *
     * @return Response
     */
    public function registeredUsersAction($workspaceId, $offset)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)
            ->find($workspaceId);
        $this->checkRegistration($workspace);
        // TODO: quick fix (force doctrine to reload only the concerned roles
        // -- otherwise all the roles loaded by the security context are returned)
        $em->detach($this->get('security.context')->getToken()->getUser());
        $paginatorUsers = $em->getRepository('ClarolineCoreBundle:User')
            ->findByWorkspace(
                $workspace,
                $offset,
                self::NUMBER_USER_PER_ITERATION
            );
        $users = $this->paginatorToArray($paginatorUsers);
        $response = new Response($this->get('claroline.resource.converter')->jsonEncodeUsers($users));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @Route(
     *     "/{workspaceId}/users/{offset}/unregistered",
     *     name="claro_workspace_unregistered_users_paginated",
     *     options={"expose"=true},
     *     requirements={"workspaceId"="^(?=.*[1-9].*$)\d*$", "offset"="^(?=.*[0-9].*$)\d*$" }
     * )
     * @Method("GET")
     *
     * Returns a partial json representation of the unregistered users of a workspace.
     *
     * @param integer $workspaceId the workspace id
     * @param integer $offset      the offset
     *
     * @return Response
     */
    public function unregisteredUsersAction($workspaceId, $offset)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)
            ->find($workspaceId);
        $this->checkRegistration($workspace);
        // TODO: quick fix (force doctrine to reload only the concerned roles
        // -- otherwise all the roles loaded by the security context are returned)
        $em->detach($this->get('security.context')->getToken()->getUser());
        $paginatorUsers = $em->getRepository('ClarolineCoreBundle:User')
            ->findWorkspaceOutsiders(
                $workspace,
                $offset,
                self::NUMBER_USER_PER_ITERATION
            );
        $users = $this->paginatorToArray($paginatorUsers);
        $response = new Response($this->get('claroline.resource.converter')->jsonEncodeUsers($users));
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * @Route(
     *     "/{workspaceId}/users",
     *     name="claro_workspace_delete_users",
     *     options={"expose"=true},
     *     requirements={"workspaceId"="^(?=.*[1-9].*$)\d*$"}
     * )
     * @Method("DELETE")
     *
     * Removes many users from a workspace.
     * It uses a query string of groupIds as parameter (userIds[]=1&userIds[]=2)
     *
     * @param integer $workspaceId the workspace id
     *
     * @return Response
     */
    public function removeUsersAction($workspaceId)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $workspace = $em->getRepository(self::ABSTRACT_WS_CLASS)
            ->find($workspaceId);
        $this->checkRegistration($workspace);
        $roles = $em->getRepository('ClarolineCoreBundle:Role')
            ->findByWorkspace($workspace);
        $params = $this->get('request')->query->all();

        if (isset($params['ids'])) {
            $this->checkRemoveManagerRoleIsValid($params['ids'], $workspace);
            foreach ($params['ids'] as $userId) {

                $user = $em->find('ClarolineCoreBundle:User', $userId);

                if (null != $user) {
                    foreach ($roles as $role) {
                        $user->removeRole($role);
                    }
                }
            }
        }

        $em->flush();

        return new Response("success", 204);
    }

    /**
     * Checks if the role manager of the user can be changed.
     * There should be awlays at least one manager of a workspace.
     *
     * @param array $userIds an array of user ids
     * @param AbstractWorkspace $workspace the relevant workspace
     *
     * @throws LogicException
     */
    private function checkRemoveManagerRoleIsValid($userIds, $workspace)
    {
        $em = $this->get('doctrine.orm.entity_manager');
        $countRemovedManagers = 0;
        $managerRole = $em->getRepository('ClarolineCoreBundle:Role')
            ->findManagerRole($workspace);

        foreach ($userIds as $userId) {
            $user = $em->find('ClarolineCoreBundle:User', $userId);

            if (null !== $user) {
                if ($workspace == $user->getPersonalWorkspace()) {
                    throw new LogicException("You can't remove the original manager from a personal workspace");
                }
                if ($user->hasRole($managerRole->getName())) {
                    $countRemovedManagers++;
                }
            }
        }

        $userManagers = $em->getRepository('ClarolineCoreBundle:User')
            ->findByWorkspaceAndRole($workspace, $managerRole);
        $countUserManagers = count($userManagers);

        if ($countRemovedManagers >= $countUserManagers) {
            throw new LogicException(
                "You can't remove every managers (you're trying to remove {$countRemovedManagers} "
                . "manager(s) out of {$countUserManagers})"
            );
        }
    }

    /**
     * Checks if the current user can see a workspace.
     *
     * @param AbstractWorkspace $workspace
     *
     * @throws AccessDeniedHttpException
     */
    private function checkRegistration($workspace)
    {
        if (!$this->get('security.context')->isGranted('user_management', $workspace)) {
            throw new AccessDeniedHttpException();
        }
    }

    /**
     * Most dql request required by this controller are paginated.
     * This function transform the results of the repository in an array.
     *
     * @param Paginator $paginator the return value of the Repository using a paginator.
     *
     * @return array.
     */
    private function paginatorToArray($paginator)
    {
        return $this->get('claroline.utilities.paginator_parser')
            ->paginatorToArray($paginator);
    }
}
