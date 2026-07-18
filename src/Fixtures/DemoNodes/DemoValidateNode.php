<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Fixtures\DemoNodes;

use Padosoft\LaravelFlow\Node\Attributes\FlowNode;
use Padosoft\LaravelFlow\Node\Attributes\Input;
use Padosoft\LaravelFlow\Node\Attributes\Output;
use Padosoft\LaravelFlow\Node\FlowNodeHandler;
use Padosoft\LaravelFlow\Node\NodeContext;
use Padosoft\LaravelFlow\Node\NodeResult;
use Padosoft\LaravelFlow\Node\PortType;

/**
 * Real counterpart of `ArrayReadModel`'s `demo.validate` fixture catalog
 * entry. See `DemoTriggerNode` for why this class exists.
 */
#[FlowNode(type: 'demo.validate', category: 'logic', name: 'Validate Order', icon: 'check', description: 'Validates the order payload.')]
final class DemoValidateNode implements FlowNodeHandler
{
    /** @var array<string, mixed> */
    #[Input(type: PortType::Json, required: true, label: 'Order payload', key: 'in')]
    public array $in;

    #[Output(type: PortType::Bool, label: 'Is valid', key: 'valid')]
    public bool $valid;

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::success(['valid' => true]);
    }
}
