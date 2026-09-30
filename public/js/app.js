/**
 * Scripts principaux - Complexe Sportif Kaira
 * Académie Khaïra Foot (AKF)
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialisation
    initSidebar();
    initTooltips();
    initAlerts();
    initSearch();
    initShortcuts();
    initConfirmHandlers();
});

/**
 * Gestion du sidebar
 */
function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleBtn = document.getElementById('sidebarToggle');
    const overlay = document.getElementById('sidebarOverlay');

    if (!sidebar || !toggleBtn) return;

    // Récupérer l'état sauvegardé
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && window.innerWidth > 992) {
        sidebar.classList.add('collapsed');
        mainContent?.classList.add('sidebar-collapsed');
    }

    // Toggle sidebar
    toggleBtn.addEventListener('click', function() {
        if (window.innerWidth <= 992) {
            // Mobile: slide in/out
            sidebar.classList.toggle('active');
            overlay?.classList.toggle('active');
        } else {
            // Desktop: collapse/expand
            sidebar.classList.toggle('collapsed');
            mainContent?.classList.toggle('sidebar-collapsed');
            localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
        }
    });

    // Fermer sidebar sur clic overlay
    overlay?.addEventListener('click', function() {
        sidebar.classList.remove('active');
        overlay.classList.remove('active');
    });

    // Responsive
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            sidebar.classList.remove('active');
            overlay?.classList.remove('active');
        }
    });
}

/**
 * Initialiser les tooltips Bootstrap
 */
function initTooltips() {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function(tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });
}

/**
 * Auto-fermeture des alertes
 */
function initAlerts() {
    const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert?.close();
        }, 5000);
    });
}

/**
 * Recherche globale
 */
function initSearch() {
    const searchInput = document.getElementById('globalSearch');
    if (!searchInput) return;

    let searchTimeout;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            hideSearchResults();
            return;
        }

        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, 300);
    });

    // Fermer au clic extérieur
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.global-search')) {
            hideSearchResults();
        }
    });

    // Raccourci clavier
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            this.value = '';
            this.blur();
            hideSearchResults();
        }
    });
}

function performSearch(query) {
    // TODO: Implémenter la recherche AJAX
    console.log('Recherche:', query);
}

function hideSearchResults() {
    const results = document.getElementById('searchResults');
    if (results) {
        results.style.display = 'none';
    }
}

/**
 * Raccourcis clavier
 */
function initShortcuts() {
    document.addEventListener('keydown', function(e) {
        // Ignorer si dans un input
        if (e.target.matches('input, textarea, select')) return;

        // N = Nouvelle réservation
        if (e.key === 'n' && !e.ctrlKey && !e.metaKey) {
            window.location.href = BASE_URL + '/reservations/nouveau.php';
        }

        // / = Focus recherche
        if (e.key === '/' && !e.ctrlKey && !e.metaKey) {
            e.preventDefault();
            document.getElementById('globalSearch')?.focus();
        }

        // Escape = Fermer modals
        if (e.key === 'Escape') {
            const modals = document.querySelectorAll('.modal.show');
            modals.forEach(function(modal) {
                const bsModal = bootstrap.Modal.getInstance(modal);
                bsModal?.hide();
            });
        }
    });
}

/**
 * Afficher une notification toast
 */
