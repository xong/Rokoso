<?php

declare(strict_types=1);

namespace App\Tests\Twig;

use App\Twig\PanelWidthExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PanelWidthExtensionTest extends TestCase
{
    /**
     * @return iterable<string, array{string|null, float|null}>
     */
    public static function cookies(): iterable
    {
        yield 'no cookie' => [null, null];
        yield 'stored width' => ['33.25', 33.3];
        yield 'below minimum' => ['5', 15.0];
        yield 'above maximum' => ['90', 50.0];
        yield 'garbage' => ['30%;color:red', null];
    }

    #[DataProvider('cookies')]
    public function testListWidthFromCookie(?string $cookie, ?float $expected): void
    {
        $stack = new RequestStack();
        $stack->push(new Request(attributes: ['_route' => 'mail_show'], cookies: null === $cookie ? [] : ['panel_list_mail_inbox' => $cookie]));

        $panel = (new PanelWidthExtension($stack))->panelWidth('list');

        self::assertSame(['name' => 'list', 'cookie' => 'panel_list_mail_inbox', 'min' => 15, 'max' => 50, 'value' => $expected], $panel);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function routes(): iterable
    {
        yield 'mail folder' => ['mail_done', 'mail_inbox'];
        yield 'mail rules belong to the admin pages' => ['mail_rule_index', 'organization_index'];
        yield 'folders belong to files' => ['folder_show', 'file_index'];
        yield 'contacts' => ['contact_show', 'contact_index'];
        yield 'contact groups' => ['contact_group_show', 'contact_group_index'];
        yield 'facilities' => ['facility_index', 'facility_index'];
        yield 'outside the menu' => ['profile_edit', 'profile'];
    }

    #[DataProvider('routes')]
    public function testCookiePerPage(string $route, string $section): void
    {
        $stack = new RequestStack();
        $stack->push(new Request(attributes: ['_route' => $route]));

        self::assertSame('panel_comments_'.$section, (new PanelWidthExtension($stack))->panelWidth('comments')['cookie']);
    }
}
