<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'progress_log')]
class ProgressLog
{
    #[ORM\Id]
    #[ORM\Column(name: 'log_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Hobby::class)]
    #[ORM\JoinColumn(name: 'hobby_id', referencedColumnName: 'hobby_id', nullable: false, onDelete: 'CASCADE')]
    public ?Hobby $hobby = null;

    #[ORM\Column(name: 'hours_spent', type: Types::FLOAT)]
    #[Assert\Positive(message: 'Hours must be greater than 0.')]
    #[Assert\LessThanOrEqual(value: 24, message: 'Hours cannot exceed 24 for one session.')]
    public float $hoursSpent = 0;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Notes cannot exceed {{ limit }} characters.')]
    public ?string $notes = null;

    #[ORM\Column(name: 'log_date', type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: 'Session date is required.')]
    public ?\DateTimeImmutable $logDate = null;

    #[ORM\PrePersist]
    public function ensureImmutableDates(): void
    {
        // logDate is already typed as DateTimeImmutable | null, no conversion needed
    }
}
