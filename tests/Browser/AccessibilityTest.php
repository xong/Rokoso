<?php

declare(strict_types=1);

namespace App\Tests\Browser;

/**
 * Automatic accessibility check with axe-core on the main pages (light and dark theme).
 * axe-core (MPL-2.0) is not part of the project: it is downloaded once into var/ and only injected into the
 * test browser. Fails on "serious" and "critical" findings (WCAG 2.2 A/AA rules).
 */
final class AccessibilityTest extends BrowserTestCase
{
    private const string AXE_VERSION = '4.11.0';

    private const array PAGES = ['/', '/mail', '/mail/new', '/calendar', '/tasks', '/meetings', '/polls', '/forum', '/files', '/contacts', '/projects', '/knowledge', '/notifications', '/profile', '/profile/security', '/organizations', '/search?q=Eltern', '/admin', '/admin/organizations', '/admin/log'];

    public function testMainPagesHaveNoSeriousViolations(): void
    {
        $this->client->request('GET', '/login');
        $problems = $this->check('/login');

        $user = $this->createUser();
        $user->setPlatformAdmin(true);
        $this->createOrganization($user);
        $this->login($user);
        foreach (['light', 'dark'] as $theme) {
            foreach (self::PAGES as $path) {
                $this->client->request('GET', $path);
                $this->client->waitFor('main');
                $this->client->executeScript(\sprintf('document.documentElement.dataset.theme = %s', json_encode($theme)));
                $problems = [...$problems, ...$this->check($path.' ('.$theme.')')];
            }
        }

        self::assertSame([], $problems, "axe findings:\n".implode("\n", $problems));
    }

    /**
     * @return list<string>
     */
    private function check(string $label): array
    {
        // no colour transitions while measuring contrast
        $this->client->executeScript("const s = document.createElement('style'); s.textContent = '*, *::before, *::after { transition: none !important; animation: none !important; }'; document.head.append(s);");
        $this->client->executeScript((string) file_get_contents($this->axe()));
        /** @var list<array{id: string, impact: ?string, help: string, nodes: list<array{target: list<string>, message: string}>}> $violations */
        $violations = $this->client->executeAsyncScript(<<<'JS'
            const done = arguments[arguments.length - 1];
            axe.run(document, {runOnly: {type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']}})
                .then(r => done(r.violations.map(v => ({id: v.id, impact: v.impact, help: v.help, nodes: v.nodes.slice(0, 5).map(n => ({target: n.target.map(String), message: (n.any[0] || n.all[0] || n.none[0] || {}).message || ''}))}))))
                .catch(e => done([{id: 'axe-error', impact: 'critical', help: String(e), nodes: []}]));
            JS);

        $problems = [];
        foreach ($violations as $v) {
            if (\in_array($v['impact'], ['serious', 'critical'], true)) {
                $targets = implode('
    ', array_map(static fn (array $n): string => implode(' ', $n['target']).('' !== $n['message'] ? ' ('.$n['message'].')' : ''), $v['nodes']));
                $problems[] = \sprintf('%s: [%s] %s – %s', $label, $v['id'], $v['help'], $targets);
            }
        }

        return $problems;
    }

    private function axe(): string
    {
        $file = \dirname(__DIR__, 2).'/var/axe-'.self::AXE_VERSION.'.min.js';
        if (!is_file($file)) {
            $script = file_get_contents('https://cdn.jsdelivr.net/npm/axe-core@'.self::AXE_VERSION.'/axe.min.js');
            if (false === $script) {
                self::markTestSkipped('axe-core could not be downloaded.');
            }
            file_put_contents($file, $script);
        }

        return $file;
    }
}
