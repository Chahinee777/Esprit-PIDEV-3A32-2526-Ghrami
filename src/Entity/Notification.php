<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\Column(name: 'notification_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(length: 50)]
    public string $type = '';

    #[ORM\Column(type: Types::STRING, length: 500)]
    public string $content = '';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'related_user_id', referencedColumnName: 'user_id', nullable: true, onDelete: 'SET NULL')]
    public ?User $relatedUser = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'is_read', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isRead = false;
}
