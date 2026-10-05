document.addEventListener('DOMContentLoaded', () => {
    const toggle = document.querySelector('.mobile-nav-toggle');
    const nav = document.querySelector('.main-nav');
    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = nav.classList.toggle('is-open');
            toggle.setAttribute('aria-expanded', String(open));
        });
    }

    const ordersLinks = Array.from(document.querySelectorAll('.main-nav a')).filter(link => {
        const text = link.textContent?.trim().toLowerCase();
        return text === 'pedidos';
    });

    if (ordersLinks.length) {
        const style = document.createElement('style');
        style.textContent = `
            .nav-pending-badge{display:inline-grid;place-items:center;min-width:22px;height:22px;padding:0 6px;margin-left:6px;border-radius:999px;background:#f6d96c;color:#5b4311;font-size:.68rem;font-weight:900;line-height:1;vertical-align:middle}
            .nav-pending-badge[hidden]{display:none}
        `;
        document.head.appendChild(style);

        ordersLinks.forEach(link => {
            if (!link.querySelector('.nav-pending-badge')) {
                const badge = document.createElement('span');
                badge.className = 'nav-pending-badge';
                badge.hidden = true;
                badge.setAttribute('aria-label', 'Pedidos pendientes nuevos');
                link.appendChild(badge);
            }
        });

        function updatePendingBadges(data) {
            const count = Number(data?.count || 0);
            document.querySelectorAll('.nav-pending-badge').forEach(badge => {
                badge.textContent = count > 99 ? '99+' : String(count);
                badge.hidden = count === 0;
                badge.setAttribute('aria-label', `${count} ${count === 1 ? 'pedido pendiente nuevo' : 'pedidos pendientes nuevos'}`);
            });
        }

        async function refreshPendingBadge() {
            try {
                const response = await fetch('/admin/orders/pending', {
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    cache: 'no-store'
                });
                if (!response.ok) return;
                updatePendingBadges(await response.json());
            } catch (error) {
                console.warn('No se pudo actualizar el contador de pedidos.', error);
            }
        }

        if (window.mekatosPendingPoller) {
            // La página ya consulta /admin/orders/pending (p. ej. la lista de pedidos):
            // se reutilizan sus respuestas para no duplicar las peticiones.
            document.addEventListener('mekatos:pending-updated', event => updatePendingBadges(event.detail));
            if (window.mekatosPendingSnapshot) updatePendingBadges(window.mekatosPendingSnapshot);
        } else {
            refreshPendingBadge();
            window.setInterval(refreshPendingBadge, 5000);
        }
    }
});
