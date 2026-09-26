document.addEventListener('DOMContentLoaded', function () {
  const toggle = document.getElementById('mobileSidebarToggle');
  const sidebar = document.getElementById('sellerSidebar');
  const overlay = document.querySelector('.seller-sidebar-overlay');

  toggle?.addEventListener('click', function () {
    sidebar?.classList.add('show');
    if (overlay) overlay.style.display = 'block';
  });

  overlay?.addEventListener('click', function () {
    sidebar?.classList.remove('show');
    this.style.display = 'none';
  });
});
