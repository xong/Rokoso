<?php

declare(strict_types=1);

namespace App\Controller;

use App\Agent\AgentContext;
use App\Agent\Mcp\McpServer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * MCP endpoint for AI agents (streamable HTTP, answers as plain JSON, no sessions or server-sent events).
 * Authentication by bearer token in the "api" firewall ({@see \App\Agent\AgentTokenAuthenticator}).
 */
final class McpController extends AbstractController
{
    #[Route('/mcp', name: 'mcp', methods: ['GET', 'POST', 'DELETE'])]
    public function __invoke(
        Request $request,
        AgentContext $agent,
        McpServer $server,
        #[Autowire(service: 'limiter.agent_api')]
        RateLimiterFactoryInterface $limiter,
    ): Response {
        if (!$request->isMethod('POST')) {
            return new Response(null, Response::HTTP_METHOD_NOT_ALLOWED, ['Allow' => 'POST']);
        }
        $token = $agent->current() ?? throw $this->createAccessDeniedException();
        $limit = $limiter->create('token-'.$token->getId())->consume();
        if (!$limit->isAccepted()) {
            return new JsonResponse(['error' => 'too_many_requests'], Response::HTTP_TOO_MANY_REQUESTS, [
                'Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time()),
            ]);
        }

        try {
            $message = json_decode($request->getContent(), true, 64, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return self::rpc(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']], Response::HTTP_BAD_REQUEST);
        }
        $response = $server->handle($message, $token);

        return null === $response ? new Response(null, Response::HTTP_ACCEPTED) : self::rpc($response);
    }

    /**
     * @param array<mixed> $data
     */
    private static function rpc(array $data, int $status = Response::HTTP_OK): JsonResponse
    {
        $response = new JsonResponse(null, $status);
        $response->setEncodingOptions(\JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);

        return $response->setData($data);
    }
}
