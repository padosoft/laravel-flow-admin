import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { ReactFlow, ReactFlowProvider, Background } from '@xyflow/react';
import '@xyflow/react/dist/style.css';

/**
 * The Flow Studio React island's entry point. E-PR1's scope is proving the
 * build/serve/mount pipeline end to end with `@xyflow/react` wired in — an
 * empty, read-only canvas. Rendering a real published graph (nodes from the
 * catalog, typed color-coded wires) is E-PR2's job.
 */
function StudioApp() {
  return (
    <div data-testid="flow-studio-canvas" style={{ width: '100%', height: '100%' }}>
      <ReactFlowProvider>
        {/* hideAttribution is explicitly MIT-permitted, not a Pro-only
            feature — xyflow's own docs: "Anyone is free to remove this
            attribution whether they're a Pro subscriber or not." Keeping
            the admin panel's own chrome (sidebar/topbar) consistent takes
            priority over a third-party badge. */}
        <ReactFlow nodes={[]} edges={[]} fitView proOptions={{ hideAttribution: true }}>
          <Background />
        </ReactFlow>
      </ReactFlowProvider>
    </div>
  );
}

const mountPoint = document.getElementById('flow-studio-root');

if (mountPoint) {
  createRoot(mountPoint).render(
    <StrictMode>
      <StudioApp />
    </StrictMode>,
  );
}
