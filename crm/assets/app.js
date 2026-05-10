// HalaOps CRM — minimal client behavior
(function() {
  // Auto-dismiss flash messages after 5s
  document.querySelectorAll('main > div.mb-4').forEach(el => {
    if (el.textContent.trim()) {
      setTimeout(() => {
        el.style.transition = 'opacity .3s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 300);
      }, 5000);
    }
  });

  // Confirm on dangerous actions handled inline; keep here for extension.
})();
