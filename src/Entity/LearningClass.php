<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'classes')]
class LearningClass
{
    #[ORM\Id]
    #[ORM\Column(name: 'class_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ClassProvider::class)]
    #[ORM\JoinColumn(name: 'provider_id', referencedColumnName: 'provider_id', nullable: false, onDelete: 'CASCADE')]
    public ?ClassProvider $provider = null;

    #[ORM\Column(length: 200)]
    #[Assert\NotBlank(message: 'Class title is required.')]
    #[Assert\Length(
        min: 3,
        max: 200,
        minMessage: 'Class title must be at least {{ limit }} characters.',
        maxMessage: 'Class title cannot exceed {{ limit }} characters.'
    )]
    public string $title = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 5000, maxMessage: 'Description cannot exceed {{ limit }} characters.')]
    public ?string $description = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Category cannot exceed {{ limit }} characters.')]
    public ?string $category = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\PositiveOrZero(message: 'Price cannot be negative.')]
    public float $price = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive(message: 'Duration must be greater than zero.')]
    public int $duration = 0;

    #[ORM\Column(name: 'max_participants', type: Types::INTEGER)]
    #[Assert\Positive(message: 'Max participants must be greater than zero.')]
    public int $maxParticipants = 0;

    #[ORM\Column(name: 'video_path', length: 500, nullable: true)]
    public ?string $videoPath = null;

    #[ORM\Column(name: 'image_path', length: 500, nullable: true)]
    public ?string $imagePath = null;
}
