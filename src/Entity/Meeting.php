<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'meetings')]
class Meeting
{
    #[ORM\Id]
    #[ORM\Column(name: 'meeting_id', length: 36)]
    public ?string $id = null;

    #[ORM\ManyToOne(targetEntity: Connection::class)]
    #[ORM\JoinColumn(name: 'connection_id', referencedColumnName: 'connection_id', nullable: false, onDelete: 'CASCADE')]
    public ?Connection $connection = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'organizer_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $organizer = null;

    #[ORM\Column(name: 'meeting_type', length: 20)]
    #[Assert\NotBlank(message: 'Meeting type is required.')]
    #[Assert\Choice(
        choices: ['virtual', 'physical'],
        message: 'Meeting type must be virtual or physical.'
    )]
    public string $meetingType = '';

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Location cannot exceed {{ limit }} characters.')]
    public ?string $location = null;

    #[ORM\Column(name: 'scheduled_at', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'Meeting date and time are required.')]
    public ?\DateTimeInterface $scheduledAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Range(
        min: 1,
        max: 1440,
        notInRangeMessage: 'Meeting duration must be between {{ min }} and {{ max }} minutes.'
    )]
    public int $duration = 0;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(
        choices: ['scheduled', 'completed', 'cancelled'],
        message: 'Please provide a valid meeting status.'
    )]
    public ?string $status = 'scheduled';
}
