<?php

namespace Claroline\CoreBundle\Entity\Rights;

use Doctrine\ORM\Mapping as ORM;
use Claroline\CoreBundle\Entity\Role;
use Claroline\CoreBundle\Entity\Resource\AbstractResource;
use Claroline\CoreBundle\Entity\Resource\ResourceType;
use Doctrine\Common\Collections\ArrayCollection;

/**
 * @ORM\Entity(repositoryClass="Claroline\CoreBundle\Repository\ResourceRightsRepository")
 * @ORM\Table(name="claro_resource_rights")
 */
class ResourceRights
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @ORM\ManyToOne(
     *     targetEntity="Claroline\CoreBundle\Entity\Role",
     *     inversedBy="resourcesRights"
     * )
     */
    private $role;

    /**
     * @ORM\ManyToOne(
     *     targetEntity="Claroline\CoreBundle\Entity\Resource\AbstractResource",
     *     inversedBy="rights", cascade={"persist"}
     * )
     */
    private $resource;

    /**
     * @ORM\Column(type="boolean", name="can_see")
     */
    protected $canView;

    /**
     * @ORM\Column(type="boolean", name="can_delete")
     */
    protected $canDelete;

    /**
     * @ORM\Column(type="boolean", name="can_open")
     */
    protected $canOpen;

    /**
     * @ORM\Column(type="boolean", name="can_edit")
     */
    protected $canEdit;

    /**
     * @ORM\Column(type="boolean", name="can_copy")
     */
    protected $canCopy;

    /**
     * @ORM\Column(type="boolean", name="can_export")
     */
    protected $canExport;

    /**
     * @ORM\ManyToMany(
     *     targetEntity="Claroline\CoreBundle\Entity\Resource\ResourceType",
     *     inversedBy="rights"
     * )
     * @ORM\JoinTable(
     *     name="claro_list_type_creation",
     *     joinColumns={
     *         @ORM\JoinColumn(name="right_id", referencedColumnName="id")
     *     },
     *     inverseJoinColumns={
     *         @ORM\JoinColumn(name="resource_type_id", referencedColumnName="id")
     *     }
     * )
     */
    protected $resourceTypes;

    public function __construct()
    {
        $this->resourceTypes = new ArrayCollection();
    }

    public function getId()
    {
        return $this->id;
    }

    public function getRole()
    {
        return $this->role;
    }

    public function setRole(Role $role)
    {
        $this->role = $role;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function setResource(AbstractResource $resource)
    {
        $this->resource = $resource;
    }

    public function canView()
    {
        return $this->canView;
    }

    public function setCanView($canView)
    {
        $this->canView = $canView;
    }

    public function canDelete()
    {
        return $this->canDelete;
    }

    public function setCanDelete($canDelete)
    {
        $this->canDelete = $canDelete;
    }

    public function canOpen()
    {
        return $this->canOpen;
    }

    public function setCanOpen($canOpen)
    {
        $this->canOpen = $canOpen;
    }

    public function canEdit()
    {
        return $this->canEdit;
    }

    public function setCanEdit($canEdit)
    {
        $this->canEdit = $canEdit;
    }

    public function canCopy()
    {
        return $this->canCopy;
    }

    public function setCanCopy($canCopy)
    {
        $this->canCopy = $canCopy;
    }

    public function setCanExport($canExport)
    {
        $this->canExport = $canExport;
    }

    public function canExport()
    {
        return $this->canExport;
    }

    /**
     * Sets every right to false
     */
    public function reset()
    {
        $this->canCopy = false;
        $this->canDelete = false;
        $this->canEdit = false;
        $this->canOpen = false;
        $this->canView = false;
        $this->canExport = false;
    }

    /**
     * Compares the current permission with an array of permission
     *
     * @param type $array
     *
     * @return boolean
     */
    public function isEquals($rights)
    {
        foreach ($this->getRights() as $key => $current) {
            if ($current != $rights[$key]) {
                return false;
            }
        }

        return true;
    }

    /**
     * Gets an array with the current permissions
     *
     * @return array
     */
    public function getRights()
    {
        return array(
            'canCopy' => $this->canCopy,
            'canDelete' => $this->canDelete,
            'canEdit' => $this->canEdit,
            'canOpen' => $this->canOpen,
            'canView' => $this->canView,
            'canExport' => $this->canExport
        );
    }

    /**
     * Sets the current permission from an array
     *
     * @param type array
     */
    public function setRights($rights)
    {
        foreach ($rights as $key => $value) {
            $this->$key = $value;
        }
    }

    public function addResourceType(ResourceType $resourceType)
    {
        $this->resourceTypes->add($resourceType);
    }

    public function removeResourceType(ResourceType $resourceType)
    {
        $this->resourceTypes->removeElement($resourceType);
    }

    public function getResourceTypes()
    {
        return $this->resourceTypes;
    }

    public function cleanResourceTypes()
    {
        $this->resourceTypes = new ArrayCollection();
    }
}