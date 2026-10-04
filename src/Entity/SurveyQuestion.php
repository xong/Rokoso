<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\SurveyQuestionType;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity]
class SurveyQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $position = 0;

    #[ORM\Column(length: 20, enumType: SurveyQuestionType::class)]
    private SurveyQuestionType $type = SurveyQuestionType::Single;

    #[ORM\Column(length: 500)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 500)]
    private string $label = '';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $options = [];

    #[ORM\Column]
    private bool $required = false;

    public function __construct(
        #[ORM\ManyToOne(inversedBy: 'questions')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private Survey $survey,
    ) {
    }

    #[Assert\Callback]
    public function validateOptions(ExecutionContextInterface $context): void
    {
        if ($this->type->hasOptions() && \count($this->options) < 2) {
            $context->buildViolation('survey.options_required')->atPath('optionsText')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurvey(): Survey
    {
        return $this->survey;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getType(): SurveyQuestionType
    {
        return $this->type;
    }

    public function setType(SurveyQuestionType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = trim((string) $label);

        return $this;
    }

    /**
     * @return list<string>
     */
    public function getOptions(): array
    {
        return $this->type->hasOptions() ? $this->options : [];
    }

    /**
     * @param list<string> $options
     */
    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    /** One option per line, for the form */
    public function getOptionsText(): string
    {
        return implode("\n", $this->options);
    }

    public function setOptionsText(?string $text): static
    {
        $options = [];
        foreach (preg_split('/\R/', (string) $text) ?: [] as $line) {
            $line = trim($line);
            if ('' !== $line && !\in_array($line, $options, true)) {
                $options[] = mb_substr($line, 0, 200);
            }
        }
        $this->options = $options;

        return $this;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): static
    {
        $this->required = $required;

        return $this;
    }
}
