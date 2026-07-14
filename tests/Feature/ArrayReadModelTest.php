<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Padosoft\LaravelFlowAdmin\Adapters\ArrayReadModel;
use Padosoft\LaravelFlowAdmin\Contracts\Dto\RunSummary;
use Padosoft\LaravelFlowAdmin\Tests\TestCase;

final class ArrayReadModelTest extends TestCase
{
    public function test_array_read_model_loads_fixture_and_reports_120_runs(): void
    {
        $model = new ArrayReadModel;
        $result = $model->listRuns(perPage: 250);

        $this->assertSame(120, $result->total);
        $this->assertCount(120, $result->items);
    }

    public function test_array_read_model_status_distribution_matches_fixture_seed(): void
    {
        $result = (new ArrayReadModel)->listRuns(perPage: 250);

        $counts = [];
        foreach ($result->items as $run) {
            $this->assertInstanceOf(RunSummary::class, $run);
            $counts[$run->status] = ($counts[$run->status] ?? 0) + 1;
        }

        $expected = [
            'running' => 20,
            'success' => 65,
            'paused' => 14,
            'failed' => 11,
            'compensated' => 4,
            'pending' => 6,
        ];

        $this->assertSame($expected, $counts);
    }

    public function test_array_read_model_status_filter_and_query_filter_are_consistent(): void
    {
        $model = new ArrayReadModel;

        $running = $model->listRuns(status: 'running', perPage: 250);
        $this->assertSame(20, $running->total);
        foreach ($running->items as $run) {
            $this->assertSame('running', $run->status);
        }

        $query = $model->listRuns(query: 'fr_', perPage: 250);
        $this->assertSame(120, $query->total);
        $this->assertNotEmpty($query->items);
    }

    public function test_array_read_model_returns_a_fixture_graph_for_the_known_flow(): void
    {
        $result = (new ArrayReadModel)->graph('order_checkout_flow');

        $this->assertNotNull($result);
        $this->assertSame(1, $result['graph']['schema_version']);
        $this->assertCount(4, $result['graph']['nodes']);
        $this->assertCount(3, $result['graph']['connections']);

        // Exercises 3 distinct PortTypes across the graph's wires, so the
        // canvas has something real to color-code.
        $this->assertSame('json', $result['catalog']['demo.trigger']['outputs'][0]['type']);
        $this->assertSame('bool', $result['catalog']['demo.validate']['outputs'][0]['type']);
        $this->assertSame('text', $result['catalog']['demo.charge']['outputs'][0]['type']);
    }

    public function test_array_read_model_returns_null_graph_for_an_unknown_flow(): void
    {
        $this->assertNull((new ArrayReadModel)->graph('does-not-exist'));
    }
}
