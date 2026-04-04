<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'class_providers')]
class ClassProvider
{
    #[ORM\Id]
    #[ORM\Column(name: 'provider_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'user_id', nullable: false, onDelete: 'CASCADE')]
    public ?User $user = null;

    #[ORM\Column(name: 'company_name', length: 100, nullable: true)]
    public ?string $companyName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    public ?string $expertise = null;

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    public float $rating = 0;

    #[ORM\Column(name: 'is_verified', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isVerified = false;
}
