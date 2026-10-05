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
        $stack->push(new Request(cookies: null === $cookie ? [] : ['panel_list' => $cookie]));

        $panel = (new PanelWidthExtension($stack))->panelWidth('list');

        self::assertSame(['name' => 'list', 'min' => 15, 'max' => 50, 'value' => $expected], $panel);
    }
}
