// ===== Mock data for laravel-flow admin =====
// Realistic enough to feel real, generic enough to fit any project.

const FLOW_DEFS = [
  { id: 'order_checkout_flow', name: 'OrderCheckoutFlow', steps: 6, version: 'v3.2' },
  { id: 'user_onboarding_flow', name: 'UserOnboardingFlow', steps: 4, version: 'v1.7' },
  { id: 'payout_settlement_flow', name: 'PayoutSettlementFlow', steps: 5, version: 'v2.1' },
  { id: 'invoice_generation_flow', name: 'InvoiceGenerationFlow', steps: 3, version: 'v1.4' },
  { id: 'fraud_review_flow', name: 'FraudReviewFlow', steps: 7, version: 'v4.0' },
  { id: 'subscription_renewal_flow', name: 'SubscriptionRenewalFlow', steps: 5, version: 'v2.8' },
  { id: 'kyc_verification_flow', name: 'KycVerificationFlow', steps: 6, version: 'v1.9' },
];

const STATUSES = ['running', 'success', 'failed', 'paused', 'compensated', 'pending'];

// Deterministic PRNG so list is stable across renders
function mulberry32(seed) {
  return function() {
    seed |= 0; seed = (seed + 0x6D2B79F5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  }
}

function genShortId(rng, prefix = '') {
  const chars = 'abcdef0123456789';
  let s = prefix;
  for (let i = 0; i < 12; i++) s += chars[Math.floor(rng() * chars.length)];
  return s;
}

function pick(rng, arr) { return arr[Math.floor(rng() * arr.length)]; }

function genRuns(count = 120) {
  const rng = mulberry32(42);
  const now = new Date('2026-05-05T14:32:00Z').getTime();
  const runs = [];
  for (let i = 0; i < count; i++) {
    const def = pick(rng, FLOW_DEFS);
    // Distribution: more success/running, some failed/paused
    const r = rng();
    let status;
    if (r < 0.55) status = 'success';
    else if (r < 0.75) status = 'running';
    else if (r < 0.83) status = 'failed';
    else if (r < 0.91) status = 'paused';
    else if (r < 0.96) status = 'compensated';
    else status = 'pending';

    const startedOffset = Math.floor(rng() * 1000 * 60 * 60 * 72); // last 72h
    const startedAt = now - startedOffset;
    const duration = status === 'running' || status === 'paused' || status === 'pending'
      ? Math.floor(rng() * 1000 * 60 * 4)
      : Math.floor(rng() * 1000 * 60 * 18) + 800;
    const finishedAt = (status === 'running' || status === 'paused' || status === 'pending') ? null : startedAt + duration;

    const stepsTotal = def.steps;
    let stepsDone;
    if (status === 'success') stepsDone = stepsTotal;
    else if (status === 'failed') stepsDone = Math.max(1, Math.floor(rng() * stepsTotal));
    else if (status === 'paused') stepsDone = Math.max(1, Math.floor(rng() * (stepsTotal - 1)));
    else if (status === 'running') stepsDone = Math.max(1, Math.floor(rng() * (stepsTotal - 1)));
    else if (status === 'compensated') stepsDone = stepsTotal;
    else stepsDone = 0;

    runs.push({
      id: genShortId(rng, 'fr_'),
      flow_def: def.id,
      flow_name: def.name,
      version: def.version,
      status,
      started_at: startedAt,
      finished_at: finishedAt,
      duration_ms: duration,
      steps_done: stepsDone,
      steps_total: stepsTotal,
      triggered_by: pick(rng, ['api', 'cli', 'webhook', 'scheduler', 'admin', 'system']),
      actor: pick(rng, [
        'api@system',
        'admin@example.test',
        'webhook:stripe',
        'scheduler:cron',
        'cli:artisan',
        'operator-1@example.test',
        'operator-2@example.test',
      ]),
      correlation_id: genShortId(rng, 'cor_'),
      retries: Math.floor(rng() * 3),
      idempotency_key: genShortId(rng, 'idem_'),
    });
  }
  return runs.sort((a, b) => b.started_at - a.started_at);
}

// Generate detail-level data for a single run
function genRunDetail(run) {
  const rng = mulberry32(parseInt(run.id.slice(3, 11), 16) || 1);
  const def = FLOW_DEFS.find(d => d.id === run.flow_def);
  const stepNames = {
    order_checkout_flow: ['ValidateCart', 'ReserveInventory', 'ChargePayment', 'CreateInvoice', 'SendConfirmation', 'NotifyWarehouse'],
    user_onboarding_flow: ['CreateAccount', 'SendWelcomeEmail', 'ProvisionWorkspace', 'AssignDefaultRole'],
    payout_settlement_flow: ['CalculateBalance', 'AwaitApproval', 'InitiateBankTransfer', 'PollTransferStatus', 'NotifyMerchant'],
    invoice_generation_flow: ['CollectLineItems', 'GeneratePDF', 'EmailInvoice'],
    fraud_review_flow: ['LoadSignals', 'ScoreRisk', 'AwaitManualReview', 'ApplyDecision', 'NotifyCustomer', 'AuditLog', 'CloseCase'],
    subscription_renewal_flow: ['LoadSubscription', 'ChargeRenewal', 'ExtendPeriod', 'EmailReceipt', 'UpdateLedger'],
    kyc_verification_flow: ['CollectDocuments', 'OcrExtract', 'ScreenSanctions', 'AwaitReviewer', 'PersistDecision', 'NotifyUser'],
  }[def.id] || Array.from({length: def.steps}, (_, i) => `Step${i+1}`);

  const steps = stepNames.map((name, i) => {
    let status;
    if (i < run.steps_done) status = 'success';
    else if (i === run.steps_done) {
      if (run.status === 'running') status = 'running';
      else if (run.status === 'failed') status = 'failed';
      else if (run.status === 'paused') status = 'paused';
      else if (run.status === 'pending') status = 'pending';
      else status = 'success';
    }
    else status = 'pending';

    if (run.status === 'compensated' && i >= run.steps_done - 2) status = 'compensated';

    const dur = status === 'pending' ? 0 :
                status === 'running' ? Math.floor(rng() * 8000) + 1200 :
                Math.floor(rng() * 6000) + 200;
    const startOffset = i * (Math.floor(rng() * 800) + 400);
    return {
      id: `step_${i+1}`,
      name,
      status,
      duration_ms: dur,
      started_at: run.started_at + startOffset,
      attempts: status === 'failed' ? Math.floor(rng() * 3) + 1 : 1,
      handler: `App\\\\Flows\\\\${def.name.replace('Flow','')}\\\\${name}Step`,
      input: status === 'pending' ? null : { sample: 'data', step: i+1 },
      output: status === 'success' ? { ok: true, ref: `${name.toLowerCase()}_${i+1}` } : null,
      error: status === 'failed' ? {
        class: 'Padosoft\\\\Flow\\\\Exceptions\\\\StepExecutionException',
        message: pick(rng, [
          'Connection timeout to upstream service after 30s',
          'Validation failed: amount must be positive',
          'Rate limit exceeded for provider:stripe',
          'Idempotency conflict: key already used',
        ]),
      } : null,
    };
  });

  // Audit events
  const audit = [];
  audit.push({ ts: run.started_at, event: 'flow.started', actor: run.actor, detail: `Triggered via ${run.triggered_by}` });
  steps.forEach((s, i) => {
    if (s.status !== 'pending') {
      audit.push({ ts: s.started_at, event: 'step.started', actor: 'system', detail: s.name });
      if (s.status === 'success' || s.status === 'compensated') {
        audit.push({ ts: s.started_at + s.duration_ms, event: 'step.completed', actor: 'system', detail: `${s.name} (${s.duration_ms}ms)` });
      }
      if (s.status === 'failed') {
        audit.push({ ts: s.started_at + s.duration_ms, event: 'step.failed', actor: 'system', detail: s.error?.message });
      }
      if (s.status === 'paused' && s.name.startsWith('Await')) {
        audit.push({ ts: s.started_at + 100, event: 'flow.paused', actor: 'system', detail: 'Awaiting external signal' });
      }
    }
  });
  if (run.status === 'compensated') {
    audit.push({ ts: run.finished_at || Date.now(), event: 'flow.compensated', actor: 'system', detail: 'Compensation chain executed' });
  }
  if (run.status === 'success') {
    audit.push({ ts: run.finished_at, event: 'flow.completed', actor: 'system', detail: `Total ${run.duration_ms}ms` });
  }
  audit.sort((a, b) => a.ts - b.ts);

  // Approvals (only for paused flows)
  const approvals = [];
  if (run.status === 'paused') {
    const pausedStep = steps.find(s => s.status === 'paused');
    if (pausedStep) {
      approvals.push({
        id: 'apv_' + run.id.slice(3, 11),
        step: pausedStep.name,
        requested_at: pausedStep.started_at,
        token: 'apv_tok_' + Math.random().toString(36).slice(2, 14),
        reason: 'Manual approval required',
      });
    }
  }

  // Outbox (webhook events)
  const outbox = [];
  steps.forEach((s, i) => {
    if (s.status === 'success' || s.status === 'failed') {
      outbox.push({
        id: 'evt_' + (parseInt(run.id.slice(3, 11), 16) + i).toString(36).slice(-10),
        topic: `${def.id}.${s.name.toLowerCase()}.${s.status === 'success' ? 'completed' : 'failed'}`,
        target: pick(rng, ['https://hooks.example.com/flow', 'https://stripe.com/webhooks', 'https://internal.api/sink', 'https://slack.com/webhooks/T0/B0']),
        attempts: s.status === 'failed' ? 3 : 1,
        status: s.status === 'failed' ? (rng() < 0.4 ? 'dead' : 'pending') : 'delivered',
        next_retry_at: s.status === 'failed' ? Date.now() + 30000 : null,
        last_response: s.status === 'failed' ? 'HTTP 502 Bad Gateway' : 'HTTP 200 OK',
      });
    }
  });

  return {
    ...run,
    steps,
    audit,
    approvals,
    outbox,
    payload: {
      run_id: run.id,
      flow: def.name,
      version: run.version,
      input: {
        order_id: 'ord_' + run.correlation_id.slice(4, 12),
        customer_id: 'cus_' + Math.random().toString(36).slice(2, 10),
        amount_cents: 12450,
        currency: 'EUR',
        metadata: {
          source: run.triggered_by,
          ip: '203.0.113.' + Math.floor(rng() * 255),
          user_agent: 'Mozilla/5.0',
        },
      },
      context: {
        retries: run.retries,
        idempotency_key: run.idempotency_key,
        correlation_id: run.correlation_id,
      },
    },
  };
}

// Histogram of runs over last 24 hours, hourly
function genHourly(runs) {
  const now = new Date('2026-05-05T14:32:00Z').getTime();
  const buckets = Array.from({length: 24}, (_, i) => ({
    hour: i,
    label: `${(new Date(now - (23-i) * 3600000).getUTCHours()).toString().padStart(2,'0')}:00`,
    success: 0,
    failed: 0,
    running: 0,
  }));
  runs.forEach(r => {
    const hoursAgo = Math.floor((now - r.started_at) / 3600000);
    if (hoursAgo >= 0 && hoursAgo < 24) {
      const idx = 23 - hoursAgo;
      if (r.status === 'success') buckets[idx].success++;
      else if (r.status === 'failed') buckets[idx].failed++;
      else if (r.status === 'running') buckets[idx].running++;
    }
  });
  return buckets;
}

const RUNS = genRuns(120);
const HOURLY = genHourly(RUNS);

// KPIs
const KPIS = (() => {
  const now = new Date('2026-05-05T14:32:00Z').getTime();
  const last24 = RUNS.filter(r => now - r.started_at < 86400000);
  const success = last24.filter(r => r.status === 'success').length;
  const failed = last24.filter(r => r.status === 'failed').length;
  const running = RUNS.filter(r => r.status === 'running').length;
  const paused = RUNS.filter(r => r.status === 'paused').length;
  const total24 = last24.length;
  const successRate = total24 > 0 ? (success / total24) * 100 : 0;
  const finishedRuns = last24.filter(r => r.finished_at);
  const avgDuration = finishedRuns.length > 0
    ? finishedRuns.reduce((s, r) => s + r.duration_ms, 0) / finishedRuns.length
    : 0;
  const p95 = (() => {
    const sorted = [...finishedRuns].sort((a, b) => a.duration_ms - b.duration_ms);
    return sorted[Math.floor(sorted.length * 0.95)]?.duration_ms || 0;
  })();
  return {
    runs_24h: total24,
    success_rate: successRate,
    failed_24h: failed,
    running_now: running,
    paused_now: paused,
    avg_duration_ms: avgDuration,
    p95_duration_ms: p95,
    success_24h: success,
  };
})();

window.FLOW_DATA = { FLOW_DEFS, RUNS, HOURLY, KPIS, genRunDetail, STATUSES };
