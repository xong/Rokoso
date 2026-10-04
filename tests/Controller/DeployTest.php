<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\AppTestCase;

/**
 * Deploy hook: only with the token, only for the expected release.
 */
final class DeployTest extends AppTestCase
{
    public function testWrongTokenIsForbidden(): void
    {
        $this->client->request('POST', '/_deploy', ['release' => 'x'], [], ['HTTP_X_DEPLOY_TOKEN' => 'falsch']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/_deploy');
        self::assertResponseStatusCodeSame(405);
    }

    public function testOtherReleaseAsksToRetry(): void
    {
        $this->client->request('POST', '/_deploy', ['release' => 'older-release'], [], ['HTTP_X_DEPLOY_TOKEN' => 'test-deploy-token-0123456789abcdef']);
        self::assertResponseStatusCodeSame(409);
        self::assertStringContainsString('running release: '.basename(self::getContainer()->getParameter('kernel.project_dir')), (string) $this->client->getInternalResponse()->getContent());
    }
}
