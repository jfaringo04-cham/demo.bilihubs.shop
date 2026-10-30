window.markAsRead = function (notificationId, element, event) {
  if (event) {
    event.preventDefault();
  }

  const template = document
    .querySelector('meta[name="rider-notification-read-url"]')
    ?.getAttribute('content');

  const targetUrl = element?.getAttribute('href') || '#';

  if (!template) {
    if (targetUrl && targetUrl !== '#') {
      window.location.href = targetUrl;
    }
    return false;
  }

  fetch(template.replace('__id__', encodeURIComponent(notificationId)), {
    method: 'POST',
    headers: {
      'X-CSRF-TOKEN':
        document
          .querySelector('meta[name="csrf-token"]')
          ?.getAttribute('content') || '',
      Accept: 'application/json',
      'Content-Type': 'application/json',
    },
  })
    .then((response) => {
      if (!response.ok) {
        throw new Error('Unable to mark notification as read.');
      }

      element?.classList.remove('fw-bold');
      element?.querySelector('.text-primary')?.remove();

      const badge = document.querySelector('.notification-badge');
      if (!badge) return;

      const currentCount = parseInt(badge.textContent || '0', 10);
      if (currentCount > 1) {
        badge.textContent = String(currentCount - 1);
      } else {
        badge.remove();
      }
    })
    .catch(() => {
      // Do not block navigation when the notification read request fails.
    })
    .finally(() => {
      if (targetUrl && targetUrl !== '#') {
        window.location.href = targetUrl;
      }
    });

  return false;
};