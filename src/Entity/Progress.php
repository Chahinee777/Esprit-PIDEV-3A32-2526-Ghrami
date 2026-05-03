<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'progress')]
class Progress
{
    #[ORM\Id]
    #[ORM\Column(name: 'progress_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hobby::class, inversedBy: 'progress')]
    #[ORM\JoinColumn(name: 'hobby_id', referencedColumnName: 'hobby_id', nullable: false, onDelete: 'CASCADE')]
    public ?Hobby $hobby = null;

    #[ORM\Column(name: 'hours_spent', type: Types::FLOAT, nullable: true)]
    public ?float $hoursSpent = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $notes = null;
}
