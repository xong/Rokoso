<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\Feature;
use App\Help\HelpCenter;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Routing\RouterInterface;

final class HelpCenterTest extends KernelTestCase
{
    public function testParseReadsFrontMatterTitleAndSummary(): void
    {
        $article = HelpCenter::parse('beispiel', <<<'MD'
            ---
            routes: [poll_, survey_]
            feature: polls
            group: guide
            ---
            # Beispiel

            Ein **kurzer** Text mit [Link](/help/forum).

            ## Abschnitt

            Mehr.
            MD);

        self::assertSame('Beispiel', $article->title);
        self::assertSame('Ein kurzer Text mit Link.', $article->summary);
        self::assertSame(['poll_', 'survey_'], $article->routes);
        self::assertSame(Feature::Polls, $article->feature);
        self::assertTrue($article->guide);
        self::assertStringContainsString('### Abschnitt', $article->body);
        self::assertStringNotContainsString('# Beispiel', $article->body);
    }

    public function testArticlesMatchRoutesAndLinkToExistingArticles(): void
    {
        $help = self::getContainer()->get(HelpCenter::class);
        $router = self::getContainer()->get(RouterInterface::class);
        $routes = array_keys($router->getRouteCollection()->all());
        $articles = $help->all();

        self::assertNotEmpty($articles);
        foreach ($articles as $slug => $article) {
            self::assertNotSame('', $article->title, $slug);
            self::assertNotSame('', $article->summary, $slug);
            foreach ($article->routes as $prefix) {
                self::assertNotEmpty(array_filter($routes, static fn (string $r): bool => str_starts_with($r, $prefix)), \sprintf('%s: no route starts with "%s"', $slug, $prefix));
            }
            preg_match_all('#\]\(/help/([a-z0-9-]+)#', $article->body, $links);
            foreach ($links[1] as $target) {
                self::assertArrayHasKey($target, $articles, \sprintf('%s links to missing article "%s"', $slug, $target));
            }
        }
    }
}
