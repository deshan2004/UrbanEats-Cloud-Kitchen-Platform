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

</body>
</html>
