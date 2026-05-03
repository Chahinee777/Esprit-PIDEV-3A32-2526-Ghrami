<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'digest_logs')]
class DigestLog
{
    #[ORM\Id]
    #[ORM\Column(name: 'digest_log_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    public string $content = '';

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(length: 20)]
    public string $channel = 'email';

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    public bool $opened = false;
}