function showToast(message, type = 'info') {
    const toastContainer = document.getElementById('toastContainer') || createToastContainer();

    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type} border-0`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    toastContainer.appendChild(toast);
    const bsToast = new bootstrap.Toast(toast, { delay: 4000 });
    bsToast.show();

    toast.addEventListener('hidden.bs.toast', function() {
        toast.remove();
    });
}

function createToastContainer() {
    const container = document.createElement('div');
    container.id = 'toastContainer';
    container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
    container.style.zIndex = '9999';
    document.body.appendChild(container);
    return container;
}

/**
 * Modal de confirmation (remplace window.confirm).
 * Appel : confirmModal('message', callback)
 *      ou confirmModal({ title, message, confirmText, confirmClass, icon, iconClass }, callback)
 */
function confirmModal(options, callback) {
    if (typeof options === 'string') {
        options = { message: options };
    }
    const opts = Object.assign({
        title: 'Confirmation',
        message: '',
        confirmText: 'Confirmer',
        cancelText: 'Annuler',
        confirmClass: 'btn-primary',
        icon: 'fa-question-circle',
        iconClass: 'text-primary'
    }, options || {});

    const modalEl = document.getElementById('appConfirmModal');
    if (!modalEl) {
        if (window.confirm(opts.message) && typeof callback === 'function') callback();
        return;
    }

    modalEl.querySelector('.modal-title').innerHTML =
        '<i class="fas ' + opts.icon + ' ' + opts.iconClass + ' me-2"></i>' + opts.title;
    modalEl.querySelector('.modal-message').innerHTML = opts.message;
    modalEl.querySelector('.btn-cancel').textContent = opts.cancelText;

    // Reset bouton de confirmation pour éviter le cumul de listeners
    const oldBtn = modalEl.querySelector('.btn-confirm');
    const newBtn = oldBtn.cloneNode(false);
    newBtn.className = 'btn btn-confirm ' + opts.confirmClass;
    newBtn.innerHTML = opts.confirmText;
    oldBtn.parentNode.replaceChild(newBtn, oldBtn);

    const bs = bootstrap.Modal.getOrCreateInstance(modalEl);
    newBtn.addEventListener('click', function() {
        bs.hide();
        if (typeof callback === 'function') callback();
    });
    bs.show();
}

/**
 * Modal d'information (remplace window.alert).
 * type : 'info' (défaut) | 'success' | 'warning' | 'danger'
 */
function alertModal(message, options) {
    const opts = Object.assign({
        title: 'Information',
        type: 'info'
    }, options || {});

    const map = {
        info:    { icon: 'fa-info-circle',         cls: 'text-info' },
        success: { icon: 'fa-check-circle',        cls: 'text-success' },
        warning: { icon: 'fa-exclamation-triangle', cls: 'text-warning' },
        danger:  { icon: 'fa-times-circle',         cls: 'text-danger' }
    };
    const cfg = map[opts.type] || map.info;

    const modalEl = document.getElementById('appAlertModal');
    if (!modalEl) {
        window.alert(message);
        return;
    }

    modalEl.querySelector('.modal-title').innerHTML =
        '<i class="fas ' + cfg.icon + ' ' + cfg.cls + ' me-2"></i>' + opts.title;
    modalEl.querySelector('.modal-message').innerHTML = message;

    bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

/**
 * Compat : ancienne API
 */
function confirmAction(message, callback) {
    confirmModal(message, callback);
}

/**
 * Auto-handler pour les éléments avec [data-confirm].
 * Supporte :
 *   <form data-confirm="message">
 *   <a    data-confirm="message" href="...">
 * Attributs optionnels :
 *   data-confirm-title, data-confirm-text, data-confirm-class,
 *   data-confirm-icon, data-confirm-icon-class
 */
function initConfirmHandlers() {
    document.querySelectorAll('form[data-confirm]').forEach(function(form) {
        if (form.dataset.confirmBound) return;
        form.dataset.confirmBound = '1';
        form.addEventListener('submit', function(e) {
            if (form.dataset.confirmed === '1') return;
            e.preventDefault();
            confirmModal({
                title: form.dataset.confirmTitle || 'Confirmation',
                message: form.dataset.confirm,
                confirmText: form.dataset.confirmText || 'Confirmer',
                confirmClass: form.dataset.confirmClass || 'btn-danger',
                icon: form.dataset.confirmIcon || 'fa-exclamation-triangle',
                iconClass: form.dataset.confirmIconClass || 'text-warning'
            }, function() {
                form.dataset.confirmed = '1';
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit();
                } else {
                    form.submit();
                }
            });
        });
    });

    document.querySelectorAll('a[data-confirm]').forEach(function(link) {
        if (link.dataset.confirmBound) return;
        link.dataset.confirmBound = '1';
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const href = link.getAttribute('href');
            confirmModal({
                title: link.dataset.confirmTitle || 'Confirmation',
                message: link.dataset.confirm,
                confirmText: link.dataset.confirmText || 'Confirmer',
                confirmClass: link.dataset.confirmClass || 'btn-danger',
                icon: link.dataset.confirmIcon || 'fa-exclamation-triangle',
                iconClass: link.dataset.confirmIconClass || 'text-warning'
            }, function() {
                if (href && href !== '#') window.location.href = href;
            });
        });
    });
}

/**
 * Formater un montant en FCFA
 */
function formatMoney(amount) {
    return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
}

/**
 * Formater une date
 */
function formatDate(date) {
    return new Date(date).toLocaleDateString('fr-FR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
    });
}

/**
 * Formater une heure
 */
function formatTime(time) {
    return time.substring(0, 5);
}

/**
 * Requête AJAX simplifiée
 */
async function fetchAPI(url, options = {}) {
    const defaultOptions = {
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    };

    try {
        const response = await fetch(url, { ...defaultOptions, ...options });
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Erreur serveur');
        }

        return data;
    } catch (error) {
        console.error('Erreur API:', error);
        showToast(error.message, 'danger');
        throw error;
    }
}

/**
 * Soumettre un formulaire en AJAX
 */
async function submitForm(form, successCallback) {
    const formData = new FormData(form);
    const submitBtn = form.querySelector('[type="submit"]');
    const originalText = submitBtn?.innerHTML;

    try {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Traitement...';
        }

        const response = await fetch(form.action, {
            method: form.method || 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });

        const data = await response.json();

        if (data.success) {
            showToast(data.message || 'Opération réussie', 'success');
            if (successCallback) {
                successCallback(data);
            }
        } else {
            showToast(data.message || 'Une erreur est survenue', 'danger');
        }

        return data;
    } catch (error) {
        console.error('Erreur soumission:', error);
        showToast('Erreur de connexion au serveur', 'danger');
    } finally {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    }
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Imprimer un élément
 */
function printElement(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;

    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Impression</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
            <style>
                body { padding: 20px; }
                @media print {
                    .no-print { display: none !important; }
                }
            </style>
        </head>
        <body>
            ${element.innerHTML}
            <script>window.onload = function() { window.print(); window.close(); }<\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Exposer les fonctions globalement
window.showToast = showToast;
window.confirmAction = confirmAction;
window.formatMoney = formatMoney;
window.fetchAPI = fetchAPI;
window.submitForm = submitForm;
window.debounce = debounce;
window.printElement = printElement;

// Variable globale pour l'URL de base
const BASE_URL = document.querySelector('meta[name="base-url"]')?.content || '';
