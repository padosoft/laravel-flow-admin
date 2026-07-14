<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlowAdmin\Authorizers\DenyAllAuthorizer;
use Padosoft\LaravelFlowAdmin\Contracts\ActionAuthorizer;
use Padosoft\LaravelFlowAdmin\Support\Authorize;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class AuthorizeActionTest extends TestCase
{
    public function test_authorize_blocked_by_deny_all_returns_403(): void
    {
        $this->app->bind(ActionAuthorizer::class, DenyAllAuthorizer::class);
        $this->app->forgetInstance(ActionAuthorizer::class);

        try {
            Authorize::action('view_runs', fn (): string => 'ok');

            $this->fail('Expected action() to throw HttpException');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
            $this->assertSame('Action not authorized', $exception->getMessage());
        }
    }

    public function test_authorize_allows_with_allow_all_authorizer(): void
    {
        $this->app->bind(ActionAuthorizer::class, AllowAllAuthorizer::class);
        $this->app->forgetInstance(ActionAuthorizer::class);

        $result = Authorize::action('view_runs', fn (): string => 'ok');

        $this->assertSame('ok', $result);
    }
}

final class AllowAllAuthorizer implements ActionAuthorizer
{
    public function canViewRuns(?array $actor): bool
    {
        return true;
    }

    public function canViewRunDetail(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canReplayRun(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canApproveByToken(string $tokenHash, ?array $actor): bool
    {
        return true;
    }

    public function canRejectByToken(string $tokenHash, ?array $actor): bool
    {
        return true;
    }

    public function canCancelRun(string $runId, ?array $actor): bool
    {
        return true;
    }

    public function canRetryWebhook(int $outboxId, ?array $actor): bool
    {
        return true;
    }

    public function canViewKpis(?array $actor): bool
    {
        return true;
    }

    public function canEditDefinition(string $flowName, ?array $actor): bool
    {
        return true;
    }
}
