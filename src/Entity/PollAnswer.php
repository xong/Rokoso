<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One answer for an option: selected / yes (1) or maybe (2, date finding). "No" is not stored.
 * Secret polls leave the ballot empty.
 */
#[ORM\Entity]
class PollAnswer
{
    public const int YES = 1;
    public const int MAYBE = 2;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'answers')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PollOption $option,
        #[ORM\ManyToOne(inversedBy: 'answers')]
        #[ORM\JoinColumn(onDelete: 'CASCADE')]
        private ?PollBallot $ballot,
        #[ORM\Column(type: Types::SMALLINT)]
        private int $value = self::YES,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOption(): PollOption
    {
        return $this->option;
    }

    public function getBallot(): ?PollBallot
    {
        return $this->ballot;
    }

    public function getValue(): int
    {
        return $this->value;
    }
}
