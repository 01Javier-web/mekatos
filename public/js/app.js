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
            .app-live-clock{display:inline-flex;align-items:center;gap:5px;margin-left:10px;padding:6px 9px;border:1px solid rgba(255,255,255,.25);border-radius:8px;color:#fff;font-size:.72rem;font-weight:750;white-space:nowrap}
            .app-live-clock strong{color:#fff}
            @media(max-width:760px){.app-live-clock{display:none}}
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

        async function refreshPendingBadge() {
            try {
                const response = await fetch('/admin/orders/pending', {
                    headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                    cache: 'no-store'
                });
                if (!response.ok) return;
                const data = await response.json();
                const count = Number(data.count || 0);
                document.querySelectorAll('.nav-pending-badge').forEach(badge => {
                    badge.textContent = count > 99 ? '99+' : String(count);
                    badge.hidden = count === 0;
                    badge.setAttribute('aria-label', `${count} ${count === 1 ? 'pedido pendiente nuevo' : 'pedidos pendientes nuevos'}`);
                });
            } catch (error) {
                console.warn('No se pudo actualizar el contador de pedidos.', error);
            }
        }

        refreshPendingBadge();
        window.setInterval(refreshPendingBadge, 5000);
    }

    const headerInner = document.querySelector('.header-inner');
    if (headerInner && !document.getElementById('app-live-clock')) {
        const clock = document.createElement('span');
        clock.className = 'app-live-clock';
        clock.id = 'app-live-clock';
        clock.setAttribute('aria-label', 'Hora actual de Colombia');
        headerInner.insertBefore(clock, headerInner.querySelector('.user-menu') || null);

        const formatter = new Intl.DateTimeFormat('es-CO', {
            timeZone: 'America/Bogota',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        });

        const updateClock = () => {
            clock.innerHTML = `🕐 <strong>${formatter.format(new Date())}</strong>`;
        };

        updateClock();
        window.setInterval(updateClock, 1000);
    }
});
