<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'messages')]
class Message
{
    #[ORM\Id]
    #[ORM\Column(name: 'message_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'sender_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $sender = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'receiver_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $receiver = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Message content is required.')]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Message cannot exceed {{ limit }} characters.'
    )]
    public string $content = '';

    #[ORM\Column(name: 'sent_at', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $sentAt = null;

    #[ORM\Column(name: 'is_read', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isRead = false;
}
