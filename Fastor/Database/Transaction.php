<?php

namespace Fastor\Database;

use Cycle\ORM\EntityManager;
use Cycle\ORM\ORMInterface;

class Transaction
{
    private EntityManager $em;

    public function __construct(ORMInterface $orm)
    {
        $this->em = new EntityManager($orm);
    }

    public function persist(object $entity): self
    {
        $this->em->persist($entity);
        return $this;
    }

    public function delete(object $entity): self
    {
        $this->em->delete($entity);
        return $this;
    }

    public function run(): void
    {
        $this->em->run();
    }
}
