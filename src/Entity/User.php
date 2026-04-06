<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[UniqueEntity(fields: ['username'], message: 'This username is already taken')]
#[UniqueEntity(fields: ['email'], message: 'This email is already in use')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(name: 'user_id', type: Types::BIGINT)]
    #[ORM\GeneratedValue]
    public ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Username is required')]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: 'Username must be at least {{ limit }} characters',
        maxMessage: 'Username cannot exceed {{ limit }} characters'
    )]
    #[Assert\Regex(
        pattern: '/^[a-zA-Z0-9_-]+$/',
        message: 'Username can only contain letters, numbers, underscores and hyphens'
    )]
    public string $username = '';

    #[ORM\Column(name: 'full_name', length: 100, nullable: false)]
    #[Assert\NotBlank(message: 'Full name is required')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Full name cannot exceed {{ limit }} characters'
    )]
    public string $fullName = '';

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank(message: 'Email is required')]
    #[Assert\Email(message: 'Please provide a valid email address')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Email cannot exceed {{ limit }} characters'
    )]
    public string $email = '';

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\NotBlank(
        message: 'Password is required',
        groups: ['registration', 'password_reset'],
        normalizer: 'trim'
    )]
    #[Assert\Length(
        min: 6,
        minMessage: 'Password must be at least {{ limit }} characters',
        groups: ['registration', 'password_reset']
    )]
    public ?string $password = null;

    #[ORM\Column(name: 'profile_picture', length: 500, nullable: true)]
    public ?string $profilePicture = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'Bio cannot exceed {{ limit }} characters'
    )]
    public ?string $bio = null;

    #[ORM\Column(length: 100, nullable: false)]
    #[Assert\NotBlank(message: 'Location is required')]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Location cannot exceed {{ limit }} characters'
    )]
    public string $location = '';

    #[ORM\Column(name: 'is_online', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isOnline = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE)]
    public ?\DateTimeInterface $createdAt = null;

    #[ORM\Column(name: 'last_login', type: Types::DATETIME_MUTABLE, nullable: true)]
    public ?\DateTimeInterface $lastLogin = null;

    #[ORM\Column(name: 'google_id', length: 50, nullable: true)]
    public ?string $googleId = null;

    #[ORM\Column(name: 'auth_provider', length: 20, options: ['default' => 'local'])]
    public string $authProvider = 'local';

    #[ORM\Column(name: 'is_banned', type: Types::BOOLEAN, options: ['default' => false])]
    public bool $isBanned = false;

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getRoles(): array
    {
        // Admin is user with ID 0 or email chahine@ghrami.tn
        if ($this->id === 0 || strtolower($this->email) === 'chahine@ghrami.tn') {
            return ['ROLE_ADMIN'];
        }

        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function getLocation(): string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = (string) ($location ?? '');

        return $this;
    }
}
