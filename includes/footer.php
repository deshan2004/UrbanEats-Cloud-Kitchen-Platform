<?php
/**
 * UrbanEats Cloud Kitchen Platform
 * Shared Global Footer (Component A — Requirement 3)
 * 
 * Reusable footer layout used across the entire application by all components.
 */

declare(strict_types=1);
?>
    </div><!-- /.container -->
</main><!-- /#main-content -->

<footer class="site-footer">
    <div class="container footer-content">
        <div style="display: flex; align-items: center; gap: 0.65rem;">
            <img src="<?= e(url('public/images/logo.png')); ?>" alt="UrbanEats" style="width: 24px; height: 24px; border-radius: 6px; object-fit: cover;">
            <span>&copy; <?= date('Y'); ?> <strong>UrbanEats Cloud Kitchen</strong>. All rights reserved.</span>
        </div>
        <div class="footer-meta">
            <span>IT3113 Advanced Web Technologies</span> &bull; 
            <span>Sri Lanka</span>
        </div>
    </div>
</footer>

<!-- Global UrbanEats Custom Confirmation Modal -->
<div id="ue-confirm-overlay" style="display:none; position:fixed; inset:0; background:rgba(25, 20, 16, 0.65); backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px); z-index:99999; align-items:center; justify-content:center; padding:1.25rem; opacity:0; transition:opacity 0.22s ease;">
    <div id="ue-confirm-card" style="background:#FFFFFF; border-radius:20px; max-width:420px; width:100%; box-shadow:0 25px 65px -12px rgba(25, 20, 16, 0.35); border:1px solid rgba(234, 223, 211, 0.9); overflow:hidden; transform:scale(0.92); transition:transform 0.22s cubic-bezier(0.16, 1, 0.3, 1); padding:2rem 1.75rem; text-align:center;">
        <div style="width:60px; height:60px; border-radius:50%; background:rgba(216, 68, 58, 0.1); color:#D8443A; display:inline-flex; align-items:center; justify-content:center; font-size:1.85rem; margin-bottom:1.15rem;">
            🗑️
        </div>
        <h3 id="ue-confirm-title" style="font-size:1.3rem; font-weight:800; color:var(--ink-900); margin:0 0 0.5rem 0;">Confirm Action</h3>
        <p id="ue-confirm-msg" style="font-size:0.95rem; color:var(--ink-600); line-height:1.55; margin:0 0 1.65rem 0;">Are you sure you want to proceed?</p>
        <div style="display:flex; gap:0.75rem; justify-content:center;">
            <button type="button" id="ue-confirm-cancel" class="btn btn-secondary" style="flex:1; padding:0.75rem 1rem; font-weight:700; border-radius:var(--radius-md);">Cancel</button>
            <button type="button" id="ue-confirm-ok" class="btn" style="flex:1; padding:0.75rem 1rem; font-weight:700; border-radius:var(--radius-md); background:#D8443A; border-color:#D8443A; color:#FFFFFF;">Yes, Remove</button>
        </div>
    </div>
</div>

