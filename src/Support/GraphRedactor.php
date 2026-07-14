<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Support;

/**
 * Strips per-node `config` from a `GraphSerializer::toArray()` envelope
 * before it leaves the ReadModel layer. Core has no "sensitive config key"
 * concept — a node's `config` is an arbitrary operator-supplied array that
 * routinely carries API keys, bearer tokens, or webhook secrets for
 * HTTP/API-call node types. The Studio read-only canvas (E-PR2) only needs
 * `id`/`type`/`position` to render nodes, so the safest contract is to
 * never forward `config` to the browser until a later subtask defines an
 * explicit "safe to display/edit" schema for it.
 *
 * Scope: only per-node `config` is stripped. The envelope's top-level
 * `metadata` passes through untouched — nothing in core or this package's
 * fixtures stashes secrets there today, but a future subtask that starts
 * writing free-form data into graph-level `metadata` must extend this
 * redaction (or add a dedicated one) before it reaches the client.
 */
final class GraphRedactor
{
    /**
     * @param  array<string, mixed>  $graph  a `GraphSerializer::toArray()` envelope
     * @return array<string, mixed> the same envelope with every node's `config` removed
     */
    public static function stripNodeConfig(array $graph): array
    {
        $nodes = $graph['nodes'] ?? null;

        if (! is_array($nodes)) {
            return $graph;
        }

        $graph['nodes'] = array_map(
            static function (mixed $node): mixed {
                if (! is_array($node)) {
                    return $node;
                }

                unset($node['config']);

                return $node;
            },
            $nodes,
        );

        return $graph;
    }
}
