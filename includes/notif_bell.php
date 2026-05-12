<?php
/**
 * Reusable notification bell dropdown component.
 *
 * Requires these variables to be set before include:
 *   $notif_count   (int)    — unread count
 *   $notif_list    (array)  — result of get_notifikasi()
 *   $mark_all_url  (string) — URL to mark-all action (role-specific)
 */
?>
<style>
/* ===== NOTIF BELL DROPDOWN ===== */
.notif-wrapper {
    position: relative;
}
.notif-btn {
    background: none;
    border: none;
    cursor: pointer;
    font-size: 1.25rem;
    padding: 0.4rem 0.5rem;
    border-radius: 8px;
    color: var(--text-muted, #94a3b8);
    position: relative;
    transition: background 0.15s, color 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.notif-btn:hover { background: rgba(255,255,255,0.07); color: var(--text-main, #f1f5f9); }
.notif-badge {
    position: absolute;
    top: 0px;
    right: 0px;
    background: #ef4444;
    color: #fff;
    font-size: 0.55rem;
    padding: 2px 5px;
    border-radius: 99px;
    font-weight: 700;
    line-height: 1;
    min-width: 16px;
    text-align: center;
    border: 2px solid var(--bg-card, #1e293b);
}
.notif-dropdown {
    display: none;
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 340px;
    background: var(--bg-card, #1e293b);
    border: 1px solid var(--border, #334155);
    border-radius: 12px;
    box-shadow: 0 10px 30px -5px rgba(0,0,0,0.5);
    z-index: 1000;
    overflow: hidden;
}
.notif-dropdown.open { display: block; animation: slideDown 0.2s ease-out; }
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-5px); }
    to { opacity: 1; transform: translateY(0); }
}
.notif-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem;
    border-bottom: 1px solid var(--border, #334155);
    font-weight: 600;
    font-size: 0.875rem;
    color: var(--text-main);
}
.notif-mark-all-form { margin: 0; }
.notif-mark-all {
    background: none;
    border: none;
    color: var(--accent, #38bdf8);
    font-size: 0.75rem;
    cursor: pointer;
    padding: 0;
    font-weight: 500;
}
.notif-mark-all:hover { color: var(--accent-hover, #0ea5e9); }
.notif-list {
    max-height: 380px;
    overflow-y: auto;
}
.notif-list::-webkit-scrollbar { width: 4px; }
.notif-list::-webkit-scrollbar-track { background: transparent; }
.notif-list::-webkit-scrollbar-thumb { background: var(--border, #334155); border-radius: 4px; }
.notif-item {
    padding: 0.875rem 1rem;
    border-bottom: 1px solid rgba(51,65,85,0.5);
    transition: background 0.15s;
    display: flex;
    gap: 0.75rem;
}
.notif-item:last-child { border-bottom: none; }
.notif-item:hover { background: rgba(255,255,255,0.03); }
.notif-item.notif-unread {
    background: rgba(56,189,248,0.04);
}
.notif-unread-indicator {
    width: 8px;
    height: 8px;
    background: var(--accent, #38bdf8);
    border-radius: 50%;
    margin-top: 4px;
    flex-shrink: 0;
}
.notif-content { flex: 1; }
.notif-text {
    font-size: 0.8125rem;
    color: var(--text-main, #f1f5f9);
    line-height: 1.5;
    margin-bottom: 0.25rem;
}
.notif-time {
    font-size: 0.75rem;
    color: var(--text-muted, #94a3b8);
}
.notif-empty {
    padding: 3rem 1rem;
    text-align: center;
    color: var(--text-muted, #94a3b8);
    font-size: 0.875rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.75rem;
}
</style>

<div class="notif-wrapper" id="notifWrapper">
    <button class="notif-btn" id="notifBtn" type="button" aria-label="Notifikasi" onclick="toggleNotifDropdown(event)">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
        <?php if ($notif_count > 0): ?>
            <span class="notif-badge"><?= (int)$notif_count ?></span>
        <?php endif; ?>
    </button>

    <div class="notif-dropdown" id="notifDropdown">
        <div class="notif-header">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                <span>Notifikasi</span>
            </div>
            <?php if ($notif_count > 0): ?>
                <form class="notif-mark-all-form" method="POST" action="<?= htmlspecialchars($mark_all_url) ?>">
                    <input type="hidden" name="redirect" value="<?= htmlspecialchars($_SERVER['REQUEST_URI']) ?>">
                    <button type="submit" class="notif-mark-all">Tandai semua dibaca</button>
                </form>
            <?php endif; ?>
        </div>
        <div class="notif-list">
            <?php if (empty($notif_list)): ?>
                <div class="notif-empty">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="opacity: 0.5;"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path><path d="M13.73 21a2 2 0 0 1-3.46 0"></path></svg>
                    <span>Belum ada notifikasi baru</span>
                </div>
            <?php else: ?>
                <?php foreach ($notif_list as $n): ?>
                <div class="notif-item <?= !$n['is_read'] ? 'notif-unread' : '' ?>">
                    <?php if (!$n['is_read']): ?>
                        <div class="notif-unread-indicator"></div>
                    <?php endif; ?>
                    <div class="notif-content">
                        <div class="notif-text">
                            <?= htmlspecialchars($n['pesan']) ?>
                        </div>
                        <div class="notif-time"><?= time_ago($n['created_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function () {
    function toggleNotifDropdown(e) {
        e.stopPropagation();
        var dd = document.getElementById('notifDropdown');
        dd.classList.toggle('open');
    }
    window.toggleNotifDropdown = toggleNotifDropdown;

    document.addEventListener('click', function (e) {
        var wrapper = document.getElementById('notifWrapper');
        if (wrapper && !wrapper.contains(e.target)) {
            var dd = document.getElementById('notifDropdown');
            if (dd) dd.classList.remove('open');
        }
    });
})();
</script>

