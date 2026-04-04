<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'connections')]
class Connection
{
    #[ORM\Id]
    #[ORM\Column(name: 'connection_id', length: 36)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'initiator_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $initiator = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'receiver_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $receiver = null;

    #[ORM\Column(name: 'connection_type', length: 50)]
    #[Assert\NotBlank(message: 'Connection type is required.')]
    #[Assert\Choice(
        choices: ['skill', 'activity', 'hobby', 'general', 'Mentor'],
        message: 'Please select a valid connection type.'
    )]
    public string $connectionType = '';

    #[ORM\Column(name: 'receiver_skill', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Requested skill cannot exceed {{ limit }} characters.')]
    public ?string $receiverSkill = null;

    #[ORM\Column(name: 'initiator_skill', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Shared skill cannot exceed {{ limit }} characters.')]
    public ?string $initiatorSkill = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(
        choices: ['pending', 'accepted', 'rejected', 'cancelled'],
        message: 'Please provide a valid connection status.'
    )]
    public ?string $status = 'pending';
}
