            </main>
            <!-- Fin content-wrapper -->

            <!-- Footer -->
            <footer class="py-3 px-4 text-center text-muted small border-top bg-white">
                <span>&copy; <?= date('Y') ?> <?= APP_FULL_NAME ?> - <?= ACADEMIE_NAME ?></span>
                <span class="mx-2">|</span>
                <span><?= APP_SLOGAN ?></span>
            </footer>
        </div>
        <!-- Fin main-content -->
    </div>
    <!-- Fin app-wrapper -->

    <!-- Overlay pour mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Modal de confirmation global -->
    <div class="modal fade" id="appConfirmModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-question-circle text-primary me-2"></i>Confirmation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="modal-message mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light btn-cancel" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary btn-confirm">Confirmer</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal d'alerte global -->
    <div class="modal fade" id="appAlertModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-info-circle text-info me-2"></i>Information</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="modal-message mb-0"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery (requis pour Select2) - DOIT être chargé en premier -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>

    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

    <!-- FullCalendar 6 (locales incluses dans le bundle) -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/i18n/fr.js"></script>

    <!-- Scripts personnalisés -->
    <script src="<?= asset('js/app.js') ?>"></script>

    <!-- Notifications System -->
    <?php if (Auth::check()): ?>
    <script>
    // Configuration des notifications
    const NotifConfig = {
        apiUrl: '<?= APP_URL ?>/api/notifications.php',
        pollInterval: 60000, // 60 secondes
        maxDisplay: 5
    };

    // Charger les notifications (uniquement non lues pour le dropdown)
    function loadNotifications() {
        fetch(NotifConfig.apiUrl + '?action=list&limit=' + NotifConfig.maxDisplay + '&unread=1', {
            method: 'GET',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                updateNotificationsUI(data.notifications, data.unread_count);
            }
        })
        .catch(error => {
            console.warn('Notifications non disponibles:', error.message);
        });
    }

    // Mettre à jour l'UI des notifications
    function updateNotificationsUI(notifications, unreadCount) {
        const countBadge = document.getElementById('notifCount');
        const listContainer = document.getElementById('notificationsList');

        // Mettre à jour le compteur
        if (countBadge) {
            if (unreadCount > 0) {
                countBadge.textContent = unreadCount > 99 ? '99+' : unreadCount;
                countBadge.style.display = 'flex';
            } else {
                countBadge.style.display = 'none';
            }
        }

        // Mettre à jour la liste
        if (listContainer) {
            if (notifications.length === 0) {
                listContainer.innerHTML = '<div class="text-center text-muted py-4"><i class="fas fa-check-circle fa-2x mb-2 opacity-50 text-success"></i><p class="mb-0 small">Aucune notification non lue</p></div>';
            } else {
                let html = '';
                notifications.forEach(notif => {
                    const isUnread = notif.lu == 0;
                    const typeClass = getNotifTypeClass(notif.type);
                    const timeAgo = formatTimeAgo(notif.created_at);

                    html += `
                        <a href="${notif.lien || '#'}" class="dropdown-item notification-item ${isUnread ? 'unread' : ''}"
                           onclick="markNotificationRead(${notif.id}, event)">
                            <div class="d-flex align-items-start">
                                <div class="notification-icon ${typeClass}">
                                    <i class="fas fa-${notif.icone || 'bell'}"></i>
                                </div>
                                <div class="flex-grow-1 ms-2">
                                    <div class="notification-title">${escapeHtml(notif.titre)}</div>
                                    <div class="notification-text">${escapeHtml(notif.message)}</div>
                                    <div class="notification-time">${timeAgo}</div>
                                </div>
                                ${isUnread ? '<span class="unread-dot"></span>' : ''}
                            </div>
                        </a>
                    `;
                });
                listContainer.innerHTML = html;
            }
        }
    }

    // Classes de couleur par type
    function getNotifTypeClass(type) {
        const classes = {
            'info': 'bg-info',
            'success': 'bg-success',
            'warning': 'bg-warning',
            'danger': 'bg-danger'
        };
        return classes[type] || 'bg-secondary';
    }

    // Formater le temps écoulé
    function formatTimeAgo(dateStr) {
        const date = new Date(dateStr);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);

        if (diffMins < 1) return 'À l\'instant';
        if (diffMins < 60) return `Il y a ${diffMins} min`;
        if (diffHours < 24) return `Il y a ${diffHours}h`;
        if (diffDays < 7) return `Il y a ${diffDays}j`;
        return date.toLocaleDateString('fr-FR');
    }

    // Échapper HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Marquer une notification comme lue
    function markNotificationRead(id, event) {
        const formData = new FormData();
        formData.append('id', id);

        fetch(NotifConfig.apiUrl + '?action=read', {
            method: 'POST',
            body: formData
        }).then(() => {
            // Mettre à jour le compteur
            const countBadge = document.getElementById('notifCount');
            if (countBadge) {
                const current = parseInt(countBadge.textContent) || 0;
                if (current > 1) {
                    countBadge.textContent = current - 1;
                } else {
                    countBadge.style.display = 'none';
                }
            }
        });
    }

    // Marquer toutes les notifications comme lues
    function markAllNotificationsRead() {
        fetch(NotifConfig.apiUrl + '?action=read_all', {
            method: 'POST'
        }).then(response => response.json())
        .then(data => {
            if (data.success) {
                // Masquer le compteur
                const countBadge = document.getElementById('notifCount');
                if (countBadge) countBadge.style.display = 'none';

                // Retirer les indicateurs non-lu
                document.querySelectorAll('.notification-item.unread').forEach(item => {
                    item.classList.remove('unread');
                    const dot = item.querySelector('.unread-dot');
                    if (dot) dot.remove();
                });
            }
        });
    }

    // Charger au démarrage et toutes les 60 secondes
    document.addEventListener('DOMContentLoaded', function() {
        loadNotifications();
        setInterval(loadNotifications, NotifConfig.pollInterval);

        // Charger les notifications quand on ouvre le dropdown
        var notifDropdown = document.getElementById('notificationsDropdown');
        if (notifDropdown) {
            notifDropdown.addEventListener('shown.bs.dropdown', function() {
                loadNotifications();
            });
        }
    });
    </script>
    <style>
    /* Styles pour les notifications */
    .notification-item {
        padding: 10px 15px !important;
        border-bottom: 1px solid #f0f0f0;
        white-space: normal !important;
    }
    .notification-item:last-child {
        border-bottom: none;
    }
    .notification-item.unread {
        background-color: #f8f9ff;
    }
    .notification-item:hover {
        background-color: #f0f4ff !important;
    }
    .notification-icon {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 14px;
        flex-shrink: 0;
    }
    .notification-title {
        font-weight: 600;
        font-size: 13px;
        color: #333;
        line-height: 1.3;
    }
    .notification-text {
        font-size: 12px;
        color: #666;
        line-height: 1.3;
        margin-top: 2px;
    }
    .notification-time {
        font-size: 11px;
        color: #999;
        margin-top: 4px;
    }
    .unread-dot {
        width: 8px;
        height: 8px;
        background-color: #3b82f6;
        border-radius: 50%;
        flex-shrink: 0;
        margin-left: 8px;
    }
    #notifCount {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        font-size: 10px;
        min-width: 18px;
        height: 18px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
    }
    </style>
    <?php endif; ?>

    <?php if (isset($extraJs)): ?>
        <?php foreach ($extraJs as $js): ?>
            <script src="<?= asset($js) ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (isset($inlineJs)): ?>
        <script><?= $inlineJs ?></script>
    <?php endif; ?>

    <style>
    /* Overlay mobile */
    .sidebar-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        z-index: 999;
    }

    .sidebar-overlay.active {
        display: block;
    }

    @media (max-width: 992px) {
        .sidebar.active ~ .sidebar-overlay {
            display: block;
        }
    }
    </style>
</body>
</html>
