// ==========================================
// ORTAK BİLDİRİM YÖNETİM SİSTEMİ
// ==========================================

function renderNotifications() {
    const notificationList = document.getElementById('notificationList');
    const badge = document.getElementById('notificationBadge');
    const countBadge = document.getElementById('notificationCountBadge');
    const emptyMsg = document.getElementById('emptyNotification');
    const clearAllBtn = document.getElementById('clearAllBtn');

    if (!notificationList) return;

    let notifications = JSON.parse(localStorage.getItem('adminNotifications')) || [];

    const existingItems = notificationList.querySelectorAll('.notification-item');
    existingItems.forEach(item => item.remove());

    if (notifications.length > 0) {
        if (badge) badge.style.display = 'block';
        if (countBadge) {
            countBadge.style.display = 'inline-block';
            countBadge.textContent = notifications.length + ' New';
        }
        if (emptyMsg) emptyMsg.style.display = 'none';
        if (clearAllBtn) clearAllBtn.style.display = 'inline-block';

        notifications.forEach((notif, index) => {
            const li = document.createElement('li');
            li.className = 'notification-item';

            let titleText = notif.title || 'Notification';
            let bodyText = notif.text || '';

            if (!notif.title && notif.text && notif.text.includes(':')) {
                const parts = notif.text.split(':');
                titleText = parts[0].trim();
                bodyText = parts[1].trim();
            }

            let formattedTime = notif.time ? notif.time.replace(/,([^,]*)$/, '$1') : '';

            li.innerHTML = `
                <div class="dropdown-item py-2 px-3 border-bottom position-relative">
                    <div class="d-flex align-items-start justify-content-between">
                        <div class="d-flex align-items-start me-2">
                            <div class="bg-light text-primary rounded-circle p-2 me-2 d-flex align-items-center justify-content-center flex-shrink-0" style="width: 32px; height: 32px;">
                                <i class="bi ${notif.icon || 'bi-info-circle'} fs-6"></i>
                            </div>
                            <div>
                                <span class="fw-bold d-block text-dark lh-sm" style="font-size: 12px;">${titleText}</span>
                                ${bodyText ? `<span class="text-secondary d-block mt-1" style="font-size: 11px; font-weight: 500; word-break: break-word;">${bodyText}</span>` : ''}
                            </div>
                        </div>
                        <button type="button" class="btn btn-link text-muted p-0 border-0 delete-notif-btn" title="Delete" style="font-size: 13px; text-decoration: none;">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="text-end mt-1">
                        <span class="text-muted" style="font-size: 10px; font-weight: 400;">${formattedTime}</span>
                    </div>
                </div>
            `;

            li.addEventListener('click', function(e) {
                e.stopPropagation();
            });

            const deleteBtn = li.querySelector('.delete-notif-btn');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    let currentNotifs = JSON.parse(localStorage.getItem('adminNotifications')) || [];
                    currentNotifs.splice(index, 1);
                    localStorage.setItem('adminNotifications', JSON.stringify(currentNotifs));

                    renderNotifications();
                });
            }

            notificationList.appendChild(li);
        });
    } else {
        if (badge) badge.style.display = 'none';
        if (countBadge) countBadge.style.display = 'none';
        if (emptyMsg) emptyMsg.style.display = 'block';
        if (clearAllBtn) clearAllBtn.style.display = 'none';
    }
}

// Global Event Listeners
document.addEventListener('click', function(e) {
    const clearBtn = e.target.closest('#clearAllBtn');
    if (clearBtn) {
        e.preventDefault();
        e.stopPropagation();

        localStorage.setItem('adminNotifications', JSON.stringify([]));
        renderNotifications();
    }
});

document.addEventListener('DOMContentLoaded', function() {
    renderNotifications();
});

function addNotification(title, text, iconClass) {
    let notifications = JSON.parse(localStorage.getItem('adminNotifications')) || [];

    const now = new Date();
    const formattedDate = now.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
    const formattedTime = now.toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
    const timeStamp = `${formattedDate} ${formattedTime}`;

    notifications.unshift({
        title: title,
        text: text || '',
        icon: iconClass || 'bi-ticket-detailed',
        time: timeStamp
    });

    localStorage.setItem('adminNotifications', JSON.stringify(notifications));
    renderNotifications();
}