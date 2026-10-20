document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-modal]').forEach(btn => btn.addEventListener('click', () => {
    const modal = document.getElementById(btn.dataset.modal); if (modal) modal.classList.add('show');
  }));
  document.querySelectorAll('.modal .close').forEach(btn => btn.addEventListener('click', () => btn.closest('.modal').classList.remove('show')));
  document.querySelectorAll('.modal').forEach(m => m.addEventListener('click', e => { if (e.target === m) m.classList.remove('show'); }));
  document.querySelectorAll('[data-tabs] button').forEach(btn => btn.addEventListener('click', () => {
    document.querySelectorAll('[data-tabs] button').forEach(x => x.classList.remove('active'));
    document.querySelectorAll('.tab-panel').forEach(x => x.classList.remove('active'));
    btn.classList.add('active'); const panel=document.getElementById('tab-'+btn.dataset.tab); if(panel) panel.classList.add('active');
  }));
  const params = new URLSearchParams(location.search); const tab=params.get('tab');
  if(tab){ const b=document.querySelector(`[data-tab="${tab}"]`); if(b) b.click(); }
});
