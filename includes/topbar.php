<style>
    /* 1. TOPBAR STYLES */
    .top-header {
        background: #1e3c72; 
        color: white; 
        padding: 10px 20px;
        display: flex; 
        justify-content: space-between; 
        align-items: center;
        height: 60px; 
        box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        margin-bottom: 20px;
        
        /* STICKY MAGIC START */
        position: sticky;      
        top: 0;                 
        z-index: 1000;          /* Ensure it stays ON TOP of everything else */
        width: 100%;            
        box-sizing: border-box; 
    }
    
    .brand-area { display: flex; align-items: center; gap: 15px; }
    .brand-logo { height: 40px; background: white; border-radius: 5px; padding: 2px; }
    .brand-title { font-size: 20px; font-weight: bold; letter-spacing: 0.5px; color: white; text-decoration: none; }
    
    .btn-back-nav {
        background: rgba(255,255,255,0.2); color: white; 
        padding: 8px 15px; border-radius: 6px; text-decoration: none; 
        font-weight: 600; font-size: 14px; transition: 0.3s; cursor: pointer; border: none;
    }
    .btn-back-nav:hover { background: rgba(255,255,255,0.3); }
    .btn-logout {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 8px 15px;
        border-radius: 6px;
        text-decoration: none;
        font-weight: 600;
        font-size: 14px;
        transition: 0.3s;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .btn-logout:hover { background: rgba(255,255,255,0.3); }

    /* 2. LOADER STYLES */
    #loader-wrapper {
        position: fixed; top: 0; left: 0; width: 100%; height: 100%;
        background: #ffffff; z-index: 9999;
        display: flex; justify-content: center; align-items: center;
        transition: opacity 0.5s ease, visibility 30s;
    }
    .loader-hidden { opacity: 0; visibility: hidden; pointer-events: none; }
    
    .loader-logo {
        width: 80px; animation: pulse 1.5s infinite alternate;
    }
    @keyframes pulse { from { transform: scale(1); opacity: 1; } to { transform: scale(1.2); opacity: 0.7; } }

    /* 3. ADMIN MOBILE NAV */
    .admin-mobile-ui .sidebar-mobile-backdrop,
    .sales-mobile-ui .sidebar-mobile-backdrop {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.42);
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.22s ease, visibility 0.22s ease;
        z-index: 1190;
    }

    .admin-mobile-ui .sidebar-mobile-backdrop.show,
    .sales-mobile-ui .sidebar-mobile-backdrop.show {
        opacity: 1;
        visibility: visible;
    }

    .admin-mobile-ui .sidebar-mobile-fab,
    .sales-mobile-ui .sidebar-mobile-fab {
        position: fixed;
        right: 18px;
        bottom: 20px;
        width: 58px;
        height: 58px;
        border: none;
        border-radius: 50%;
        background: linear-gradient(135deg, #1e3c72 0%, #294f92 100%);
        color: #fff;
        box-shadow: 0 16px 30px rgba(30, 60, 114, 0.3);
        display: none;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        cursor: pointer;
        z-index: 1300;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .admin-mobile-ui .sidebar-mobile-fab:hover,
    .sales-mobile-ui .sidebar-mobile-fab:hover {
        transform: translateY(-2px);
        box-shadow: 0 20px 36px rgba(30, 60, 114, 0.36);
    }

    .admin-mobile-ui .sidebar-mobile-logout,
    .sales-mobile-ui .sidebar-mobile-logout {
        display: none;
        margin-top: auto;
        background: rgba(255,255,255,0.14);
        border: 1px solid rgba(255,255,255,0.16);
    }

    .admin-mobile-ui .sidebar-mobile-logout:hover,
    .sales-mobile-ui .sidebar-mobile-logout:hover {
        background: rgba(255,255,255,0.22);
    }

    @media (max-width: 900px) {
        .admin-mobile-ui .top-header,
        .sales-mobile-ui .top-header,
        .dashboard-page .top-header {
            height: 58px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }

        .admin-mobile-ui .brand-area,
        .sales-mobile-ui .brand-area,
        .dashboard-page .brand-area {
            gap: 10px;
            min-width: 0;
        }

        .admin-mobile-ui .brand-logo,
        .sales-mobile-ui .brand-logo,
        .dashboard-page .brand-logo {
            height: 34px;
        }

        .admin-mobile-ui .brand-title,
        .sales-mobile-ui .brand-title,
        .dashboard-page .brand-title {
            font-size: 16px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .admin-mobile-ui .btn-back-nav,
        .sales-mobile-ui .btn-back-nav,
        .dashboard-page .btn-back-nav {
            padding: 8px 10px;
            font-size: 13px;
        }

        .admin-mobile-ui.sidebar-open,
        .sales-mobile-ui.sidebar-open {
            overflow: hidden;
        }

        .admin-mobile-ui .top-header .btn-logout,
        .sales-mobile-ui .top-header .btn-logout {
            display: none;
        }

        .admin-mobile-ui .dashboard-container,
        .sales-mobile-ui .dashboard-container,
        .sales-mobile-ui .page-wrap {
            min-height: auto !important;
            height: auto !important;
            overflow: visible !important;
        }

        .admin-mobile-ui .main-content,
        .sales-mobile-ui .main-content {
            height: auto !important;
            overflow: visible !important;
            padding: 14px 12px 88px !important;
        }

        .admin-mobile-ui .header-title,
        .sales-mobile-ui .header-title {
            font-size: 20px !important;
            line-height: 1.2;
            margin-bottom: 16px !important;
        }

        .admin-mobile-ui .sidebar,
        .sales-mobile-ui .sidebar {
            position: fixed !important;
            top: 70px !important;
            left: 12px !important;
            bottom: 12px !important;
            width: min(292px, calc(100vw - 24px)) !important;
            height: auto !important;
            max-height: none !important;
            border-radius: 22px !important;
            z-index: 1200 !important;
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
            box-shadow: 0 20px 44px rgba(15, 23, 42, 0.24) !important;
            transition: transform 0.24s ease, opacity 0.24s ease !important;
            padding: 18px 14px !important;
        }

        .admin-mobile-ui .sidebar-header,
        .sales-mobile-ui .sidebar-header {
            margin-bottom: 14px !important;
            padding-bottom: 12px !important;
        }

        .admin-mobile-ui .sidebar a,
        .sales-mobile-ui .sidebar a {
            display: flex !important;
            width: 100% !important;
            margin: 0 0 8px 0 !important;
            padding: 12px 14px !important;
            border-radius: 14px !important;
            font-size: 15px !important;
        }

        .admin-mobile-ui .sidebar a i,
        .sales-mobile-ui .sidebar a i {
            width: 22px !important;
            margin-right: 12px !important;
        }

        .admin-mobile-ui .sidebar.collapsed,
        .sales-mobile-ui .sidebar.collapsed {
            width: min(292px, calc(100vw - 24px)) !important;
            transform: translateX(-115%) !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }

        .admin-mobile-ui .sidebar-mobile-fab,
        .sales-mobile-ui .sidebar-mobile-fab {
            display: inline-flex;
            right: 16px;
            bottom: 16px;
            width: 54px;
            height: 54px;
        }

        .admin-mobile-ui .sidebar-mobile-logout,
        .sales-mobile-ui .sidebar-mobile-logout {
            display: flex !important;
            margin-top: 14px !important;
        }

        .admin-mobile-ui .page-card,
        .admin-mobile-ui .table-container,
        .sales-mobile-ui .page-card,
        .sales-mobile-ui .table-container,
        .sales-mobile-ui .history-card,
        .sales-mobile-ui .payout-card,
        .sales-mobile-ui .profile-card {
            border-radius: 18px !important;
            padding: 16px !important;
        }

        .admin-mobile-ui .table-container,
        .sales-mobile-ui .table-container,
        .sales-mobile-ui .history-card {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .admin-mobile-ui .styled-table,
        .sales-mobile-ui .styled-table {
            min-width: 640px;
        }

        .admin-mobile-ui .styled-table th,
        .admin-mobile-ui .styled-table td,
        .sales-mobile-ui .styled-table th,
        .sales-mobile-ui .styled-table td {
            padding: 10px 12px;
        }

        .admin-mobile-ui .form-card {
            padding: 22px !important;
        }

        .admin-mobile-ui .search-container {
            flex-direction: column !important;
            gap: 12px !important;
            align-items: stretch !important;
        }

        .admin-mobile-ui .search-box {
            width: 100% !important;
        }

        .sales-mobile-ui .search-container,
        .sales-mobile-ui .filter-bar,
        .sales-mobile-ui .search-form,
        .sales-mobile-ui .payout-form {
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 12px !important;
        }

        .sales-mobile-ui .search-box,
        .sales-mobile-ui .date-range {
            width: 100% !important;
            max-width: none !important;
        }
    }

</style>

<div id="loader-wrapper">
    <img src="../img/logo.png" alt="Loading..." class="loader-logo">
</div>

<div class="top-header">
    <?php if(isset($is_dashboard) && $is_dashboard === true): ?>
        <div class="brand-area">
            <img src="../img/logo.png" alt="Logo" class="brand-logo">
            <span class="brand-title">Galadawa Textiles</span>
        </div>
        <div></div>
    <?php else: ?>
        <div>
            <button onclick="history.back()" class="btn-back-nav">
                <i class="fas fa-arrow-left"></i> Back
            </button>
        </div>
        <div class="brand-area">
            <span class="brand-title">Galadawa Textiles</span>
            <img src="../img/logo.png" alt="Logo" class="brand-logo">
        </div>
    <?php endif; ?>
    <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<script>
    window.addEventListener('pageshow', function() { 
        document.getElementById('loader-wrapper').classList.add('loader-hidden'); 
    });
    
    // Show loader on link clicks (optional polish)
    document.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', function(e) {
            const target = this.getAttribute('href');
            if(target && target !== '#' && !target.startsWith('javascript') && !target.includes('#')) {
                document.getElementById('loader-wrapper').classList.remove('loader-hidden');
            }
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const body = document.body;
        const usesMobileShell = body.classList.contains('admin-mobile-ui') || body.classList.contains('sales-mobile-ui');
        if (!usesMobileShell) return;

        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        const mobileQuery = window.matchMedia('(max-width: 900px)');
        let wasMobile = mobileQuery.matches;

        let backdrop = document.getElementById('sidebarMobileBackdrop');
        if (!backdrop) {
            backdrop = document.createElement('div');
            backdrop.id = 'sidebarMobileBackdrop';
            backdrop.className = 'sidebar-mobile-backdrop';
            body.appendChild(backdrop);
        }

        let fab = document.getElementById('sidebarMobileFab');
        if (!fab) {
            fab = document.createElement('button');
            fab.type = 'button';
            fab.id = 'sidebarMobileFab';
            fab.className = 'sidebar-mobile-fab';
            fab.setAttribute('aria-label', 'Open menu');
            fab.innerHTML = '<i class="fas fa-bars"></i>';
            body.appendChild(fab);
        }

        if (!sidebar.querySelector('.sidebar-mobile-logout')) {
            const logoutLink = document.createElement('a');
            logoutLink.href = '../logout.php';
            logoutLink.className = 'sidebar-mobile-logout';
            logoutLink.innerHTML = '<i class="fas fa-sign-out-alt"></i><span>Logout</span>';
            sidebar.appendChild(logoutLink);
        }

        function setFabState(isOpen) {
            const icon = fab.querySelector('i');
            if (!icon) return;
            icon.className = isOpen ? 'fas fa-times' : 'fas fa-bars';
            fab.setAttribute('aria-label', isOpen ? 'Close menu' : 'Open menu');
        }

        function syncSidebarState() {
            if (!mobileQuery.matches) {
                body.classList.remove('sidebar-open');
                backdrop.classList.remove('show');
                setFabState(false);
                return;
            }

            const isOpen = !sidebar.classList.contains('collapsed');
            body.classList.toggle('sidebar-open', isOpen);
            backdrop.classList.toggle('show', isOpen);
            setFabState(isOpen);
        }

        function closeMobileSidebar() {
            if (!mobileQuery.matches) return;
            sidebar.classList.add('collapsed');
            syncSidebarState();
        }

        if (mobileQuery.matches) {
            sidebar.classList.add('collapsed');
        }
        syncSidebarState();

        fab.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            syncSidebarState();
        });

        backdrop.addEventListener('click', closeMobileSidebar);

        document.querySelectorAll('.toggle-btn').forEach((btn) => {
            btn.addEventListener('click', function() {
                setTimeout(syncSidebarState, 0);
            });
        });

        sidebar.querySelectorAll('a').forEach((link) => {
            link.addEventListener('click', function() {
                if (mobileQuery.matches) {
                    closeMobileSidebar();
                }
            });
        });

        window.addEventListener('resize', function() {
            const nowMobile = mobileQuery.matches;
            if (nowMobile && !wasMobile) {
                sidebar.classList.add('collapsed');
            }
            if (!nowMobile && wasMobile) {
                body.classList.remove('sidebar-open');
                backdrop.classList.remove('show');
                sidebar.classList.remove('collapsed');
                setFabState(false);
            }
            wasMobile = nowMobile;
            syncSidebarState();
        });
    });

    document.addEventListener('DOMContentLoaded', function() {
        const endpoint = '../includes/live_updates.php';
        const pollIntervalMs = 5000;
        const currentPath = window.location.pathname.replace(/\\/g, '/');
        const routeMatch = currentPath.match(/\/(admin|sales)\/([^\/?#]+)$/);
        const routeKey = routeMatch ? `${routeMatch[1]}/${routeMatch[2]}` : '';
        const pageWatchMap = {
            'admin/dashboard.php': ['products', 'users', 'sales', 'holds', 'exchanges', 'payouts'],
            'admin/add_product.php': ['products'],
            'admin/view_inventory.php': ['products'],
            'admin/manage_users.php': ['users'],
            'admin/product_details.php': ['products'],
            'admin/update_product.php': ['products'],
            'admin/holding_orders.php': ['holds', 'products'],
            'admin/exchange_history.php': ['exchanges', 'sales', 'products'],
            'sales/dashboard.php': ['sales', 'holds', 'payouts'],
            'sales/pos.php': ['products'],
            'sales/inventory_view.php': ['products'],
            'sales/profile.php': ['users'],
            'sales/exchange.php': ['sales', 'products', 'exchanges'],
            'sales/holding_orders.php': ['holds', 'products'],
            'sales/view_product.php': ['products'],
            'sales/my_history.php': ['sales', 'exchanges', 'payouts'],
        };
        const watchedEntities = pageWatchMap[routeKey] || [];
        let isPolling = false;
        let liveState = {
            initialized: false,
            role: null,
            admin: {
                latest_sale_id: 0,
                latest_payout_request_id: 0,
                pending_count: 0
            },
            sales: {
                unread_count: 0
            }
        };
        let livePageState = {
            lastSignature: null,
            pendingSignature: null,
            deferredNoticeShown: false
        };

        Array.from(document.forms || []).forEach((form) => {
            const markDirty = () => { form.dataset.liveDirty = '1'; };
            form.addEventListener('input', markDirty, { passive: true });
            form.addEventListener('change', markDirty, { passive: true });
            form.addEventListener('submit', function() {
                form.dataset.liveDirty = '0';
            });
        });

        function buildWatchedSignature(payload) {
            if (!Array.isArray(watchedEntities) || watchedEntities.length === 0) return '';
            const signatures = payload && payload.signatures ? payload.signatures : {};
            return watchedEntities.map((key) => String(signatures[key] || '0')).join('||');
        }

        function hasDirtyForm() {
            return Array.from(document.forms || []).some((form) => form.dataset.liveDirty === '1');
        }

        function hasActiveEditor() {
            const activeEl = document.activeElement;
            if (!activeEl) return false;
            if (activeEl.closest && activeEl.closest('.swal2-container')) return true;
            if (activeEl.isContentEditable) return true;
            return ['INPUT', 'TEXTAREA', 'SELECT'].includes(activeEl.tagName) && !activeEl.readOnly && !activeEl.disabled;
        }

        function canApplyLiveRefresh() {
            return !hasDirtyForm() && !hasActiveEditor();
        }

        function showDeferredLiveNotice() {
            if (livePageState.deferredNoticeShown || !window.showToast) return;
            showToast('Live changes detected. This page will update when you finish editing.', { type: 'info', duration: 2600 });
            livePageState.deferredNoticeShown = true;
        }

        function applyPendingLiveRefresh() {
            if (!livePageState.pendingSignature) return;
            if (!canApplyLiveRefresh()) return;
            window.location.reload();
        }

        function maybeRefreshCurrentPage(payload) {
            const nextSignature = buildWatchedSignature(payload);
            if (!nextSignature) return;

            if (livePageState.lastSignature === null) {
                livePageState.lastSignature = nextSignature;
                return;
            }

            if (nextSignature === livePageState.lastSignature) {
                if (!livePageState.pendingSignature) {
                    livePageState.deferredNoticeShown = false;
                }
                return;
            }

            livePageState.lastSignature = nextSignature;
            livePageState.pendingSignature = nextSignature;

            if (canApplyLiveRefresh()) {
                window.location.reload();
                return;
            }

            showDeferredLiveNotice();
        }

        document.addEventListener('focusout', function() {
            setTimeout(applyPendingLiveRefresh, 80);
        });
        document.addEventListener('click', function() {
            setTimeout(applyPendingLiveRefresh, 0);
        });
        document.addEventListener('change', function() {
            setTimeout(applyPendingLiveRefresh, 0);
        });

        function ensureNotifDot(link) {
            let dot = link.querySelector('.notif-dot');
            if (!dot) {
                dot = document.createElement('span');
                dot.className = 'notif-dot';
                link.appendChild(dot);
            }
            return dot;
        }

        function syncNotifLink(link, count, countText) {
            if (!link) return;

            link.querySelectorAll('.notif-dot').forEach((dot) => {
                if (count <= 0) {
                    dot.remove();
                }
            });

            if (count > 0) {
                ensureNotifDot(link);
            }

            link.querySelectorAll(':scope > span').forEach((span) => {
                const text = (span.textContent || '').trim();
                if (/^\(\d+\)$/.test(text) && !span.classList.contains('live-notif-count') && !span.classList.contains('js-admin-pending-count')) {
                    span.classList.add('live-notif-count');
                }
            });

            let counter = link.querySelector('.js-admin-pending-count, .live-notif-count');
            if (countText) {
                if (!counter) {
                    counter = document.createElement('span');
                    counter.className = 'live-notif-count';
                    counter.style.marginLeft = '6px';
                    counter.style.fontSize = '11px';
                    counter.style.color = '#fff';
                    counter.style.opacity = '0.8';
                    link.appendChild(counter);
                }
                counter.textContent = countText;
            } else {
                link.querySelectorAll('.js-admin-pending-count, .live-notif-count').forEach((node) => node.remove());
            }
        }

        function updateSidebarNotifications(payload) {
            if (!payload || !payload.role) return;

            if (payload.role === 'admin' && payload.admin) {
                const pendingCount = Number(payload.admin.pending_count || 0);
                const activeHoldCount = Number(payload.admin.active_hold_count || 0);
                document.querySelectorAll('.sidebar a[href$="payout_requests.php"]').forEach((link) => {
                    syncNotifLink(link, pendingCount, pendingCount > 0 ? `(${pendingCount})` : '');
                });
                document.querySelectorAll('.sidebar a[href$="holding_orders.php"]').forEach((link) => {
                    syncNotifLink(link, activeHoldCount, activeHoldCount > 0 ? `(${activeHoldCount})` : '');
                });
                return;
            }

            if (payload.sales) {
                const unreadCount = Number(payload.sales.unread_count || 0);
                const activeHoldCount = Number(payload.sales.active_hold_count || 0);
                document.querySelectorAll('.sidebar a[href$="payouts.php"]').forEach((link) => {
                    syncNotifLink(link, unreadCount, '');
                });
                document.querySelectorAll('.sidebar a[href$="holding_orders.php"]').forEach((link) => {
                    syncNotifLink(link, activeHoldCount, activeHoldCount > 0 ? `(${activeHoldCount})` : '');
                });
            }
        }

        function maybeShowLiveToast(payload) {
            if (!liveState.initialized || !window.showToast) return;

            if (payload.role === 'admin' && payload.admin) {
                const latestSaleId = Number(payload.admin.latest_sale_id || 0);
                const latestPayoutId = Number(payload.admin.latest_payout_request_id || 0);
                const pendingCount = Number(payload.admin.pending_count || 0);

                if (latestPayoutId > liveState.admin.latest_payout_request_id || pendingCount > liveState.admin.pending_count) {
                    showToast('New payout request received.', { type: 'info', duration: 2500 });
                }

                if (latestSaleId > liveState.admin.latest_sale_id) {
                    showToast('New sale recorded.', { type: 'success', duration: 2200 });
                }
                return;
            }

            if (payload.sales) {
                const unreadCount = Number(payload.sales.unread_count || 0);
                if (unreadCount > liveState.sales.unread_count) {
                    const status = payload.sales.latest_notice && payload.sales.latest_notice.status
                        ? String(payload.sales.latest_notice.status).replace('_', ' ')
                        : 'updated';
                    showToast(`Payout request ${status}.`, { type: 'info', duration: 2600 });
                }
            }
        }

        function persistLiveState(payload) {
            liveState.initialized = true;
            liveState.role = payload.role || null;
            if (payload.admin) {
                liveState.admin.latest_sale_id = Number(payload.admin.latest_sale_id || 0);
                liveState.admin.latest_payout_request_id = Number(payload.admin.latest_payout_request_id || 0);
                liveState.admin.pending_count = Number(payload.admin.pending_count || 0);
            }
            if (payload.sales) {
                liveState.sales.unread_count = Number(payload.sales.unread_count || 0);
            }
        }

        function broadcastLiveUpdate(payload) {
            document.dispatchEvent(new CustomEvent('galadawa:live-update', {
                detail: payload
            }));
        }

        async function pollLiveUpdates() {
            if (isPolling || document.hidden) return;
            isPolling = true;

            try {
                const response = await fetch(`${endpoint}?t=${Date.now()}`, {
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (!response.ok) return;

                const payload = await response.json();
                if (!payload || payload.ok !== true) return;

                maybeShowLiveToast(payload);
                updateSidebarNotifications(payload);
                broadcastLiveUpdate(payload);
                maybeRefreshCurrentPage(payload);
                persistLiveState(payload);
            } catch (error) {
                // Ignore transient polling failures and try again next cycle.
            } finally {
                isPolling = false;
            }
        }

        pollLiveUpdates();
        setInterval(pollLiveUpdates, pollIntervalMs);
        document.addEventListener('visibilitychange', function() {
            if (!document.hidden) {
                pollLiveUpdates();
            }
        });
    });

</script>
