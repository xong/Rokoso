<?php

declare(strict_types=1);

namespace App\Help;

use App\Entity\User;
use App\Enum\Feature;
use App\Service\Features;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Yaml\Yaml;

/**
 * Built-in help: Markdown articles in help/ ("NN-slug.md", sorted by NN). Each file starts with YAML front matter
 * (`routes`: route prefixes for the "?" link, `feature`: area it belongs to, `group: guide` for how-tos), then
 * "# Title" and a first paragraph that serves as summary. Articles of areas the user cannot use are hidden.
 */
final class HelpCenter
{
    /** @var array<string, HelpArticle>|null */
    private ?array $articles = null;

    public function __construct(
        private readonly Features $features,
        #[Autowire('%kernel.project_dir%/help')]
        private readonly string $directory,
    ) {
    }

    /**
     * @return array<string, HelpArticle> by slug, in file order
     */
    public function all(): array
    {
        if (null === $this->articles) {
            $this->articles = [];
            $files = glob($this->directory.'/*.md') ?: [];
            sort($files);
            foreach ($files as $file) {
                $article = self::parse((string) preg_replace('/^\d+-/', '', basename($file, '.md')), (string) file_get_contents($file));
                $this->articles[$article->slug] = $article;
            }
        }

        return $this->articles;
    }

    /**
     * @return array<string, HelpArticle>
     */
    public function availableTo(User $user): array
    {
        return array_filter($this->all(), fn (HelpArticle $a): bool => $this->isAvailable($a, $user));
    }

    public function find(string $slug, User $user): ?HelpArticle
    {
        $article = $this->all()[$slug] ?? null;

        return null !== $article && $this->isAvailable($article, $user) ? $article : null;
    }

    /**
     * Article explaining the given route: the longest matching prefix wins.
     */
    public function forRoute(string $route, User $user): ?HelpArticle
    {
        $best = null;
        $bestLength = 0;
        foreach ($this->all() as $article) {
            foreach ($article->routes as $prefix) {
                if (str_starts_with($route, $prefix) && \strlen($prefix) > $bestLength) {
                    $best = $article;
                    $bestLength = \strlen($prefix);
                }
            }
        }

        return null !== $best && $this->isAvailable($best, $user) ? $best : null;
    }

    /**
     * Articles containing all words of the query; matches in the title first.
     *
     * @return list<HelpArticle>
     */
    public function search(string $query, User $user, int $limit = 8): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($query)), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $words) {
            return [];
        }
        $hits = [];
        foreach ($this->availableTo($user) as $article) {
            $title = mb_strtolower($article->title);
            $text = $title."\n".mb_strtolower($article->body);
            $inTitle = 0;
            foreach ($words as $word) {
                if (!str_contains($text, $word)) {
                    continue 2;
                }
                $inTitle += str_contains($title, $word) ? 1 : 0;
            }
            $hits[] = [$inTitle, $article];
        }
        usort($hits, static fn (array $a, array $b): int => $b[0] <=> $a[0]);

        return \array_slice(array_column($hits, 1), 0, $limit);
    }

    private function isAvailable(HelpArticle $article, User $user): bool
    {
        return null === $article->feature || $this->features->isAvailable($user, $article->feature);
    }

    public static function parse(string $slug, string $markdown): HelpArticle
    {
        $markdown = str_replace("\r\n", "\n", $markdown);
        $meta = [];
        if (preg_match('/\A---\n(.*?)\n---\n/s', $markdown, $m)) {
            $meta = Yaml::parse($m[1]);
            $markdown = substr($markdown, \strlen($m[0]));
        }
        $meta = \is_array($meta) ? $meta : [];

        $title = $slug;
        if (preg_match('/^# (.+)$/m', $markdown, $m, \PREG_OFFSET_CAPTURE)) {
            $title = trim($m[1][0]);
            $markdown = substr($markdown, $m[0][1] + \strlen($m[0][0]));
        }
        // the page has the title as h2 already: "## Section" becomes h3
        $body = trim((string) preg_replace('/^(#{2,5}) /m', '#$1 ', $markdown));
        $summary = trim(explode("\n\n", $body, 2)[0]);
        // plain text: no Markdown emphasis or link targets in lists and search results
        $summary = (string) preg_replace(['/\[([^]]*)\]\([^)]*\)/', '/[*_`]/'], ['$1', ''], $summary);

        $routes = array_values(array_map(strval(...), (array) ($meta['routes'] ?? [])));

        return new HelpArticle(
            $slug,
            $title,
            $summary,
            $body,
            $routes,
            isset($meta['feature']) ? Feature::from((string) $meta['feature']) : null,
            'guide' === ($meta['group'] ?? null),
        );
    }
}
