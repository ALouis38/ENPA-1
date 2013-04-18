<?php

namespace Claroline\CoreBundle\Listener;

use Claroline\CoreBundle\Library\Security\Token\ViewAsToken;
use Doctrine\ORM\EntityManager;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use JMS\DiExtraBundle\Annotation as DI;

/**
 * @DI\Service
 */
class ViewAsListener
{
    private $securityContext;

    /**
     * @DI\InjectParams({
     *     "context" = @DI\Inject("security.context"),
     *     "em" = @DI\Inject("doctrine.orm.entity_manager")
     * })
     *
     * @param SecurityContextInterface $context
     */
    public function __construct(SecurityContextInterface $context, EntityManager $em)
    {
        $this->securityContext = $context;
        $this->em = $em;
    }

    /**
     * @DI\Observe("kernel.request")
     */
    public function onViewAs(GetResponseEvent $event)
    {
        $request = $event->getRequest();
        $attributes = $request->query->all();

        if (array_key_exists('view_as', $attributes)) {
            $user = $this->securityContext->getToken()->getUser();
            $viewAs = $attributes['view_as'];
            if ($viewAs === 'exit') {
                if ($this->securityContext->isGranted('ROLE_USURPATE_WORKSPACE_ROLE')) {
                    $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
                    $this->securityContext->setToken($token);
                }
            } else {
                $workspaceId = $request->attributes->get('workspaceId');
                if ($this->securityContext->isGranted('ROLE_WS_MANAGER_'.$workspaceId)) {
                    $token = new ViewAsToken(array('ROLE_USER', $viewAs, 'ROLE_USURPATE_WORKSPACE_ROLE'));
                    $token->setUser($user);
                    $this->securityContext->setToken($token);
                }
            }
        }
    }
}