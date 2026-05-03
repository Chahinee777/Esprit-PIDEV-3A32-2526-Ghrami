<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'stories')]
class Story
{
    #[ORM\Id]
    #[ORM\Column(name: 'story_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Story caption cannot exceed {{ limit }} characters.')]
    public ?string $caption = null;

    #[ORM\Column(name: 'image_url', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Story image URL cannot exceed {{ limit }} characters.')]
    public ?string $imageUrl = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $expiresAt = null;
}
