<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes;

use Padosoft\LaravelFlow\Node\Attributes\FlowNode;
use Padosoft\LaravelFlow\Node\Attributes\Input;
use Padosoft\LaravelFlow\Node\FlowNodeHandler;
use Padosoft\LaravelFlow\Node\NodeContext;
use Padosoft\LaravelFlow\Node\NodeResult;
use Padosoft\LaravelFlow\Node\PortType;

/**
 * Real counterpart of `ArrayReadModel`'s `demo.notify` fixture catalog
 * entry. See `DemoTriggerNode` for why this class exists.
 */
#[FlowNode(type: 'demo.notify', category: 'notification', name: 'Notify Customer', icon: 'bell', description: 'Sends a confirmation.')]
final class DemoNotifyNode implements FlowNodeHandler
{
    #[Input(type: PortType::Text, required: true, label: 'Message', key: 'message')]
    public string $message;

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::success([]);
    }
}
