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
 * Demo taint SINK — stands in for the dangerous end: a shell command, a
 * tool name, a URL fetched with our credentials attached.
 *
 * `command` refuses untrusted data; `note` does not, so one demo node
 * shows both sides of the rule — that the gate is narrow on purpose, and
 * that most ports are supposed to carry other people's text.
 */
#[FlowNode(type: 'demo.run_command', category: 'ops', name: 'Run Command', icon: 'terminal', description: 'Runs a command. Its command port refuses untrusted data.')]
final class DemoRunCommandNode implements FlowNodeHandler
{
    #[Input(type: PortType::Text, required: true, label: 'Command', key: 'command', requiresTrusted: true)]
    public string $command = '';

    #[Input(type: PortType::Text, required: false, label: 'Note', key: 'note')]
    public string $note = '';

    public function execute(NodeContext $context): NodeResult
    {
        return NodeResult::success([]);
    }
}
