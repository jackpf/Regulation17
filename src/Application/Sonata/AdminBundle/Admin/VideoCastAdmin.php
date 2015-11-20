<?php

namespace Application\Sonata\AdminBundle\Admin;

use MusicBundle\Entity\VideoQueueItem;
use MusicBundle\Service\VideoProcessor;
use Doctrine\ORM\EntityManagerInterface;
use MusicBundle\Data\Data;
use MusicBundle\Service\AudioProcessor;
use Sonata\AdminBundle\Admin\Admin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;

class VideoCastAdmin extends MediaAdmin
{
    private $em;

    // Fields to be shown on create/edit forms
    protected function configureFormFields(FormMapper $formMapper)
    {
        parent::configureFormFields($formMapper);

        $formMapper
            ->add('useVideoIconAsImage')
            ->add('audioDownloadLink')
            ->add('videoFile', 'sonata_type_admin', [
                'required' => false,
                'cascade_validation' => true,
            ])
        ;
    }

    public function setEntityManager(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function preUpdate($object)
    {
        parent::preUpdate($object);

        // Delete?
        $data = $this->getRequest()->request->get($this->getUniqid());
        if (isset($data['videoFile']) && isset($data['videoFile']['_delete']) && $data['videoFile']['_delete'] && $object->getVideoFile()) {
            $this->em->remove($object->getVideoFile());
            $this->em->flush();
        }

        // Make sure life cycle events are triggered
        if ($file = $object->getVideoFile()) {
            $file->setUpdatedAt(new \DateTime());
        }
    }

    public function postPersist($object)
    {
        parent::postPersist($object);

        $this->processVideo($object);
    }

    public function postUpdate($object)
    {
        parent::postUpdate($object);

        $this->processVideo($object);
    }

    public function processVideo($object)
    {
        $file = $object->getVideoFile();

        if ($file->getFile() != null) {
            $videoQueueItem = $this->em->getRepository('MusicBundle:VideoQueueItem')
                ->findBy(['file' => $file, 'state' => VideoQueueItem::STATE_UNPROCESSED]);

            if (!$videoQueueItem) {
                $videoQueueItem = new VideoQueueItem();
                $videoQueueItem->setFile($file);
                $videoQueueItem->setState(VideoQueueItem::STATE_UNPROCESSED);
                $this->em->persist($videoQueueItem);

                $this->em->flush();
            }
        }
    }
}