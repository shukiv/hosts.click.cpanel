(() => {
  const previewPane = document.getElementById('preview-pane');
  if (!previewPane) return;

  const endpoint = previewPane.getAttribute('data-create-endpoint') || (() => {
    const parts = window.location.pathname.split('/').filter(Boolean);
    if (parts.length && parts[0].startsWith('cpsess')) {
      return `/${parts[0]}/cgi/hosts-click/create-whm.php`;
    }
    return '/cgi/hosts-click/create-whm.php';
  })();
  const alerts = document.getElementById('hc-alerts');
  const previewResult = document.getElementById('hc-preview-result');

  const renderAlert = (type, message) => {
    if (!alerts) return;
    alerts.innerHTML = '';
    if (!message) return;
    const div = document.createElement('div');
    div.className = `alert alert-${type} py-2 mb-3`;
    div.textContent = message;
    alerts.appendChild(div);
  };

  const renderPreview = (url) => {
    if (!previewResult) return;
    previewResult.innerHTML = '';
    if (!url) return;
    const wrap = document.createElement('div');
    wrap.className = 'alert alert-info mt-3 mb-0';
    const strong = document.createElement('strong');
    strong.textContent = 'Preview URL:';
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = url;
    wrap.appendChild(strong);
    wrap.appendChild(document.createTextNode(' '));
    wrap.appendChild(link);
    previewResult.appendChild(wrap);
  };

  const prependLinkRow = (domain, ip, url, createdAt, expiresAt) => {
    const table = document.getElementById('hc-links-table');
    if (!table) return;
    const tbody = table.querySelector('tbody');
    if (!tbody) return;
    const emptyRow = tbody.querySelector('.hc-links-empty');
    if (emptyRow) emptyRow.remove();

    const tr = document.createElement('tr');
    const cells = [
      domain || '',
      ip || '',
      url ? `<a href="${url}" target="_blank" rel="noopener noreferrer">${url}</a>` : '',
      createdAt || '',
      expiresAt || ''
    ];
    tr.innerHTML = cells.map((cell) => `<td>${cell}</td>`).join('');
    tbody.prepend(tr);
  };

  previewPane.querySelectorAll('form[id^="form-"]').forEach((form) => {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      renderAlert('', '');
      renderPreview('');

      const formData = new FormData(form);
      const ipInput = previewPane.querySelector(`input[form="${form.id}"]`);
      if (ipInput) {
        formData.set('ip', ipInput.value || '');
      }

      try {
        const response = await fetch(endpoint, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });
        const data = await response.json();
        if (!response.ok) {
          renderAlert('danger', data.error || 'Failed to create link.');
          return;
        }
        renderAlert('success', 'Preview link created.');
        renderPreview(data.preview_url || '');
        const domain = formData.get('domain') || '';
        const ip = formData.get('ip') || '';
        const createdAt = new Date().toLocaleString();
        const expiresAt = data.expires_at || '';
        prependLinkRow(domain, ip, data.preview_url || '', createdAt, expiresAt);
      } catch (err) {
        renderAlert('danger', 'Request failed.');
      }
    });
  });
})();
