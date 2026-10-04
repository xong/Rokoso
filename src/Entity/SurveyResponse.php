<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * One filled-in survey. Answers are keyed by question id: string (single, text), list of strings (multiple) or int (scale).
 */
#[ORM\Entity]
class SurveyResponse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /**
     * @param array<int|string, string|int|list<string>> $answers
     */
    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'responses')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Survey $survey,
        #[ORM\Column(type: Types::JSON)]
        private array $answers,
        #[ORM\Column(length: 120, nullable: true)]
        private ?string $name = null,
        #[ORM\Column(length: 180, nullable: true)]
        private ?string $email = null,
    ) {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurvey(): Survey
    {
        return $this->survey;
    }

    /**
     * @return string|int|list<string>|null
     */
    public function getAnswer(SurveyQuestion $question): string|int|array|null
    {
        return $this->answers[(string) $question->getId()] ?? $this->answers[(int) $question->getId()] ?? null;
    }

    /**
     * @return array<int|string, string|int|list<string>>
     */
    public function getAnswers(): array
    {
        return $this->answers;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
