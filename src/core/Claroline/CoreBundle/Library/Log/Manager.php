<?php
namespace Claroline\CoreBundle\Library\Log;

use JMS\DiExtraBundle\Annotation as DI;
use Pagerfanta\Adapter\DoctrineORMAdapter;
use Pagerfanta\Pagerfanta;
use Pagerfanta\Exception\NotValidCurrentPageException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Claroline\CoreBundle\Form\DataTransformer\DateRangeToTextTransformer;
use Claroline\CoreBundle\Form\WorkspaceLogFilterType;
use Claroline\CoreBundle\Form\AdminLogFilterType;
use Claroline\CoreBundle\Library\Event\LogCreateDelegateViewEvent;
use Claroline\CoreBundle\Library\Event\LogResourceChildUpdateEvent;
use Claroline\CoreBundle\Library\Resource\ResourceCollection;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Claroline\CoreBundle\Entity\Logger\LogWorkspaceWidgetConfig;
use Claroline\CoreBundle\Entity\Logger\LogDesktopWidgetConfig;

/**
 * @DI\Service("claroline.log.manager")
 */
class Manager
{
    const LOG_PER_PAGE = 40;

    private $container;

    protected function isAllowedToViewLogs($workspace)
    {
        return ($this->container->get('security.context')->isGranted('ROLE_WS_COLLABORATOR_'.$workspace->getId()) === true
            || $this->container->get('security.context')->isGranted('ROLE_WS_MANAGER_'.$workspace->getId()) === true);
    }

    protected function renderLogs($logs)
    {
        //List item delegation
        $views = array();
        foreach ($logs as $log) {
            if ($log->getAction() === LogResourceChildUpdateEvent::ACTION) {
                $eventName = 'create_log_list_item_'.$log->getResourceType()->getName();
                $event = new LogCreateDelegateViewEvent($log);
                $this->container->get('event_dispatcher')->dispatch($eventName, $event);

                if ($event->getResponseContent() === "") {
                    throw new \Exception(
                        "Event '{$eventName}' didn't receive any response."
                    );
                }

                $views[$log->getId().''] = $event->getResponseContent();
            }
        }

        return $views;
    }

    protected function getAdminActionRestriction()
    {
        return array(
            'group_add_user',
            'group_create',
            'group_delete',
            'group_remove_user',
            'group_update',
            'user_create',
            'user_delete',
            'user_login',
            'user_update',
            'workspace_create',
            'workspace_delete',
            'workspace_update'
        );
    }

    protected function getWorkspaceActionRestriction()
    {
        return array(
            'resource_create',
            'resource_delete',
            'resource_update',
            'resource_child_update',
            'resource_move',
            'resource_shortcut',
            'resource_read',
            'resource_export',
            'resource_child_update',
            'resource_copy',
            'ws_role_create',
            'ws_role_delete',
            'ws_role_update',
            'ws_role_change_right',
            'ws_role_subscribe_user',
            'ws_role_unsubscribe_user',
            'ws_role_subscribe_group',
            'ws_role_unsubscribe_group',
            'ws_tool_read'
        );
    }

    protected function getDefaultRange()
    {
        //By default last thirty days :
        $startDate = new \DateTime('now');
        $startDate->setTime(0, 0, 0);
        $startDate->sub(new \DateInterval('P29D')); // P29D means a period of 29 days

        $endDate = new \DateTime('now');
        $endDate->setTime(23, 59, 59);

        return array($startDate->getTimestamp(), $endDate->getTimestamp());
    }

    protected function getAdminOrCollaboratorWorkspaceIds()
    {
        $workspaceIds = array();
        $loggedUser = $this->container->get('security.context')->getToken()->getUser();
        $workspaceIdsResult = $this
            ->container
            ->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')
            ->findIdsByUserAndRoleNames($loggedUser, array('ROLE_WS_COLLABORATOR', 'ROLE_WS_MANAGER'));

        foreach ($workspaceIdsResult as $line) {
            $workspaceIds[] = $line['id'];
        }

        return $workspaceIds;
    }

    /**
     * @DI\InjectParams({
     *     "container" = @DI\Inject("service_container")
     * })
     */
    public function __construct($container)
    {
        $this->container = $container;
    }

