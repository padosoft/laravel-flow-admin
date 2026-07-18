<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Stubs;

use Padosoft\LaravelFlow\FlowContext;
use Padosoft\LaravelFlow\FlowStepHandler;
use Padosoft\LaravelFlow\FlowStepResult;

/**
 * Minimal step handler for mutation Feature tests: it always succeeds and
 * writes no state, so a flow of `step → approvalGate → step` pauses at the
 * gate on execute() and completes once the approval is decided.
 */
final class AlwaysSucceedsHandler implements FlowStepHandler
{
    public function execute(FlowContext $context): FlowStepResult
    {
        return FlowStepResult::success();
    }
}
