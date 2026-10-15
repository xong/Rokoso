<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\Mcp\ToolError;
use App\Agent\Mcp\ToolResult;
use App\Calendar\CalendarService;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\User;
use App\Enum\Feature;
use App\Repository\OrganizationRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Shared helpers of the agent tools: access checks (voter and organizations allowing agents), links, dates,
 * validation and file contents.
 */
final readonly class ToolKit
{
    public const string NOT_FOUND = 'Not found or not accessible.';

    /** largest file handed to the agent as content */
    private const int MAX_FILE_BYTES = 4 * 1024 * 1024;
    private const int MAX_TEXT_BYTES = 200 * 1024;

    public function __construct(
        private Security $security,
        private AgentContext $agent,
        private UrlGeneratorInterface $urls,
        private ValidatorInterface $validator,
        private OrganizationRepository $organizations,
        private ProjectRepository $projects,
        public EntityManagerInterface $em,
    ) {
    }

    /**
     * Ends the call unless the person may do this with the subject and its organization allows agents.
     */
    public function check(string $attribute, object $subject, ?Organization $organization): void
    {
        if (!$this->agent->allows($organization) || !$this->security->isGranted($attribute, $subject)) {
            throw new ToolError(self::NOT_FOUND);
        }
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    public function find(string $class, int $id): object
    {
        return $this->em->find($class, $id) ?? throw new ToolError(self::NOT_FOUND);
    }

    /**
     * Organization to create something in: the given one, or the only one possible.
     *
     * @param bool $personal without organization, entries are personal (only when the person belongs to none)
     */
    public function organization(User $user, Feature $feature, ?int $id, bool $personal = false): ?Organization
    {
        $choices = array_values(array_filter($this->organizations->findForUser($user, $feature), $this->agent->allows(...)));
        if (null !== $id) {
            foreach ($choices as $organization) {
                if ($organization->getId() === $id) {
                    return $organization;
                }
            }
            throw new ToolError('Unknown organization_id or the organization does not allow this. Use list_organizations.');
        }
        if (1 === \count($choices)) {
            return $choices[0];
        }
        if ([] === $choices) {
            if ($personal && [] === $this->organizations->findForUser($user)) {
                return null;
            }
            throw new ToolError('No organization of the person uses this area and allows AI agents.');
        }
        throw new ToolError('Several organizations are possible, pass organization_id: '.implode(', ', array_map(static fn (Organization $o): string => \sprintf('%d (%s)', $o->getId(), $o->getName()), $choices)).'.');
    }

    public function project(User $user, ?int $id): ?Project
    {
        if (null === $id || 0 === $id) {
            return null;
        }
        foreach ($this->projects->findVisibleFor($user) as $project) {
            if ($project->getId() === $id && $this->agent->allows($project->getOrganization())) {
                return $project;
            }
        }
        throw new ToolError('Unknown project_id. Use list_projects.');
    }

    /**
     * Ends the call with the validation messages (German, as shown in Rokoso); changes of a stored entity are undone.
     * New entities are validated before they are persisted.
     */
    public function validate(object $object): void
    {
        $violations = $this->validator->validate($object);
        if (\count($violations) > 0) {
            $messages = [];
            foreach ($violations as $violation) {
                $path = $violation->getPropertyPath();
                $messages[] = ('' === $path ? '' : self::snake($path).': ').$violation->getMessage();
            }
            if (!$this->em->getMetadataFactory()->isTransient($object::class) && $this->em->contains($object) && !$this->em->getUnitOfWork()->isScheduledForInsert($object)) {
                // keep a later flush (e.g. of notifications) from saving the invalid changes
                $this->em->refresh($object);
            }
            throw new ToolError('Invalid input: '.implode(' ', array_unique($messages)));
        }
    }

    /**
     * Absolute link for the person.
     *
     * @param array<string, mixed> $parameters
     */
    public function url(string $route, array $parameters = []): string
    {
        return $this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
    }

    public static function time(?\DateTimeInterface $time): ?string
    {
        return null === $time ? null : \DateTimeImmutable::createFromInterface($time)->setTimezone(new \DateTimeZone(CalendarService::TIMEZONE))->format(\DATE_ATOM);
    }

    public static function day(?\DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d');
    }

    /**
     * @return array{id: int|null, name: string}|null
     */
    public static function person(?User $user): ?array
    {
        return null === $user ? null : ['id' => $user->getId(), 'name' => $user->getName()];
    }

    /**
     * @return array{id: int|null, name: string}|null
     */
    public static function org(?Organization $organization): ?array
    {
        return null === $organization ? null : ['id' => $organization->getId(), 'name' => $organization->getName()];
    }

    /**
     * Readable text of an HTML mail without a text part.
     */
    public static function htmlToText(string $html): string
    {
        $html = (string) preg_replace('#<(script|style|head)\b[^>]*>.*?</\1>#is', '', $html);
        $html = (string) preg_replace('#<br\s*/?>|</(p|div|li|tr|h[1-6])>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), \ENT_QUOTES | \ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/\n{3,}/", "\n\n", (string) preg_replace('/[ \t]+/', ' ', $text)));
    }

    public static function excerpt(?string $text, int $length): ?string
    {
        if (null === $text || mb_strlen($text) <= $length) {
            return $text;
        }

        return mb_substr($text, 0, $length).'… [shortened]';
    }

    /**
     * File content for the agent: text inline, images and PDFs as MCP blocks, everything else only described.
     *
     * @param array<string, mixed> $data metadata of the file
     */
    public static function fileResult(array $data, string $path, string $filename, string $mimeType, int $size): ToolResult
    {
        $mimeType = self::mimeType($filename, $mimeType);
        if (!is_file($path)) {
            throw new ToolError('The file is missing in the storage.');
        }
        if ($size > self::MAX_FILE_BYTES) {
            return new ToolResult($data + ['note' => 'File too large to hand over, the person can open it via the url.']);
        }
        $content = (string) file_get_contents($path);
        if (str_starts_with($mimeType, 'text/') || \in_array($mimeType, ['application/json', 'application/xml', 'text/csv'], true)) {
            $text = mb_convert_encoding(substr($content, 0, self::MAX_TEXT_BYTES), 'UTF-8', mb_check_encoding($content, 'UTF-8') ? 'UTF-8' : 'Windows-1252');

            return new ToolResult($data + ['text' => $text, 'truncated' => \strlen($content) > self::MAX_TEXT_BYTES]);
        }
        if (\in_array($mimeType, ['image/png', 'image/jpeg', 'image/gif', 'image/webp'], true)) {
            return new ToolResult($data, [['type' => 'image', 'data' => base64_encode($content), 'mimeType' => $mimeType]]);
        }
        if ('application/pdf' === $mimeType) {
            return new ToolResult($data, [['type' => 'resource', 'resource' => [
                'uri' => 'rokoso://file/'.rawurlencode($filename),
                'mimeType' => $mimeType,
                'blob' => base64_encode($content),
            ]]]);
        }

        return new ToolResult($data + ['note' => 'This file type cannot be handed over, the person can open it via the url.']);
    }

    private static function mimeType(string $filename, string $mimeType): string
    {
        $mimeType = strtolower(trim(explode(';', $mimeType)[0]));
        if ('' === $mimeType || 'application/octet-stream' === $mimeType) {
            $guess = MimeTypes::getDefault()->getMimeTypes(strtolower(pathinfo($filename, \PATHINFO_EXTENSION)));
            $mimeType = $guess[0] ?? 'application/octet-stream';
        }

        return $mimeType;
    }

    private static function snake(string $path): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $path));
    }
}
