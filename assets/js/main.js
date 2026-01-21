// Phát hiện drag: lưu nơi mousedown
let dragStart = null;
document.addEventListener('mousedown', (e) => {
    dragStart = e.target;
});

// Đóng modal chỉ khi click trực tiếp vào modal background (không phải drag)
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal')) {
        // Chỉ đóng nếu mousedown cũng xảy ra trên modal (không phải drag từ bên trong)
        if (dragStart === e.target) {
            e.target.classList.remove('active');
        }
        dragStart = null;
    }
});

// Xử lý nút X đóng modal
document.addEventListener('click', (e) => {
    if (e.target.classList.contains('modal-close')) {
        const modal = e.target.closest('.modal');
        if (modal) {
            modal.classList.remove('active');
        }
    }
    dragStart = null;
});

// Force light theme only
document.documentElement.setAttribute('data-theme', 'light');

// Initialize header theme toggle (if present) — update button text and wire clicks
// REMOVED: Theme toggle functionality - forcing light theme only

// Format tiền tệ
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND'
    }).format(amount);
}

// Thông báo (toast) - smooth and accessible
function showNotification(message, type = 'success', duration = 3200) {
    const prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = 'toast toast-' + (type === 'error' ? 'error' : 'success');
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    toast.innerHTML = `<div class="toast-body">${message}</div><button class="toast-close" aria-label="Close">&times;</button>`;

    container.appendChild(toast);

    // Small delay to trigger CSS transition
    if (!prefersReduced) requestAnimationFrame(() => requestAnimationFrame(() => toast.classList.add('visible')));
    else toast.classList.add('visible');

    const remove = () => {
        toast.classList.remove('visible');
        toast.classList.add('closing');
        setTimeout(() => { try { toast.remove(); } catch(e){} }, prefersReduced ? 0 : 260);
    };

    toast.querySelector('.toast-close').addEventListener('click', remove);
    setTimeout(remove, duration);
}

// Sidebar toggle (mobile)
const sidebarToggleBtn = document.getElementById('sidebarToggle');
const sidebar = document.getElementById('mainSidebar');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');

sidebarToggleBtn?.addEventListener('click', function() {
    document.body.classList.toggle('sidebar-open');
    sidebar?.classList.toggle('open');
    sidebarBackdrop?.classList.toggle('visible');
});

// Backdrop click closes sidebar
sidebarBackdrop?.addEventListener('click', function() {
    document.body.classList.remove('sidebar-open');
    sidebar?.classList.remove('open');
    sidebarBackdrop?.classList.remove('visible');
});

// Close sidebar when clicking outside on mobile (safety fallback)
document.addEventListener('click', (e) => {
    const toggle = document.getElementById('sidebarToggle');
    if (!sidebar || !toggle) return;
    if (document.body.classList.contains('sidebar-open')) {
        if (!sidebar.contains(e.target) && !toggle.contains(e.target)) {
            document.body.classList.remove('sidebar-open');
            sidebar.classList.remove('open');
            sidebarBackdrop?.classList.remove('visible');
        }
    }
});

// Close sidebar with Escape key
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        if (document.body.classList.contains('sidebar-open')) {
            document.body.classList.remove('sidebar-open');
            sidebar?.classList.remove('open');
            sidebarBackdrop?.classList.remove('visible');
        }
    }
});

// Auto-mark current sidebar link as active based on current path
document.addEventListener('DOMContentLoaded', () => {
    try {
        const path = window.location.pathname.replace(/\/ShopToolNro/, ''); // normalize if app served from subfolder
        document.querySelectorAll('aside.sidebar nav a').forEach(a => {
            const href = a.getAttribute('href') || '';
            // Build absolute pathname for comparison
            const normalizedHref = href.replace(/^\/ShopToolNro/, '');
            if (normalizedHref === path || (normalizedHref !== '/' && path.startsWith(normalizedHref))) {
                a.classList.add('active');
            }
        });
    } catch (e) { /* no-op */ }

    // Fetch site-wide notification and show banner or popup (if active and not dismissed)
    try {
        fetch('/ShopToolNro/api/public/notification.php').then(r => r.json()).then(res => {
            const noti = res?.notification ?? null;
            if (!noti || !noti.message) return;
            const id = noti.id || null;
            const dismissed = id ? localStorage.getItem('hide_notification_' + id) : null;
            if (dismissed) return; // user dismissed this message

            const style = (noti.display_style || 'banner');

            if (style === 'popup') {
                const overlay = document.createElement('div');
                overlay.className = 'promo-overlay';

                const popup = document.createElement('div');
                popup.className = 'promo-popup';
                popup.innerHTML = `
                    <button class="promo-close" aria-label="Close">&times;</button>
                    <div class="promo-body">
                        <div class="promo-msg">${noti.message}</div>
                        ${noti.cta_label ? ('<a class="promo-cta" href="' + (noti.cta_url || '#') + '">' + noti.cta_label + '</a>') : ''}
                    </div>
                `;

                overlay.appendChild(popup);
                document.body.appendChild(overlay);

                // show
                requestAnimationFrame(() => overlay.classList.add('visible'));
                requestAnimationFrame(() => popup.classList.add('visible'));

                function closePromo() {
                    overlay.classList.remove('visible');
                    setTimeout(() => overlay.remove(), 300);
                    if (id) localStorage.setItem('hide_notification_' + id, '1');
                }

                popup.querySelector('.promo-close').addEventListener('click', closePromo);
                overlay.addEventListener('click', (e) => { if (e.target === overlay) closePromo(); });
                document.addEventListener('keydown', function escHandler(e) { if (e.key === 'Escape') { closePromo(); document.removeEventListener('keydown', escHandler); } });

                // Auto-hide for non-urgent
                if ((noti.priority || 'normal') !== 'urgent') {
                    setTimeout(() => closePromo(), 9000);
                }

            } else {
                const banner = document.createElement('div');
                banner.className = 'site-banner ' + (noti.priority || 'normal');
                banner.innerHTML = `<div class="banner-inner"><div class="banner-content">${noti.message}</div><div><button class="banner-close" aria-label="Close">&times;</button></div></div>`;
                document.body.appendChild(banner);

                requestAnimationFrame(() => requestAnimationFrame(() => banner.classList.add('visible')));

                const closeBtn = banner.querySelector('.banner-close');
                closeBtn.addEventListener('click', () => {
                    banner.classList.remove('visible');
                    setTimeout(() => banner.remove(), 320);
                    if (id) localStorage.setItem('hide_notification_' + id, '1');
                });

                if ((noti.priority || 'normal') !== 'urgent') {
                    setTimeout(() => { banner.classList.remove('visible'); setTimeout(() => banner.remove(), 320); }, 7000);
                }
            }
        }).catch(() => {});
    } catch (e) { /* no-op */ }
});
