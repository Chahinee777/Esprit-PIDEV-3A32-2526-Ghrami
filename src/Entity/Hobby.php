<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'hobbies')]
class Hobby
{
    public const ALLOWED_CATEGORIES = [
        'Sports & Fitness',
        'Arts & Crafts',
        'Music',
        'Cooking',
        'Gaming',
        'Reading',
        'Technology',
        'Photography',
        'Gardening',
        'Writing',
        'Learning Languages',
        'Dancing',
        'Traveling',
        'Other',
    ];

    #[ORM\Id]
    #[ORM\Column(name: 'hobby_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Hobby name is required.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Hobby name must be at least {{ limit }} characters.',
        maxMessage: 'Hobby name cannot exceed {{ limit }} characters.'
    )]
    public string $name = '';

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: 'Category is required.')]
    #[Assert\Choice(
        choices: self::ALLOWED_CATEGORIES,
        message: 'Please select a valid category.'
    )]
    public ?string $category = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1200, maxMessage: 'Description cannot exceed {{ limit }} characters.')]
    public ?string $description = null;

    #[ORM\OneToMany(targetEntity: Progress::class, mappedBy: 'hobby', cascade: ['remove'])]
    public Collection $progress;

    #[ORM\OneToMany(targetEntity: Milestone::class, mappedBy: 'hobby', cascade: ['remove'])]
    public Collection $milestones;

    public function __construct()
    {
        $this->progress = new ArrayCollection();
        $this->milestones = new ArrayCollection();
    }
}
