/**
 * UrbanEats Cloud Kitchen Platform
 * Real-time Order Alerting & Notification Engine (Admin & Kitchen Staff)
 * 
 * Features:
 * - Native Web Audio API dual-tone culinary chime synthesizer (No MP3 dependencies)
 * - Glassmorphic floating toast notification with micro-animations
 * - HTML5 Desktop Push Notification integration
 * - Sound mute/unmute persistence via localStorage
 * - Seamless background polling with automatic queue board refresh
 */

(function () {
    'use strict';

    // State management
    let lastKnownOrderId = 0;
    let isInitialized = false;
    let isSoundEnabled = localStorage.getItem('ue_sound_enabled') !== 'false'; // default true
    let pollInterval = null;
    let audioContext = null;

    // 1. Web Audio API Dual-Tone Chime Synthesizer
    function getAudioContext() {
        if (!audioContext) {
            const AudioCtx = window.AudioContext || window.webkitAudioContext;
            if (AudioCtx) {
                audioContext = new AudioCtx();
            }
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

            // Tone 1: D5 (587.33 Hz) - Crisp kitchen order alert
            const osc1 = ctx.createOscillator();
            const gain1 = ctx.createGain();
            osc1.type = 'sine';
            osc1.frequency.setValueAtTime(587.33, now);
            gain1.gain.setValueAtTime(0, now);
            gain1.gain.linearRampToValueAtTime(0.22, now + 0.04);
            gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
            osc1.connect(gain1);
            gain1.connect(ctx.destination);
            osc1.start(now);
            osc1.stop(now + 0.46);

            // Tone 2: A5 (880.00 Hz) - Harmonic resolution
            const osc2 = ctx.createOscillator();
            const gain2 = ctx.createGain();
            osc2.type = 'triangle';
            osc2.frequency.setValueAtTime(880.00, now + 0.12);
            gain2.gain.setValueAtTime(0, now + 0.12);
            gain2.gain.linearRampToValueAtTime(0.25, now + 0.16);
            gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.75);
            osc2.connect(gain2);
            gain2.connect(ctx.destination);
            osc2.start(now + 0.12);
            osc2.stop(now + 0.76);

        } catch (e) {
            console.warn('[UrbanEats Audio] Chime playback note:', e);
        }
    }

    // 2. Ensure Toast Container Exists
    function getToastContainer() {
        let container = document.getElementById('ue-notification-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'ue-notification-container';
            container.setAttribute('aria-live', 'polite');
            container.style.cssText = `
                position: fixed;
                top: 24px;
                right: 24px;
                z-index: 999999;
                display: flex;
                flex-direction: column;
                gap: 12px;
                max-width: 400px;
                width: calc(100vw - 48px);
                pointer-events: none;
            `;
            document.body.appendChild(container);
        }
        return container;
    }

    // 3. Render Glassmorphic Toast Notification Card
    function showOrderToast(order) {
        const container = getToastContainer();

        const toast = document.createElement('div');
        toast.className = 'ue-live-order-toast';
        toast.style.cssText = `
            pointer-events: auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px) saturate(180%);
            -webkit-backdrop-filter: blur(20px) saturate(180%);
            border: 1px solid rgba(242, 107, 29, 0.45);
            border-left: 6px solid #F26B1D;
            border-radius: 16px;
            box-shadow: 0 20px 45px -10px rgba(242, 107, 29, 0.28), 0 4px 12px rgba(0, 0, 0, 0.08);
            padding: 1.15rem 1.25rem;
            color: #1F2937;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            transform: translateX(120%);
            opacity: 0;
            transition: all 0.35s cubic-bezier(0.16, 1, 0.3, 1);
        `;

        const isDelivery = order.order_type === 'delivery';
        const typeBadge = isDelivery ? '🛵 Delivery' : '🏪 Kitchen Pickup';

        toast.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #FEF3C7; color: #D97706; font-size: 0.9rem; animation: ue-bell-ring 1s ease infinite;">
                        🔔
                    </span>
                    <strong style="font-size: 0.92rem; color: #111827;">New Order Received!</strong>
                </div>
                <div style="display: flex; align-items: center; gap: 0.4rem;">
                    <span style="font-size: 0.72rem; color: #6B7280;">${order.time_ago || 'Just now'}</span>
                    <button type="button" class="ue-toast-close" title="Dismiss" style="background: none; border: none; font-size: 1.1rem; line-height: 1; cursor: pointer; color: #9CA3AF; padding: 0 4px;">&times;</button>
                </div>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 0.35rem;">
                <span style="font-family: 'Courier New', Courier, monospace; font-weight: 800; font-size: 1.05rem; color: #F26B1D;">
                    #${order.order_code}
                </span>
                <span style="font-size: 0.78rem; font-weight: 700; background: ${isDelivery ? '#EFF6FF' : '#ECFDF5'}; color: ${isDelivery ? '#1D4ED8' : '#047857'}; padding: 0.15rem 0.55rem; border-radius: 9999px;">
                    ${typeBadge}
                </span>
            </div>

            <div style="font-size: 0.88rem; font-weight: 600; color: #374151; margin-bottom: 0.25rem;">
                ${order.customer_name} &bull; <span style="color: #111827; font-weight: 800;">${order.total_formatted}</span>
            </div>

            <div style="font-size: 0.8rem; color: #6B7280; line-height: 1.4; margin-bottom: 0.85rem; max-height: 38px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                🍽️ ${order.items_summary}
            </div>

            <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                <a href="${window.location.origin}/UrbanEats-Cloud-Kitchen-Platform/orders/kitchen_queue.php" style="background: #F26B1D; color: #FFFFFF; text-decoration: none; font-size: 0.82rem; font-weight: 700; padding: 0.4rem 0.85rem; border-radius: 8px; box-shadow: 0 2px 6px rgba(242, 107, 29, 0.3); display: inline-flex; align-items: center; gap: 0.3rem;">
                    View in Kitchen Queue &rarr;
                </a>
            </div>
        `;

        container.appendChild(toast);

        // Slide in
        requestAnimationFrame(() => {
            toast.style.transform = 'translateX(0)';
            toast.style.opacity = '1';
        });

        // Close action
        const closeBtn = toast.querySelector('.ue-toast-close');
        function dismissToast() {
            toast.style.transform = 'translateX(120%)';
            toast.style.opacity = '0';
            setTimeout(() => {
                if (toast.parentNode) toast.parentNode.removeChild(toast);
            }, 350);
        }

        if (closeBtn) closeBtn.addEventListener('click', dismissToast);

        // Auto dismiss after 9 seconds
        setTimeout(dismissToast, 9000);

        // Desktop Notification
        if ('Notification' in window && Notification.permission === 'granted') {
            try {
                const notif = new Notification(`🔔 New Order #${order.order_code}`, {
                    body: `${order.customer_name} (${order.total_formatted})\n${order.items_summary}`,
                    icon: '/UrbanEats-Cloud-Kitchen-Platform/public/images/logo.png',
                    tag: `order-${order.order_id}`,
                });
                notif.onclick = function () {
                    window.focus();
                    window.location.href = '/UrbanEats-Cloud-Kitchen-Platform/orders/kitchen_queue.php';
                };
            } catch (err) {
                console.warn('Desktop Notification error:', err);
            }
        }
    }

    // 4. Live Polling Function
    async function pollLiveOrders() {
        try {
            const baseUrl = window.location.origin + '/UrbanEats-Cloud-Kitchen-Platform/api/live_orders.php';
            const url = isInitialized ? `${baseUrl}?since_id=${lastKnownOrderId}` : baseUrl;

            const res = await fetch(url, { credentials: 'same-origin' });
            if (!res.ok) return;

            const data = await res.json();
            if (!data || !data.success) return;

            // First run: calibrate max order ID so we don't alert old orders
            if (!isInitialized) {
                lastKnownOrderId = data.max_order_id || 0;
                isInitialized = true;
                updateLiveStatusPill(data.metrics);
                return;
            }

            // Subsequent runs: alert on each newly arrived order
            if (Array.isArray(data.new_orders) && data.new_orders.length > 0) {
                data.new_orders.forEach((order) => {
                    playCulinaryChime();
                    showOrderToast(order);
                });

                lastKnownOrderId = data.max_order_id;

                // If currently on kitchen queue page, trigger queue reload notification
                if (window.location.pathname.includes('kitchen_queue.php')) {
                    showQueueAutoRefreshBanner(data.new_orders.length);
                }
            }

            updateLiveStatusPill(data.metrics);

        } catch (e) {
            console.warn('[UrbanEats Live Polling] Connection check:', e);
        }
    }

    // 5. Update header/nav badge if present
    function updateLiveStatusPill(metrics) {
        if (!metrics) return;
        const activeBadges = document.querySelectorAll('.ue-live-active-orders');
        activeBadges.forEach(b => {
            b.textContent = metrics.active_count || 0;
        });

        const placedBadges = document.querySelectorAll('.ue-live-placed-count');
        placedBadges.forEach(b => {
            b.textContent = metrics.placed_count || 0;
        });
    }

    // 6. Live Auto-Refresh Banner for Kitchen Queue
    function showQueueAutoRefreshBanner(newCount) {
        let banner = document.getElementById('ue-queue-refresh-banner');
        if (!banner) {
            banner = document.createElement('div');
            banner.id = 'ue-queue-refresh-banner';
            banner.style.cssText = `
                position: fixed;
                bottom: 24px;
                left: 50%;
                transform: translateX(-50%);
                background: rgba(25, 20, 16, 0.92);
                color: #FFFFFF;
                backdrop-filter: blur(12px);
                border: 1px solid rgba(242, 107, 29, 0.5);
                border-radius: 9999px;
                padding: 0.65rem 1.4rem;
                font-size: 0.88rem;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
                z-index: 99999;
                cursor: pointer;
            `;
            banner.onclick = () => window.location.reload();
            document.body.appendChild(banner);
        }

        banner.innerHTML = `
            <span style="display:inline-block; width:10px; height:10px; border-radius:50%; background:#10B981; animation:ue-pulse-dot 1s infinite;"></span>
            <span>${newCount} New order ticket(s) received! Click to refresh queue &rarr;</span>
        `;

        // Automatically reload after 4 seconds
        setTimeout(() => {
            window.location.reload();
        }, 4000);
    }

    // 7. Render Floating Control Bar (Sound Toggle & Desktop Notification)
    function setupFloatingToolbar() {
        const bar = document.createElement('div');
        bar.id = 'ue-live-toolbar';
        bar.className = 'no-print';
        bar.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99998;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(234, 223, 211, 0.95);
            border-radius: 9999px;
            padding: 5px 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 0.78rem;
            color: #374151;
        `;

        const soundIcon = isSoundEnabled ? '🔔' : '🔕';
        const soundText = isSoundEnabled ? 'Sound On' : 'Muted';

        bar.innerHTML = `
            <span style="display: inline-flex; align-items: center; gap: 4px; font-weight: 700; color: #047857;">
                <span style="width: 8px; height: 8px; border-radius: 50%; background: #10B981; animation: ue-pulse-dot 1.5s infinite;"></span>
                Live Sync
            </span>
            <span style="color: #D1D5DB;">|</span>
            <button type="button" id="ue-sound-toggle-btn" style="background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: 3px; font-size: 0.78rem; font-weight: 600; color: #4B5563; padding: 2px 4px;">
                <span>${soundIcon}</span>
                <span id="ue-sound-label">${soundText}</span>
            </button>
        `;

        document.body.appendChild(bar);

        // Sound toggle event
        const soundBtn = document.getElementById('ue-sound-toggle-btn');
        if (soundBtn) {
            soundBtn.addEventListener('click', function () {
                isSoundEnabled = !isSoundEnabled;
                localStorage.setItem('ue_sound_enabled', isSoundEnabled ? 'true' : 'false');

                const iconSpan = soundBtn.querySelector('span:first-child');
                const labelSpan = document.getElementById('ue-sound-label');
                if (iconSpan) iconSpan.textContent = isSoundEnabled ? '🔔' : '🔕';
                if (labelSpan) labelSpan.textContent = isSoundEnabled ? 'Sound On' : 'Muted';

                if (isSoundEnabled) {
                    playCulinaryChime(); // Preview chime so user hears it works!
                }
            });
        }

        // Request Desktop notification permission on first user click anywhere
        if ('Notification' in window && Notification.permission === 'default') {
            document.addEventListener('click', function reqOnce() {
                Notification.requestPermission();
                document.removeEventListener('click', reqOnce);
            }, { once: true });
        }
    }

    // 8. Inject Animations
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

    // 9. Initialize engine on DOM Ready
    document.addEventListener('DOMContentLoaded', function () {
        setupFloatingToolbar();
        pollLiveOrders(); // Initial calibration
        pollInterval = setInterval(pollLiveOrders, 5000); // Poll every 5 seconds
    });

})();
