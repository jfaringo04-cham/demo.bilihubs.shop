document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('sidebarToggle');
  const sidebar = document.getElementById('logisticSidebar');
  if (!toggle || !sidebar) return;

  const overlay = document.createElement('div');
  overlay.className = 'logistic-sidebar-overlay';
  overlay.id = 'sidebarOverlay';
  document.body.appendChild(overlay);

  const closeSidebar = function () {
    sidebar.classList.remove('active');
    document.body.classList.remove('sidebar-open');
  };

  toggle.addEventListener('click', function () {
    sidebar.classList.toggle('active');
    document.body.classList.toggle('sidebar-open');
  });

  document.addEventListener('click', function (event) {
    if (window.innerWidth < 992 && !sidebar.contains(event.target) && !event.target.closest('#sidebarToggle')) {
      closeSidebar();
    }
  });

  overlay.addEventListener('click', closeSidebar);

  const observer = new MutationObserver(function () {
    overlay.classList.toggle('active', sidebar.classList.contains('active'));
  });
  observer.observe(sidebar, {
    attributes: true,
    attributeFilter: ['class'],
  });
});