    public function getDesktopWidgetList()
    {
        $loggedUser = $this->container->get('security.context')->getToken()->getUser();
        $em = $this->container->get('doctrine.orm.entity_manager');
        $repository = $em->getRepository('ClarolineCoreBundle:Logger\Log');

        $desktopConfig = $this->getDesktopWidgetConfig($loggedUser);
        $hiddenConfigs = $em
            ->getRepository('ClarolineCoreBundle:Logger\LogHiddenWorkspaceWidgetConfig')
            ->findBy(array('user' => $loggedUser));
        $workspaceIds = array();
        foreach ($hiddenConfigs as $hiddenConfig) {
            $workspaceIds[] = $hiddenConfig->getWorkspaceId();
        }

        // Get manager and collaborator workspaces config
        $workspaces = $em
            ->getRepository('ClarolineCoreBundle:Workspace\AbstractWorkspace')
            ->findByUserAndRoleNamesNotIn($loggedUser, array('ROLE_WS_COLLABORATOR', 'ROLE_WS_MANAGER'), $workspaceIds);

        if (count($workspaces) > 0) {
            $configs = $this->container->get('doctrine.orm.entity_manager')
                ->getRepository('ClarolineCoreBundle:Logger\LogWorkspaceWidgetConfig')
                ->findBy(array('workspace' => $workspaces));
        } else {
            $configs = array();
        }

        // Complete missing configs
        foreach ($workspaces as $workspace) {
            $config = null;
            $i = 0;
            while ($i < count($configs) && $config == null) {
                $current = $configs[$i++];
                if ($current->getWorkspace()->getId() == $workspace->getId()) {
                    $config = $current;
                }
            }
            if ($config === null) {
                $config = new LogWorkspaceWidgetConfig();
                $config->setWorkspace($workspace);
                $configs[] = $config;
            }
        }

        if (count($configs) > 0) {
            $query = $repository->findLogsThroughConfigs($configs, $desktopConfig->getAmount());
            $logs = $query->getResult();
            $chartData = $repository->countByDayThroughConfigs($configs, $this->getDefaultRange());
        } else {
            $logs = array();
            $chartData = array();
        }

        //List item delegation
        $views = $this->renderLogs($logs);

        return array(
            'logs' => $logs,
            'listItemViews' => $views,
            'chartData' => $chartData,
            'logAmount' => $desktopConfig->getAmount(),
            'isDesktop' => true
        );
    }

    public function getWorkspaceWidgetList($workspace)
    {
        if (!$this->isAllowedToViewLogs($workspace)) {

            return null;
        }
        $em = $this->container->get('doctrine.orm.entity_manager');
        $repository = $em->getRepository('ClarolineCoreBundle:Logger\Log');

        $config = $this->container->get('doctrine.orm.entity_manager')
            ->getRepository('ClarolineCoreBundle:Logger\LogWorkspaceWidgetConfig')
            ->findOneByWorkspace($workspace);

        if ($config === null) {
            $config = new LogWorkspaceWidgetConfig();
            $config->setWorkspace($workspace);
        }

        $query = $repository->findLogsThroughConfigs(array($config), $config->getAmount());
        $logs = $query->getResult();

        $chartData = $repository->countByDayThroughConfigs(array($config), $this->getDefaultRange());

        //List item delegation
        $views = $this->renderLogs($logs);

        return array(
            'logs' => $logs,
            'listItemViews' => $views,
            'chartData' => $chartData,
            'workspace' => $workspace,
            'logAmount' => $config->getAmount()
        );
    }

    public function getAdminList($page, $maxResult = -1)
    {
        return $this->getList(
            $page,
            $this->getAdminActionRestriction(),
            new AdminLogFilterType(),
            'admin_log_filter_form',
            null,
            $maxResult
        );
    }

    public function getDesktopList($page, $maxResult = -1)
    {
        return $this->getWorkspaceList(null, $page, $maxResult);
    }

    public function getWorkspaceList($workspace, $page, $maxResult = -1)
    {
        if ($workspace == null) {
            $workspaceIds = $this->getAdminOrCollaboratorWorkspaceIds();
        } else {
            $workspaceIds = array($workspace->getId());
        }

        $params = $this->getList(
            $page,
            $this->getWorkspaceActionRestriction(),
            new WorkspaceLogFilterType(),
            'workspace_log_filter_form',
            $workspaceIds,
            $maxResult
        );
        $params['workspace'] = $workspace;

        return $params;
    }

