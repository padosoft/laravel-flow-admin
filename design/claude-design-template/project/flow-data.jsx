// ============================================================
// Flow Studio — data model: node catalog, example graphs, runs
// ============================================================

// ---- Port type → color token mapping ----
const PORT_TYPES = {
  text:   { color: 'var(--port-text)',   label: 'Text' },
  int:    { color: 'var(--port-num)',    label: 'Integer' },
  float:  { color: 'var(--port-num)',    label: 'Float' },
  bool:   { color: 'var(--port-bool)',   label: 'Boolean' },
  json:   { color: 'var(--port-json)',   label: 'JSON' },
  any:    { color: 'var(--port-any)',    label: 'Any' },
  binary: { color: 'var(--port-binary)', label: 'Binary' },
};

function portTypesCompatible(a, b) {
  if (a === b) return true;
  if (a === 'any' || b === 'any') return true;
  if ((a === 'int' || a === 'float') && (b === 'int' || b === 'float')) return true;
  return false;
}

// ---- Node catalog (palette is generated from this) ----
const NODE_CATALOG = [
  // Control
  { type: 'control.start', name: 'Start', category: 'Control', icon: 'Play',
    inputs: [], outputs: [{ key: 'trigger', type: 'any' }], color: 'var(--cat-control)' },
  { type: 'control.branch', name: 'Branch (If/Else)', category: 'Control', icon: 'GitBranch',
    inputs: [{ key: 'condition', type: 'bool', required: true }],
    outputs: [{ key: 'true', type: 'any' }, { key: 'false', type: 'any' }], color: 'var(--cat-control)' },
  { type: 'control.foreach', name: 'For Each', category: 'Control', icon: 'Repeat',
    inputs: [{ key: 'items', type: 'json', required: true }],
    outputs: [{ key: 'item', type: 'json' }, { key: 'done', type: 'any' }], color: 'var(--cat-control)' },
  { type: 'control.end', name: 'End', category: 'Control', icon: 'Square',
    inputs: [{ key: 'result', type: 'any' }], outputs: [], color: 'var(--cat-control)' },

  // Data
  { type: 'data.transform', name: 'Transform', category: 'Data', icon: 'Shuffle',
    inputs: [{ key: 'input', type: 'json', required: true }],
    outputs: [{ key: 'output', type: 'json' }], color: 'var(--cat-data)' },
  { type: 'data.filter', name: 'Filter', category: 'Data', icon: 'Filter',
    inputs: [{ key: 'collection', type: 'json', required: true }, { key: 'predicate', type: 'text' }],
    outputs: [{ key: 'result', type: 'json' }], color: 'var(--cat-data)' },
  { type: 'data.merge', name: 'Merge', category: 'Data', icon: 'Merge',
    inputs: [{ key: 'a', type: 'json', required: true }, { key: 'b', type: 'json', required: true }],
    outputs: [{ key: 'merged', type: 'json' }], color: 'var(--cat-data)' },

  // Connect / HTTP
  { type: 'http.request', name: 'HTTP Request', category: 'Connect', icon: 'Globe',
    inputs: [{ key: 'url', type: 'text', required: true }, { key: 'body', type: 'json' }],
    outputs: [{ key: 'response', type: 'json' }, { key: 'status', type: 'int' }], color: 'var(--cat-connect)' },
  { type: 'db.query', name: 'DB Query', category: 'Connect', icon: 'Database',
    inputs: [{ key: 'query', type: 'text', required: true }, { key: 'bindings', type: 'json' }],
    outputs: [{ key: 'rows', type: 'json' }, { key: 'count', type: 'int' }], color: 'var(--cat-connect)' },
  { type: 'webhook.emit', name: 'Emit Webhook', category: 'Connect', icon: 'Send',
    inputs: [{ key: 'topic', type: 'text', required: true }, { key: 'payload', type: 'json', required: true }],
    outputs: [{ key: 'delivered', type: 'bool' }], color: 'var(--cat-connect)' },

  // AI
  { type: 'ai.llm', name: 'LLM Prompt', category: 'AI', icon: 'Sparkle',
    inputs: [{ key: 'prompt', type: 'text', required: true }, { key: 'context', type: 'json' }],
    outputs: [{ key: 'answer', type: 'json' }], color: 'var(--cat-ai)', cost: true },
  { type: 'ai.classify', name: 'Classify', category: 'AI', icon: 'Tag',
    inputs: [{ key: 'input', type: 'text', required: true }, { key: 'labels', type: 'json', required: true }],
    outputs: [{ key: 'label', type: 'text' }, { key: 'confidence', type: 'float' }], color: 'var(--cat-ai)', cost: true },
  { type: 'ai.vision', name: 'Image Analysis', category: 'AI', icon: 'Eye',
    inputs: [{ key: 'image', type: 'binary', required: true }, { key: 'question', type: 'text' }],
    outputs: [{ key: 'analysis', type: 'json' }], color: 'var(--cat-ai)', cost: true },

  // Human
  { type: 'human.approval', name: 'Approval Gate', category: 'Human', icon: 'UserCheck',
    inputs: [{ key: 'summary', type: 'text', required: true }],
    outputs: [{ key: 'decision', type: 'bool' }], color: 'var(--cat-human)' },
  { type: 'human.input', name: 'Collect Input', category: 'Human', icon: 'Edit',
    inputs: [{ key: 'form', type: 'json', required: true }],
    outputs: [{ key: 'values', type: 'json' }], color: 'var(--cat-human)' },

  // Sub-flow
  { type: 'flow.subflow', name: 'Sub-flow', category: 'Legacy', icon: 'Boxes',
    inputs: [{ key: 'input', type: 'json', required: true }],
    outputs: [{ key: 'output', type: 'json' }], color: 'var(--cat-legacy)', subflow: true },
];

