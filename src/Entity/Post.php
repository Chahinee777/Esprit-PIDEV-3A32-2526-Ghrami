<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'posts')]
class Post
{
    #[ORM\Id]
    #[ORM\Column(name: 'post_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Post content is required.')]
    #[Assert\Length(
        max: 5000,
        maxMessage: 'Post content cannot exceed {{ limit }} characters.'
    )]
    public string $content = '';

    #[ORM\Column(name: 'image_url', length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Image URL cannot exceed {{ limit }} characters.')]
    public ?string $imageUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Location cannot exceed {{ limit }} characters.')]
    public ?string $location = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Mood cannot exceed {{ limit }} characters.')]
    public ?string $mood = null;

    #[ORM\Column(name: 'hobby_tag', length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Hobby tag cannot exceed {{ limit }} characters.')]
    public ?string $hobbyTag = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: ['public', 'friends', 'private'])]
    public string $visibility = 'public';

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $createdAt = null;
}