<script>
(function() {
    let pendingCallback = null;

    function openModal(message, callback, title = 'Confirm Action', confirmBtnText = 'Yes, Confirm') {
        const overlay = document.getElementById('ue-confirm-overlay');
        const card = document.getElementById('ue-confirm-card');
        const titleEl = document.getElementById('ue-confirm-title');
        const msgEl = document.getElementById('ue-confirm-msg');
        const okBtn = document.getElementById('ue-confirm-ok');

        if (!overlay || !card) return;

        titleEl.textContent = title;
        msgEl.textContent = message || 'Are you sure you want to proceed?';
        okBtn.textContent = confirmBtnText;
        pendingCallback = callback;

        overlay.style.display = 'flex';
        requestAnimationFrame(() => {
            overlay.style.opacity = '1';
            card.style.transform = 'scale(1)';
        });
    }

    function closeModal() {
        const overlay = document.getElementById('ue-confirm-overlay');
        const card = document.getElementById('ue-confirm-card');
        if (!overlay || !card) return;

        overlay.style.opacity = '0';
        card.style.transform = 'scale(0.92)';
        setTimeout(() => {
            overlay.style.display = 'none';
            pendingCallback = null;
        }, 220);
    }

    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('ue-confirm-overlay');
        const cancelBtn = document.getElementById('ue-confirm-cancel');
        const okBtn = document.getElementById('ue-confirm-ok');

        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) closeModal();
            });
        }
        if (okBtn) {
            okBtn.addEventListener('click', function() {
                const cb = pendingCallback;
                closeModal();
                if (typeof cb === 'function') cb();
            });
        }

        // Delegate clicks on [data-confirm]
        document.body.addEventListener('click', function(e) {
            const el = e.target.closest('[data-confirm]');
            if (!el) return;

            e.preventDefault();
            e.stopPropagation();

            const msg = el.getAttribute('data-confirm');
            const href = el.getAttribute('href');
            const isDelete = msg.toLowerCase().includes('remove') || msg.toLowerCase().includes('delete') || msg.toLowerCase().includes('cancel') || msg.toLowerCase().includes('clear');

            openModal(
                msg, 
                function() {
                    if (href && href !== '#' && !href.startsWith('javascript:')) {
                        window.location.href = href;
                    } else if (el.tagName.toLowerCase() === 'button' && el.form) {
                        el.form.submit();
                    }
                }, 
                isDelete ? 'Confirm Removal' : 'Please Confirm',
                isDelete ? 'Yes, Proceed' : 'Yes, Confirm'
            );
        });

        // Delegate form submits on [data-confirm-form]
        document.body.addEventListener('submit', function(e) {
            const form = e.target.closest('form[data-confirm-form]');
            if (!form || form.dataset.confirmed === 'true') return;

            e.preventDefault();
            const msg = form.getAttribute('data-confirm-form');
            openModal(msg, function() {
                form.dataset.confirmed = 'true';
                form.submit();
            }, 'Confirm Action', 'Yes, Continue');
        });
    });

    window.openUrbanConfirm = openModal;
})();
</script>