function catalogByType(type) { return NODE_CATALOG.find(n => n.type === type); }

// ---- Example flow: "Order refund with approval" ----
function makeRefundFlow() {
  return {
    id: 'flow_refund',
    name: 'Order Refund with Approval',
    version: 7,
    state: 'published',
    nodes: [
      { id: 'n1', type: 'control.start',   position: { x: 40,  y: 240 }, config: {} },
      { id: 'n2', type: 'db.query',        position: { x: 260, y: 200 }, config: { query: 'select * from orders where id = ?' } },
      { id: 'n3', type: 'ai.llm',          position: { x: 520, y: 140 }, config: { prompt: 'Summarize fraud risk for this order', model: 'claude-sonnet' } },
      { id: 'n4', type: 'human.approval',  position: { x: 780, y: 220 }, config: { summary: 'Approve refund of €124.50?' } },
      { id: 'n5', type: 'http.request',    position: { x: 1040, y: 160 }, config: { url: 'https://api.stripe.com/v1/refunds', method: 'POST' } },
      { id: 'n6', type: 'webhook.emit',    position: { x: 1300, y: 220 }, config: { topic: 'refund.completed' } },
      { id: 'n7', type: 'control.end',     position: { x: 1540, y: 240 }, config: {} },
    ],
    connections: [
      { id: 'c1', sourceNodeId: 'n1', sourcePortKey: 'trigger',  targetNodeId: 'n2', targetPortKey: 'query' },
      { id: 'c2', sourceNodeId: 'n2', sourcePortKey: 'rows',     targetNodeId: 'n3', targetPortKey: 'context' },
      { id: 'c3', sourceNodeId: 'n3', sourcePortKey: 'answer',   targetNodeId: 'n4', targetPortKey: 'summary' },
      { id: 'c4', sourceNodeId: 'n4', sourcePortKey: 'decision', targetNodeId: 'n5', targetPortKey: 'body' },
      { id: 'c5', sourceNodeId: 'n5', sourcePortKey: 'response', targetNodeId: 'n6', targetPortKey: 'payload' },
      { id: 'c6', sourceNodeId: 'n6', sourcePortKey: 'delivered',targetNodeId: 'n7', targetPortKey: 'result' },
    ],
  };
}

