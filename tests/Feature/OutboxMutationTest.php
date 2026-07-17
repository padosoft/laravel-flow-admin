<?php

declare(strict_types=1);

namespace Padosoft\LaravelFlowAdmin\Tests\Feature;

use Illuminate\Support\Facades\DB;

/**
 * E-PR6: the outbox Redeliver action requeues a FAILED webhook row through
 * `Flow::redeliverWebhook()`. Deny-by-default is mandatory; the allow path
 * proves a failed row is reset to pending and that any other state (here a
 * delivered row) yields a 409 rather than a silent no-op.
 */
final class OutboxMutationTest extends MutationTestCase
{
    public function test_redeliver_is_forbidden_by_default(): void
    {
        $response = $this->postJson(route('flow-admin.outbox.redeliver', ['id' => 1]));

        $response->assertStatus(403);
    }

    public function test_redeliver_requeues_a_failed_row(): void
    {
        $this->bootFlowPersistence();
        $this->allowAllActions();
        $id = $this->seedOutboxRow('failed', attempts: 3);

        $response = $this->postJson(route('flow-admin.outbox.redeliver', ['id' => $id]));

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $row = DB::table('flow_webhook_outbox')->where('id', $id)->first();
        $this->assertSame('pending', $row->status);
        $this->assertSame(0, (int) $row->attempts);
    }

    public function test_redeliver_of_a_non_failed_row_returns_409(): void
    {
        $this->bootFlowPersistence();
        $this->allowAllActions();
        $id = $this->seedOutboxRow('delivered', attempts: 1);

        $response = $this->postJson(route('flow-admin.outbox.redeliver', ['id' => $id]));

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);

        // The row is untouched — a wrong-state redeliver must be a no-op.
        $this->assertSame('delivered', DB::table('flow_webhook_outbox')->where('id', $id)->value('status'));
    }

    public function test_redeliver_with_a_non_canonical_id_returns_404(): void
    {
        $this->allowAllActions();

        // A leading-zero id doesn't round-trip through the (int) cast, so the
        // controller rejects it before authorizing/acting — it can't silently
        // resolve to a different row than the URL segment names.
        $response = $this->postJson(route('flow-admin.outbox.redeliver', ['id' => '007']));

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    private function seedOutboxRow(string $status, int $attempts): int
    {
        $now = now();

        return (int) DB::table('flow_webhook_outbox')->insertGetId([
            'event' => 'demo.webhook.event',
            'status' => $status,
            'attempts' => $attempts,
            'max_attempts' => 3,
            'last_error' => $status === 'failed' ? 'connection refused' : null,
            'failed_at' => $status === 'failed' ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
