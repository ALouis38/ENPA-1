<?php

namespace Claroline\CoreBundle\Repository;

use Gedmo\Tree\Entity\Repository\NestedTreeRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Claroline\CoreBundle\Entity\Message;
use Claroline\CoreBundle\Entity\User;

class MessageRepository extends NestedTreeRepository
{
    /**
     * Returns the ancestors of a message.
     *
     * @param Message $message
     *
     * @return type
     */
    public function findAncestors(Message $message)
    {
        $dql = "SELECT m FROM Claroline\CoreBundle\Entity\Message m
            WHERE m.lft BETWEEN m.lft AND m.rgt
            AND m.root = {$message->getRoot()}
            AND m.lvl <= {$message->getLvl()}";

        $query = $this->_em->createQuery($dql);

        return $query->getResult();
    }

    /**
     * Count the number of unread messages for a user.
     *
     * @param User $user
     *
     * @return integer
     */
    public function countUnread(User $user)
    {
        $dql = "SELECT Count(m) FROM Claroline\CoreBundle\Entity\Message m
            JOIN m.userMessages um
            JOIN um.user u
            WHERE u.id = {$user->getId()}
            AND um.isRead = false
            AND um.isRemoved = false"
           ;

        $query = $this->_em->createQuery($dql);
        $result = $query->getArrayResult();

        //?? getFirstResult and alisases do not work. Why ?
        return $result[0][1];
    }

    /**
     * Warning. Returns UserMessage entities (the entities from the join table).
     *
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param boolean $isRemoved
     * @param integer $offset
     * @param integer $limit
     *
     * @return \Doctrine\ORM\Tools\Pagination\Paginator
     */
    public function findReceivedByUser(User $user, $isRemoved = false, $offset = null, $limit = null)
    {
        $isRemovedText = ($isRemoved) ? 'true' : 'false';
        $dql = "SELECT um, m, u FROM Claroline\CoreBundle\Entity\UserMessage um
            JOIN um.user u
            JOIN um.message m
            WHERE u.id = {$user->getId()}
            AND um.isRemoved = {$isRemovedText}
            ORDER BY m.date DESC";

        $query = $this->_em->createQuery($dql);
        $query->setFirstResult($offset)
            ->setMaxResults($limit);
        $paginator = new Paginator($query, true);

        return $paginator;
    }

    public function findSentByUser(User $user, $isRemoved = false, $offset = null, $limit = null)
    {
        $isRemovedText = ($isRemoved) ? 'true' : 'false';
        $dql = "SELECT m, u, um, umu FROM Claroline\CoreBundle\Entity\Message m
            JOIN m.userMessages um
            JOIN um.user umu
            JOIN m.user u
            WHERE u.id = {$user->getId()}
            AND m.isRemoved = {$isRemovedText}
            ORDER BY m.date DESC";

        $query = $this->_em->createQuery($dql);
        $query->setFirstResult($offset)
            ->setMaxResults($limit);
        $paginator = new Paginator($query, true);

        return $paginator;
    }

    /**
     * Warning. Returns UserMessage entities (the entities from the join table).
     *
     * @param string $search
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param boolean $isRemoved
     * @param integer $offset
     * @param integer $limit
     *
     * @return \Doctrine\ORM\Tools\Pagination\Paginator
     */
    public function findReceivedByUserAndObjectAndUsername(
        User $user,
        $search,
        $isRemoved = false,
        $offset = null,
        $limit = null
    )
    {
        $isRemovedText = ($isRemoved) ? 'true' : 'false';
        $search = strtoupper($search);
        $dql = "SELECT um, m, u, mu FROM Claroline\CoreBundle\Entity\UserMessage um
            JOIN um.user u
            JOIN um.message m
            JOIN m.user mu
            WHERE u.id = {$user->getId()}
            AND um.isRemoved = {$isRemovedText}
            AND UPPER(m.object) LIKE :search
            OR UPPER(mu.username) LIKE :search
            AND um.isRemoved = {$isRemovedText}
            AND u.id = {$user->getId()}
            ORDER BY m.date DESC";

        $query = $this->_em->createQuery($dql);
        $query->setParameter('search', "%{$search}%");
        $query->setFirstResult($offset)
            ->setMaxResults($limit);
        $paginator = new Paginator($query, true);

        return $paginator;
    }

    public function findSentByUserAndObjectAndUsername(
        User $user,
        $search,
        $isRemoved = false,
        $offset = null,
        $limit = null
    )
    {
        $isRemovedText = ($isRemoved) ? 'true' : 'false';
        $search = strtoupper($search);
        $dql = "SELECT m, u, um, umu FROM Claroline\CoreBundle\Entity\Message m
            JOIN m.userMessages um
            JOIN um.user umu
            JOIN m.user u
            WHERE u.id = {$user->getId()}
            AND m.isRemoved = {$isRemovedText}
            AND UPPER (m.object) LIKE :search
            OR UPPER (umu.username) LIKE :search
            AND u.id = {$user->getId()}
            AND m.isRemoved = {$isRemovedText}
            ORDER BY m.date DESC";

        $query = $this->_em->createQuery($dql);
        $query->setParameter('search', "%{$search}%");
        $query->setFirstResult($offset)
            ->setMaxResults($limit);
        $paginator = new Paginator($query, true);

        return $paginator;
    }

    public function findRemovedByUser(User $user, $offset = null, $limit = null)
    {
        $dql = "SELECT um, m, u, u FROM Claroline\CoreBundle\Entity\UserMessage um
            JOIN um.user u
            JOIN um.message m
            JOIN m.user mu
            WHERE u.id = {$user->getId()}
            AND um.isRemoved = true
            OR mu.id = {$user->getId()}
            AND m.isRemoved = true
            ORDER BY m.date DESC";

        $query = $this->_em->createQuery($dql);
        $query->setFirstResult($offset)
            ->setMaxResults($limit);
        $paginator = new Paginator($query, true);

        return $paginator;
    }

    /**
     * Warning. Returns UserMessage entities (the entities from the join table).
     *
     * @param string $search
     * @param \Claroline\CoreBundle\Entity\User $user
     * @param integer $offset
     * @param integer $limit
     *
     * @return \Doctrine\ORM\Tools\Pagination\Paginator
     */
    public function findRemovedByUserAndObjectAndUsername(
        User $user,
        $search,
        $offset = null,
        $limit = null
    )
    {
        $search = strtoupper($search);

        $dql = "SELECT um, m, u, u FROM Claroline\CoreBundle\Entity\UserMessage um
            JOIN um.user u
            JOIN um.message m
            JOIN m.user mu
            WHERE u.id = {$user->getId()}
            AND um.isRemoved = true
            AND UPPER(m.object) LIKE :search
            OR mu.id = {$user->getId()}
            AND m.isRemoved = true
            AND UPPER(m.object) LIKE :search
            OR m.isRemoved = true
            AND UPPER(mu.username) LIKE :search
            OR um.isRemoved = true
            AND u.id = {$user->getId()}
            AND UPPER(mu.username) LIKE :search
            ORDER BY m.date DESC";

        $query = $this->_em->createQuery($dql);
        $query->setFirstResult($offset)
            ->setMaxResults($limit);
        $query->setParameter('search', "%{$search}%");
        $paginator = new Paginator($query, true);

        return $paginator;
    }
}