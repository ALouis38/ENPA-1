<?php
namespace Claroline\CoreBundle\Library\User;

use Symfony\Component\Translation\Translator;
use Doctrine\ORM\EntityManager;
use Claroline\CoreBundle\Library\Workspace\Configuration;
use Claroline\CoreBundle\Library\Configuration\PlatformConfigurationHandler;
use Claroline\CoreBundle\Library\Workspace\Creator as WsCreator;
use Claroline\CoreBundle\Entity\User;
use Claroline\CoreBundle\Entity\Tool\DesktopTool;

class Creator
{
    private $em;
    private $trans;
    private $ch;
    private $wsCreator;
    private $personalWsTemplateFile;

    public function __construct(
        EntityManager $em,
        Translator $trans,
        PlatformConfigurationHandler $ch,
        WsCreator $wsCreator,
        $personalWsTemplateFile
    )
    {
        $this->em = $em;
        $this->trans = $trans;
        $this->ch = $ch;
        $this->wsCreator = $wsCreator;
        $this->personalWsTemplateFile = $personalWsTemplateFile."default.zip";
    }

    /**
     * Creates a user. This method will create the user personal workspace
     * and persist the $user.
     *
     * @param User $user
     *
     * @return User
     */
    public function create(User $user)
    {
        $user->addRole($this->em->getRepository('ClarolineCoreBundle:Role')->findOneByName('ROLE_USER'));
        $this->em->persist($user);
        $config = Configuration::fromTemplate($this->personalWsTemplateFile);
        //uncomment this line when the templating system is working
        $config->setWorkspaceType(Configuration::TYPE_SIMPLE);
        $locale = $this->ch->getParameter('locale_language');
        $this->trans->setLocale($locale);
        $personalWorkspaceName = $this->trans->trans('personal_workspace', array(), 'platform');
        $config->setWorkspaceName($personalWorkspaceName);
        $config->setWorkspaceCode($user->getUsername());
        $workspace = $this->wsCreator->createWorkspace($config, $user, false);
        $user->setPersonalWorkspace($workspace);
        $this->em->persist($workspace);

        $repo = $this->em->getRepository('ClarolineCoreBundle:Tool\Tool');
        $requiredTools[] = $repo->findOneBy(array('name' => 'home'));
        $requiredTools[] = $repo->findOneBy(array('name' => 'resource_manager'));
        $requiredTools[] = $repo->findOneBy(array('name' => 'parameters'));

        $i = 1;

        foreach ($requiredTools as $requiredTool) {
            $udt = new DesktopTool();
            $udt->setUser($user);
            $udt->setOrder($i);
            $udt->setTool($requiredTool);
            $i++;
            $this->em->persist($udt);
        }

        $this->em->flush();

        return $user;
    }
}
