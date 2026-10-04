<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Signup of an outsider for a public event.
 */
#[ORM\Entity]
class EventSignup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'signups')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CalendarItem $item,
        #[ORM\Column(length: 120)]
        private string $name,
        #[ORM\Column(length: 180)]
        private string $email,
        #[ORM\Column]
        private int $persons = 1,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItem(): CalendarItem
    {
        return $this->item;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPersons(): int
    {
        return $this->persons;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
