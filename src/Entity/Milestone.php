<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'milestones')]
class Milestone
{
    #[ORM\Id]
    #[ORM\Column(name: 'milestone_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hobby::class)]
    #[ORM\JoinColumn(name: 'hobby_id', referencedColumnName: 'hobby_id', nullable: false, onDelete: 'CASCADE')]
    public ?Hobby $hobby = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Milestone title is required.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Milestone title must be at least {{ limit }} characters.',
        maxMessage: 'Milestone title cannot exceed {{ limit }} characters.'
    )]
    public string $title = '';

    #[ORM\Column(name: 'target_date', type: Types::DATE_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $targetDate = null;

    #[ORM\Column(name: 'is_achieved', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isAchieved = false;
}
