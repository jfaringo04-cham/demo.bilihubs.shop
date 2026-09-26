window.markAsRead = function (notificationId, element) {
  const template = document.querySelector('meta[name="rider-notification-read-url"]')?.getAttribute('content');
  if (!template) return;

  fetch(template.replace('__id__', encodeURIComponent(notificationId)), {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      'Content-Type': 'application/json',
    },
  }).then((response) => {
    if (!response.ok) return;

    element?.classList.remove('fw-bold');
    element?.querySelector('.text-primary')?.remove();

    const badge = document.querySelector('.notification-badge');
    if (!badge) return;

    const currentCount = parseInt(badge.textContent || '0', 10);
    if (currentCount > 1) {
      badge.textContent = currentCount - 1;
    } else {
      badge.remove();
    }
  }).catch(() => {});
};
