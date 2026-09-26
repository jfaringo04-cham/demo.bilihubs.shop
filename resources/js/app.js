document.addEventListener('click', function (event) {
  const notificationLink = event.target.closest('[data-notification-id]');
  const template = document.querySelector('meta[name="notification-read-url"]')?.getAttribute('content');
  if (!notificationLink || !template) return;

  const notificationId = notificationLink.getAttribute('data-notification-id');
  if (!notificationId) return;

  fetch(template.replace('__id__', encodeURIComponent(notificationId)), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
    },
  }).then((response) => {
    if (!response.ok) return;

    const badge = document.querySelector('#notificationsDropdown .badge');
    if (badge) {
      const currentCount = parseInt(badge.textContent, 10);
      if (currentCount > 1) {
        badge.textContent = currentCount - 1;
      } else {
        badge.remove();
      }
    }

    notificationLink.querySelector('.badge.bg-violet-500')?.remove();
    notificationLink.classList.remove('bg-violet-50');
  }).catch(() => {});
});
