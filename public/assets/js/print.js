// print.js - dispatch-sheet printing: toolbar button + optional auto-print (?autoprint).
document.querySelector('[data-print-trigger]')?.addEventListener('click', () => window.print());
if (new URLSearchParams(location.search).has('autoprint')) {
  window.addEventListener('load', () => window.print());
}
