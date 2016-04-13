<?php

namespace Tiarg\GeneratorBundle\Services;

use JMS\Serializer\Metadata\ClassMetadata;
use Doctrine\Common\Util\Inflector as Inflector;
/**
 * Marks Objects associations as read only
 */
class ImmutableAssociationService
{
    private $em;

    /**
     * Constructor.
     */
    public function __construct( $em)
    {
        $this->em = $em;
    }

    /**
     * {@inheritdoc}
     */
    public function markAssociationsReadOnly($entity)
    {
        $metadata     = $this->em->getClassMetadata(get_class($entity));
        $associations = $metadata->getAssociationNames();

        foreach ($associations as $association) 
        {
            if ($metadata->isSingleValuedAssociation($association))
            {
                $getter = Inflector::camelize('get_' . $association);

                $this->em->getUnitOfWork()->markReadOnly($entity->$getter());
            }
        }
    }
}