    public function getList(
        $page,
        $actionsRestriction,
        $logFilterFormType,
        $queryParamName,
        $workspaceIds = null,
        $maxResult = -1
    )
    {
        $request = $this->container->get('request');
        $data = $request->query->all();

        $action = null;
        $range = null;
        $userSearch = null;
        $dateRangeToTextTransformer = new DateRangeToTextTransformer($this->container->get('translator'));

        if (array_key_exists($queryParamName, $data)) {
            $data = $data[$queryParamName];
            $action = $data['action'];
            $range = $dateRangeToTextTransformer->reverseTransform($data['range']);
            $userSearch = $data['user'];
        } else if (array_key_exists('filter', $data)) {
            $decodeFilter = json_decode(urldecode($data['filter']));
            if ($decodeFilter !== null) {
                $action = $decodeFilter->action;
                $range = $dateRangeToTextTransformer->reverseTransform($decodeFilter->range);
                $userSearch = $decodeFilter->user;
            }
        }

        if ($range == null) {
            $range = $this->getDefaultRange();
        }

        $data = array();
        $data['action'] = $action;
        $data['range'] = $range;
        $data['user'] = $userSearch;

        $filterForm = $this->container->get('form.factory')->create($logFilterFormType);
        $filterForm->setData($data);

        $data['range'] = $dateRangeToTextTransformer->transform($range);
        $filter = urlencode(json_encode($data));

        $em = $this->container->get('doctrine.orm.entity_manager');
        $repository = $em->getRepository('ClarolineCoreBundle:Logger\Log');

        $query = $repository->findFilteredLogsQuery(
            $action,
            $range,
            $userSearch,
            $actionsRestriction,
            $workspaceIds,
            $maxResult
        );

        $adapter = new DoctrineORMAdapter($query);
        $pager   = new PagerFanta($adapter);
        $pager->setMaxPerPage(self::LOG_PER_PAGE);

        try {
            $pager->setCurrentPage($page);
        } catch (NotValidCurrentPageException $e) {
            throw new NotFoundHttpException();
        }

        $chartData = $repository->countByDayFilteredLogs(
            $action,
            $range,
            $userSearch,
            $actionsRestriction,
            $workspaceIds
        );

        //List item delegation
        $views = $this->renderLogs($pager->getCurrentPageResults());
        //$views = array();

        return array(
            'pager' => $pager,
            'listItemViews' => $views,
            'filter' => $filter,
            'filterForm' => $filterForm->createView(),
            'chartData' => $chartData
        );
    }

    public function getWorkspaceVisibilityForDesktopWidget($user, $workspaces)
    {
        $workspacesVisibility = array();

        $em = $this->container->get('doctrine.orm.entity_manager');
        foreach ($workspaces as $workspace) {
            $workspacesVisibility[$workspace->getId()] = true;
        }

        $hiddenWorkspaceConfigs = $em
            ->getRepository('ClarolineCoreBundle:Logger\LogHiddenWorkspaceWidgetConfig')
            ->findBy(array('user' => $user));

        foreach ($hiddenWorkspaceConfigs as $config) {
            if ($workspacesVisibility[$config->getWorkspaceId()] !== null) {
                $workspacesVisibility[$config->getWorkspaceId()] = false;
            }
        }

        return $workspacesVisibility;
    }

    public function getDesktopWidgetConfig($user)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $config = $em
            ->getRepository('ClarolineCoreBundle:Logger\LogDesktopWidgetConfig')
            ->findOneBy(array('user' => $user));

        if ($config === null) {
            $config = new LogDesktopWidgetConfig();
            $config->setUser($user);
        }

        return $config;
    }

    public function getWorkspaceWidgetConfig($workspace)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');
        $config = $em
            ->getRepository('ClarolineCoreBundle:Logger\LogWorkspaceWidgetConfig')
            ->findOneBy(array('workspace' => $workspace));

        if ($config === null) {
            $config = new LogWorkspaceWidgetConfig();
            $config->setWorkspace($workspace);
        }

        return $config;
    }
}