// ---- Flows library ----
const FLOWS_LIBRARY = [
  { id: 'flow_refund', name: 'Order Refund with Approval', version: 7, state: 'published', successRate: 98.2, runs: 1284, lastRun: '4m ago', tags: ['payments','human'], nodes: 7, costTrend: [4,5,4,6,5,7,6,8], costPerRun: 0.08 },
  { id: 'flow_imgpipe', name: 'Product Image Pipeline', version: 3, state: 'published', successRate: 94.1, runs: 8420, lastRun: '1m ago', tags: ['ai','batch'], nodes: 9, costTrend: [12,14,13,16,18,15,19,22], costPerRun: 0.42 },
  { id: 'flow_onboard', name: 'User Onboarding', version: 12, state: 'published', successRate: 99.6, runs: 5610, lastRun: '18m ago', tags: ['lifecycle'], nodes: 5, costTrend: [2,2,3,2,3,3,2,3], costPerRun: 0.01 },
  { id: 'flow_kyc', name: 'KYC Verification', version: 4, state: 'draft', successRate: 0, runs: 0, lastRun: '—', tags: ['compliance','ai'], nodes: 6, costTrend: [], costPerRun: 0 },
  { id: 'flow_payout', name: 'Payout Settlement', version: 5, state: 'published', successRate: 91.4, runs: 942, lastRun: '2h ago', tags: ['payments'], nodes: 8, costTrend: [1,1,2,1,1,2,1,1], costPerRun: 0.02 },
  { id: 'flow_dunning', name: 'Dunning Campaign', version: 2, state: 'archived', successRate: 88.0, runs: 320, lastRun: '12d ago', tags: ['billing'], nodes: 6, costTrend: [3,2,3,4,3,2,3,3], costPerRun: 0.05 },
];

// ---- Advisor suggestions ----
const ADVISOR_SUGGESTIONS = [
  { id: 'adv1', kind: 'new', title: 'Automate abandoned-cart recovery', rationale: ['You have an EmailSend tool', 'Cart events fire 240×/day', 'No flow handles them'],
    benefit: 'high', preview: 'cart.abandoned → wait 1h → LLM personalize → EmailSend' },
  { id: 'adv2', kind: 'improve', flow: 'Product Image Pipeline', title: 'Add retry + cache to “Image Analysis”',
    detail: 'Node failed 12× this week and costs €4.20/run. A cache on identical images would cut cost ~38%.',
    severity: 'medium', benefit: 'Save ~€1.6/run', node: 'Image Analysis' },
  { id: 'adv3', kind: 'improve', flow: 'Order Refund with Approval', title: 'Parallelize fraud check and inventory lookup',
    detail: 'These two nodes have no dependency but run sequentially, adding ~800ms to every run.',
    severity: 'low', benefit: 'Save ~800ms/run', node: 'DB Query' },
  { id: 'adv4', kind: 'new', title: 'Weekly revenue digest to Slack', rationale: ['Slack webhook configured', 'You query revenue daily by hand'],
    benefit: 'medium', preview: 'schedule(mon 9am) → DB Query → LLM summarize → Slack' },
];

// ---- Live run generator ----
function makeRefundRun(progressIndex) {
  const flow = makeRefundFlow();
  // Assign live states based on progressIndex (0..7)
  const order = ['n1','n2','n3','n4','n5','n6','n7'];
  const states = {};
  order.forEach((id, i) => {
    if (i < progressIndex) states[id] = 'completed';
    else if (i === progressIndex) states[id] = id === 'n4' ? 'paused' : 'running';
    else states[id] = 'pending';
  });
  // n3 is a cache hit example, n2 completed
  if (progressIndex > 2) states['n3'] = 'cache-hit';
  return { flow, states };
}

