<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'friendships')]
class Friendship
{
    #[ORM\Id]
    #[ORM\Column(name: 'friendship_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user1_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user1 = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user2_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user2 = null;

    #[ORM\Column(type: Types::STRING, length: 20, options: ['default' => 'PENDING'])]
    public string $status = 'PENDING';

    #[ORM\Column(name: 'created_date', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $createdDate = null;

    #[ORM\Column(name: 'accepted_date', type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $acceptedDate = null;
}
