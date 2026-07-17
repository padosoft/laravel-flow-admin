{{--
    E-PR6 action runner. Progressive-enhancement handler shared by the
    approvals, outbox and run-detail pages. Any element carrying
    `data-flow-action` + `data-action-url` becomes an async POST button:
      - optional `data-confirm="…"` gates destructive actions (reject/cancel)
      - reads the csrf-token meta and posts with X-CSRF-TOKEN + Accept: json
      - reuses the global toast() (window.flowAdminToast) for feedback
      - on success reloads the list so the row reflects the new state
        (coherent refetch over fragile DOM patching, per the admin AJAX rule)
    Endpoints return the uniform {success,message,data} JSON contract.
--}}
@once
@push('scripts')
<script>
(function () {
  const meta = document.querySelector('meta[name="csrf-token"]');
  const csrf = meta ? meta.getAttribute('content') : '';
  const notify = (title, body, kind) => {
    if (typeof window.flowAdminToast === 'function') {
      window.flowAdminToast(title, body || '', kind || '');
    }
  };

  function reset(el, label) {
    el.dataset.busy = '0';
    el.removeAttribute('disabled');
    el.textContent = label;
  }

  async function run(el) {
    const url = el.getAttribute('data-action-url');
    if (!url) return;

    const confirmMsg = el.getAttribute('data-confirm');
    if (confirmMsg && !window.confirm(confirmMsg)) return;

    if (el.dataset.busy === '1') return; // guard double-submit
    const label = el.textContent;
    el.dataset.busy = '1';
    el.setAttribute('disabled', 'disabled');
    el.textContent = el.getAttribute('data-busy-label') || 'Working…';

    try {
      const res = await fetch(url, {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
      });

      let data = {};
      try { data = await res.json(); } catch (_) { data = {}; }

      // Fail closed: only a real {success:true} envelope on a 2xx counts as
      // success. A 2xx with a non-envelope body (unparseable JSON, a proxy /
      // maintenance interstitial, a middleware that swallowed the controller)
      // must NOT be reported as done — otherwise a failed mutation would show
      // "Done." and reload, hiding it from the operator.
      if (res.ok && data && data.success === true) {
        notify(data.message || 'Done.', '', '');
        // Let the toast render, then reload so the list reflects the change.
        setTimeout(() => window.location.reload(), 650);
        return;
      }

      notify(data.message || 'The action could not be completed.', '', 'error');
      reset(el, label);
    } catch (_) {
      notify('Network error.', 'Please try again.', 'error');
      reset(el, label);
    }
  }

  document.querySelectorAll('[data-flow-action]').forEach((el) => {
    el.addEventListener('click', (e) => {
      e.preventDefault();
      run(el);
    });
  });
})();
</script>
@endpush
@endonce