<?php if (!empty($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'kitchen_staff'], true)): ?>
<!-- Real-time Live Order Alerting Engine for Admin & Kitchen Operations (Embedded PHP script) -->
<script>
(function () {
    'use strict';

    const liveOrdersUrl = <?= json_encode(url('api/live_orders.php')); ?>;
    const kitchenQueueUrl = <?= json_encode(url('orders/kitchen_queue.php')); ?>;
    const logoUrl = <?= json_encode(url('public/images/logo.png')); ?>;

    let lastKnownOrderId = 0;
    let isInitialized = false;
    let isSoundEnabled = localStorage.getItem('ue_sound_enabled') !== 'false';
    let pollInterval = null;
    let audioContext = null;

    // 1. Web Audio API Chime Synthesizer
    function getAudioContext() {
        if (!audioContext) {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) audioContext = new AudioCtx();
        }
        if (audioContext && audioContext.state === 'suspended') {
            audioContext.resume();
        }
        return audioContext;
    }

    function playCulinaryChime() {
        if (!isSoundEnabled) return;
        try {
            const ctx = getAudioContext();
            if (!ctx) return;
            const now = ctx.currentTime;

            // Tone 1: 587.33 Hz (D5)
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now);
            gain1.gain.setValueAtTime(0, now);
            gain1.gain.linearRampToValueAtTime(0.25, now + 0.04);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.46);

            // Tone 2: 880.00 Hz (A5)
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'triangle';
            osc2.frequency.setValueAtTime(880.00, now + 0.12);
            gain2.gain.setValueAtTime(0, now + 0.12);
            gain2.gain.linearRampToValueAtTime(0.28, now + 0.16);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.75);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.12);
            osc2.stop(now + 0.76);
        } catch (e) {
            console.warn('[UrbanEats Audio] Chime note:', e);
        }
    }

    // 2. Toast Container
    function getToastContainer() {
        let container = document.getElementById('ue-notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'ue-notification-container';
            container.setAttribute('aria-live', 'polite');
            container.style.cssText = 'position:fixed;top:24px;right:24px;z-index:999999;display:flex;flex-direction:column;gap:12px;max-width:400px;width:calc(100vw - 48px);pointer-events:none;';
            document.body.appendChild(container);
        }
        return container;
    }

    // 3. Floating Glassmorphic Toast Card
    function showOrderToast(order) {
        const container = getToastContainer();
        const toast = document.createElement('div');
        toast.className = 'ue-live-order-toast';
        toast.style.cssText = 'pointer-events:auto;background:rgba(255,255,255,0.96);backdrop-filter:blur(20px) saturate(180%);-webkit-backdrop-filter:blur(20px) saturate(180%);border:1px solid rgba(242,107,29,0.45);border-left:6px solid #F26B1D;border-radius:16px;box-shadow:0 20px 45px -10px rgba(242,107,29,0.28),0 4px 12px rgba(0,0,0,0.08);padding:1.15rem 1.25rem;color:#1F2937;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;transform:translateX(120%);opacity:0;transition:all 0.35s cubic-bezier(0.16,1,0.3,1);';

        const isDelivery = order.order_type === 'delivery';
        const typeBadge = isDelivery ? '🛵 Delivery' : '🏪 Kitchen Pickup';

        toast.innerHTML = `
            <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:0.5rem;">
                <div style="display:flex;align-items:center;gap:0.5rem;">
                    <span style="display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:#FEF3C7;color:#D97706;font-size:0.9rem;animation:ue-bell-ring 1s ease infinite;">🔔</span>
                    <strong style="font-size:0.92rem;color:#111827;">New Order Received!</strong>
                </div>
                <div style="display:flex;align-items:center;gap:0.4rem;">
                    <span style="font-size:0.72rem;color:#6B7280;">${order.time_ago || 'Just now'}</span>
                    <button type="button" class="ue-toast-close" title="Dismiss" style="background:none;border:none;font-size:1.1rem;line-height:1;cursor:pointer;color:#9CA3AF;padding:0 4px;">&times;</button>
                </div>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:0.35rem;">
                <span style="font-family:'Courier New',Courier,monospace;font-weight:800;font-size:1.05rem;color:#F26B1D;">#${order.order_code}</span>
                <span style="font-size:0.78rem;font-weight:700;background:${isDelivery ? '#EFF6FF' : '#ECFDF5'};color:${isDelivery ? '#1D4ED8' : '#047857'};padding:0.15rem 0.55rem;border-radius:9999px;">${typeBadge}</span>
            </div>
            <div style="font-size:0.88rem;font-weight:600;color:#374151;margin-bottom:0.25rem;">
                ${order.customer_name} &bull; <span style="color:#111827;font-weight:800;">${order.total_formatted}</span>
            </div>
            <div style="font-size:0.8rem;color:#6B7280;line-height:1.4;margin-bottom:0.85rem;max-height:38px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                🍽️ ${order.items_summary}
            </div>
            <div style="display:flex;gap:0.5rem;justify-content:flex-end;">
                <a href="${kitchenQueueUrl}" style="background:#F26B1D;color:#FFFFFF;text-decoration:none;font-size:0.82rem;font-weight:700;padding:0.4rem 0.85rem;border-radius:8px;box-shadow:0 2px 6px rgba(242,107,29,0.3);display:inline-flex;align-items:center;gap:0.3rem;">
                    View in Kitchen Queue &rarr;
                </a>
            </div>
        `;

        container.appendChild(toast);
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        });

        function dismissToast() {
            toast.style.transform = 'translateX(120%)';
            toast.style.opacity = '0';
            setTimeout(() => { if (toast.parentNode) toast.parentNode.removeChild(toast); }, 350);
        }

        const closeBtn = toast.querySelector('.ue-toast-close');
        if (closeBtn) closeBtn.addEventListener('click', dismissToast);
        setTimeout(dismissToast, 9000);

        // Desktop Notification
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const notif = new Notification(`🔔 New Order #${order.order_code}`, {
                    body: `${order.customer_name} (${order.total_formatted})\n${order.items_summary}`,
                    icon: logoUrl,
                    tag: `order-${order.order_id}`,
                });
                notif.onclick = function () {
                    window.focus();
                    window.location.href = kitchenQueueUrl;
                };
            } catch (err) {
                console.warn('Desktop Notification error:', err);
            }
        }
    }

    // 4. Live Polling Loop
    async function pollLiveOrders() {
        try {
            const url = isInitialized ? `${liveOrdersUrl}?since_id=${lastKnownOrderId}` : liveOrdersUrl;
            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) return;

            const data = await res.json();
            if (!data || !data.success) return;

            if (!isInitialized) {
                lastKnownOrderId = data.max_order_id || 0;
                isInitialized = true;
                updateLiveStatusPill(data.metrics);
                return;
            }

            if (Array.isArray(data.new_orders) && data.new_orders.length > 0) {
                data.new_orders.forEach((order) => {
                    playCulinaryChime();
                    showOrderToast(order);
                });

                lastKnownOrderId = data.max_order_id;

                if (window.location.pathname.includes('kitchen_queue.php')) {
                    showQueueAutoRefreshBanner(data.new_orders.length);
                }
            }

            updateLiveStatusPill(data.metrics);
        } catch (e) {
            console.warn('[UrbanEats Polling Note]', e);
        }
    }

    function updateLiveStatusPill(metrics) {
        if (!metrics) return;
        document.querySelectorAll('.ue-live-active-orders').forEach(b => {
            b.textContent = metrics.active_count || 0;
        });
        document.querySelectorAll('.ue-live-placed-count').forEach(b => {
            b.textContent = metrics.placed_count || 0;
        });
    }

    function showQueueAutoRefreshBanner(newCount) {
        let banner = document.getElementById('ue-queue-refresh-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'ue-queue-refresh-banner';
            banner.style.cssText = 'position:fixed;bottom:24px;left:50%;transform:translateX(-50%);background:rgba(25,20,16,0.92);color:#FFFFFF;backdrop-filter:blur(12px);border:1px solid rgba(242,107,29,0.5);border-radius:9999px;padding:0.65rem 1.4rem;font-size:0.88rem;font-weight:700;display:flex;align-items:center;gap:0.75rem;box-shadow:0 10px 30px rgba(0,0,0,0.35);z-index:99999;cursor:pointer;';
            banner.onclick = () => window.location.reload();
            document.body.appendChild(banner);
        }
        banner.innerHTML = `<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#10B981;animation:ue-pulse-dot 1s infinite;"></span><span>${newCount} New order ticket(s) received! Click to refresh queue &rarr;</span>`;
        setTimeout(() => { window.location.reload(); }, 4000);
    }

    // 5. Floating Control Toolbar
    function setupFloatingToolbar() {
        const bar = document.createElement('div');
        bar.id = 'ue-live-toolbar';
        bar.className = 'no-print';
        bar.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99998;display:flex;align-items:center;gap:8px;background:rgba(255,255,255,0.92);backdrop-filter:blur(12px);border:1px solid rgba(234,223,211,0.95);border-radius:9999px;padding:5px 12px;box-shadow:0 6px 20px rgba(0,0,0,0.08);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;font-size:0.78rem;color:#374151;';

        const soundIcon = isSoundEnabled ? '🔔' : '🔕';
        const soundText = isSoundEnabled ? 'Sound On' : 'Muted';

        bar.innerHTML = `
            <span style="display:inline-flex;align-items:center;gap:4px;font-weight:700;color:#047857;">
                <span style="width:8px;height:8px;border-radius:50%;background:#10B981;animation:ue-pulse-dot 1.5s infinite;"></span>
                Live Sync
            </span>
            <span style="color:#D1D5DB;">|</span>
            <button type="button" id="ue-sound-toggle-btn" style="background:none;border:none;cursor:pointer;display:flex;align-items:center;gap:3px;font-size:0.78rem;font-weight:600;color:#4B5563;padding:2px 4px;">
                <span>${soundIcon}</span>
                <span id="ue-sound-label">${soundText}</span>
            </button>
        `;

        document.body.appendChild(bar);

        const soundBtn = document.getElementById('ue-sound-toggle-btn');
        if (soundBtn) {
            soundBtn.addEventListener('click', function () {
                isSoundEnabled = !isSoundEnabled;
                localStorage.setItem('ue_sound_enabled', isSoundEnabled ? 'true' : 'false');
                const iconSpan = soundBtn.querySelector('span:first-child');
                const labelSpan = document.getElementById('ue-sound-label');
                if (iconSpan) iconSpan.textContent = isSoundEnabled ? '🔔' : '🔕';
                if (labelSpan) labelSpan.textContent = isSoundEnabled ? 'Sound On' : 'Muted';
                if (isSoundEnabled) playCulinaryChime();
            });
        }

        if ('Notification' in window && Notification.permission === 'default') {
            document.addEventListener('click', function reqOnce() {
                Notification.requestPermission();
                document.removeEventListener('click', reqOnce);
            }, { once: true });
        }
    }

    // 6. CSS Keyframe Animations
    const styleElem = document.createElement('style');
    styleElem.textContent = `
        @keyframes ue-bell-ring {
            0%, 100% { transform: rotate(0); }
            20% { transform: rotate(15deg); }
            40% { transform: rotate(-15deg); }
            60% { transform: rotate(10deg); }
            80% { transform: rotate(-10deg); }
        }
        @keyframes ue-pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.35; transform: scale(0.85); }
        }
    `;
    document.head.appendChild(styleElem);

    document.addEventListener('DOMContentLoaded', function () {
        setupFloatingToolbar();
        pollLiveOrders();
        pollInterval = setInterval(pollLiveOrders, 5000);
    });

    window.triggerUrbanOrderChime = playCulinaryChime;
    window.showUrbanOrderToast = showOrderToast;
})();
</script>
<?php endif; ?>

</body>
</html>
