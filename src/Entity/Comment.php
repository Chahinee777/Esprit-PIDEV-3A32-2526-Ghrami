<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'comments')]
class Comment
{
    #[ORM\Id]
    #[ORM\Column(name: 'comment_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'post_id', nullable: false, onDelete: 'CASCADE')]
    public ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Comment content is required.')]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Comment cannot exceed {{ limit }} characters.'
    )]
    public string $content = '';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $createdAt = null;
}
