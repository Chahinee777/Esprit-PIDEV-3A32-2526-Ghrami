<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'bookings')]
class Booking
{
    #[ORM\Id]
    #[ORM\Column(name: 'booking_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LearningClass::class)]
    #[ORM\JoinColumn(name: 'class_id', referencedColumnName: 'class_id', nullable: false, onDelete: 'CASCADE')]
    public ?LearningClass $class = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(name: 'booking_date', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $bookingDate = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Choice(
        choices: ['pending', 'scheduled', 'completed', 'cancelled'],
        message: 'Please provide a valid booking status.'
    )]
    public ?string $status = 'scheduled';

    #[ORM\Column(name: 'payment_status', length: 20, nullable: true)]
    #[Assert\Choice(
        choices: ['pending', 'paid', 'failed', 'refunded'],
        message: 'Please provide a valid payment status.'
    )]
    public ?string $paymentStatus = 'pending';

    #[ORM\Column(name: 'total_amount', type: Types::FLOAT)]
    #[Assert\PositiveOrZero(message: 'Total amount cannot be negative.')]
    public float $totalAmount = 0;

    #[ORM\Column(name: 'stripe_session_id', length: 255, nullable: true)]
    public ?string $stripeSessionId = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'Rating must be between {{ min }} and {{ max }}.'
    )]
    public ?int $rating = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'Review cannot exceed {{ limit }} characters.')]
    public ?string $review = null;

    #[ORM\Column(name: 'watch_progress', type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Watch progress must be between {{ min }} and {{ max }}.'
    )]
    public ?int $watchProgress = 0;
}
