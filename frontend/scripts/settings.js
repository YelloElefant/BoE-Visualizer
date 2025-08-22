(() => {
  const KEY = 'boe.showGrid';
  const showGridEl = document.getElementById('show-grid');

  // Restore saved value (default = true if nothing saved)
  const saved = localStorage.getItem(KEY);
  showGridEl.checked = saved === null ? true : saved === 'true';

  // Save
  document.querySelector('.save-button')?.addEventListener('click', () => {
    localStorage.setItem(KEY, String(showGridEl.checked));
    alert('Settings saved');
  });

  // Reset
  document.querySelector('.reset-button')?.addEventListener('click', () => {
    localStorage.removeItem(KEY);
    showGridEl.checked = true;
    alert('Settings reset');
  });
})();