const RUN_EVENTS = [
  { t: '00:00.000', node: 'Start', msg: 'Flow started · correlation cor_9f2a1b', level: 'info' },
  { t: '00:00.128', node: 'DB Query', msg: 'SELECT orders → 1 row (128ms)', level: 'info' },
  { t: '00:00.640', node: 'LLM Prompt', msg: 'cache hit ⚡ (0ms, €0.00)', level: 'ok' },
  { t: '00:00.642', node: 'Approval Gate', msg: 'Paused — awaiting human decision', level: 'warn' },
];

const RUNS_LIST = [
  { id: 'run_8a3f', flow: 'Order Refund with Approval', version: 7, status: 'paused', duration: '—', cost: 0.00, trigger: 'manual', started: '2m ago' },
  { id: 'run_7c1d', flow: 'Product Image Pipeline', version: 3, status: 'running', duration: '4.2s', cost: 0.31, trigger: 'schedule', started: '3m ago' },
  { id: 'run_6b9e', flow: 'User Onboarding', version: 12, status: 'success', duration: '1.8s', cost: 0.01, trigger: 'event', started: '12m ago' },
  { id: 'run_5f2a', flow: 'Order Refund with Approval', version: 7, status: 'success', duration: '9.1s', cost: 0.08, trigger: 'webhook', started: '18m ago' },
  { id: 'run_4d8c', flow: 'Payout Settlement', version: 5, status: 'failed', duration: '2.4s', cost: 0.02, trigger: 'schedule', started: '31m ago' },
  { id: 'run_3e7b', flow: 'Product Image Pipeline', version: 3, status: 'success', duration: '6.7s', cost: 0.42, trigger: 'mcp', started: '44m ago' },
  { id: 'run_2a5f', flow: 'KYC Verification', version: 4, status: 'success', duration: '3.3s', cost: 0.11, trigger: 'manual', started: '1h ago' },
  { id: 'run_1c4d', flow: 'User Onboarding', version: 12, status: 'success', duration: '1.9s', cost: 0.01, trigger: 'event', started: '1h ago' },
];

const APPROVALS = [
  { id: 'apv_1', flow: 'Order Refund with Approval', run: 'run_8a3f', step: 'Approval Gate', requested: '2m ago', expiresIn: 3480,
    impact: 'Refund €124.50 to customer cus_a1b2 via Stripe, then emit refund.completed webhook.',
    requester: 'checkout-service', context: { order: 'ord_9f2a', amount: '€124.50', reason: 'duplicate charge' } },
  { id: 'apv_2', flow: 'Payout Settlement', run: 'run_ab12', step: 'Await Treasury Sign-off', requested: '22m ago', expiresIn: 1200,
    impact: 'Initiate SEPA transfer of €12,400.00 to merchant mrc_88. Irreversible once sent.',
    requester: 'scheduler:cron', context: { merchant: 'mrc_88', amount: '€12,400.00', period: '2026-W18' } },
];

const WEBHOOKS_OUTBOX = [
  { id: 'evt_9f21', topic: 'refund.completed', target: 'https://hooks.acme.io/flow', status: 'delivered', attempts: 1, next: '—', code: '200 OK' },
  { id: 'evt_8a10', topic: 'image.published', target: 'https://cdn.acme.io/hook', status: 'delivered', attempts: 2, next: '—', code: '200 OK' },
  { id: 'evt_7b93', topic: 'payout.initiated', target: 'https://bank.example/wh', status: 'pending', attempts: 2, next: '28s', code: '502 Bad Gateway' },
  { id: 'evt_6c44', topic: 'kyc.flagged', target: 'https://compliance.io/in', status: 'dead', attempts: 5, next: '—', code: '500 Internal Error' },
  { id: 'evt_5d27', topic: 'order.refunded', target: 'https://slack.com/wh/T0', status: 'delivered', attempts: 1, next: '—', code: '200 OK' },
];

window.FLOW = {
  PORT_TYPES, portTypesCompatible, NODE_CATALOG, catalogByType,
  makeRefundFlow, FLOWS_LIBRARY, ADVISOR_SUGGESTIONS, makeRefundRun,
  RUN_EVENTS, RUNS_LIST, APPROVALS, WEBHOOKS_OUTBOX,
};
