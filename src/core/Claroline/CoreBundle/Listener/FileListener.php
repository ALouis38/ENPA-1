<?php

namespace Claroline\CoreBundle\Listener;

use Symfony\Component\DependencyInjection\ContainerAware;
use Symfony\Component\HttpFoundation\Response;
use Claroline\CoreBundle\Library\Resource\ResourceEvent;
use Claroline\CoreBundle\Form\FileType;
use Claroline\CoreBundle\Entity\Resource\File;
use Claroline\CoreBundle\Library\Resource\MimeTypes;
use Claroline\CoreBundle\Library\Resource\CreateFormResourceEvent;
use Claroline\CoreBundle\Library\Resource\CreateResourceEvent;
use Claroline\CoreBundle\Library\Resource\DeleteResourceEvent;
use Claroline\CoreBundle\Library\Resource\CopyResourceEvent;
use Claroline\CoreBundle\Library\Resource\CustomActionResourceEvent;

class FileListener extends ContainerAware
{
    public function onCreateForm(CreateFormResourceEvent $event)
    {
        $form = $this->container->get('form.factory')->create(new FileType, new File());
        $content = $this->container->get('templating')->render(
            'ClarolineCoreBundle:Resource:resource_form.html.twig',
            array(
                'form' => $form->createView(),
                'resourceType' => 'file'
            )
        );
        $event->setResponseContent($content);
        $event->stopPropagation();
    }

    public function onCreate(CreateResourceEvent $event)
    {
        $request = $this->container->get('request');
        $form = $this->container->get('form.factory')->create(new FileType, new File());
        $form->bindRequest($request);

        if ($form->isValid()) {
            $file = $form->getData();
            $tmpFile = $file->getName();
            $fileName = $tmpFile->getClientOriginalName();
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $size = filesize($tmpFile);
            $hashName = $this->generateGuid() . "." . $extension;
            $tmpFile->move($this->container->getParameter('claroline.files.directory'), $hashName);
            $file->setSize($size);
            $file->setName($fileName);
            $file->setHashName($hashName);
            $mimeType = MimeTypes::getMimeType($extension);
            $file->setMimeType($mimeType);
            $event->setResource($file);
            $event->stopPropagation();
            return;
        }

        $content = $this->container->get('templating')->render(
            'ClarolineCoreBundle:Resource:resource_form.html.twig',
            array(
                'form' => $form->createView(),
                'resourceType' => 'file'
            )
        );
        $event->setErrorFormContent($content);
        $event->stopPropagation();
    }

    // TODO : add error handling (exceptions)
    public function onDelete(DeleteResourceEvent $event)
    {
        $em = $this->container->get('doctrine.orm.entity_manager');

        foreach ($event->getResources() as $file) {
            $em->remove($file);
            $pathName = $this->container->getParameter('claroline.files.directory')
                . DIRECTORY_SEPARATOR
                . $file->getHashName();
            chmod($pathName, 0777);
            unlink($pathName);
        }

        $event->stopPropagation();
    }

    public function onCopy(CopyResourceEvent $event)
    {
        $resource = $event->getResource();
        $newFile = new File();
        $newFile->setSize($resource->getSize());
        $newFile->setName($resource->getName());
        $hashName = $this->generateGuid() . '.' . pathinfo($resource->getHashName(), PATHINFO_EXTENSION);
        $newFile->setHashName($hashName);
        $filePath = $this->container->getParameter('claroline.files.directory') . DIRECTORY_SEPARATOR . $resource->getHashName();
        $newPath = $this->container->getParameter('claroline.files.directory') . DIRECTORY_SEPARATOR . $hashName;
        copy($filePath, $newPath);
        $em = $this->container->get('doctrine.orm.entity_manager');
        $em->persist($newFile);
        $event->setCopy($newFile);
        $event->stopPropagation();
    }

    public function onDownload(CustomActionResourceEvent $event)
    {
        $response = new Response();
        $file = $this->container->get('doctrine.orm.entity_manager')
            ->getRepository('Claroline\CoreBundle\Entity\Resource\File')
            ->find($event->getResourceId());
        $response->setContent(file_get_contents($this->container->getParameter('claroline.files.directory') . DIRECTORY_SEPARATOR . $file->getHashName()));
        $response->headers->set('Content-Transfer-Encoding', 'octet-stream');
        $response->headers->set('Content-Type', 'application/force-download');
        $response->headers->set('Content-Disposition', 'attachment; filename=' . $file->getName());
        $response->headers->set('Content-Length', $file->getSize());
        $response->headers->set('Content-Type', 'application/' . pathinfo($file->getName(), PATHINFO_EXTENSION));
        $response->headers->set('Connection', 'close');
        $event->setResponse($response);
        $event->stopPropagation();
    }

    /**
     * Generates a globally unique identifier.
     *
     * @see http://php.net/manual/fr/function.com-create-guid.php
     *
     * @return string
     */
    private function generateGuid()
    {
        if (function_exists('com_create_guid') === true) {
            return trim(com_create_guid(), '{}');
        }

        return sprintf(
            '%04X%04X-%04X-%04X-%04X-%04X%04X%04X',
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(16384, 20479),
            mt_rand(32768, 49151),
            mt_rand(0, 65535),
            mt_rand(0, 65535),
            mt_rand(0, 65535)
        );
    }
}