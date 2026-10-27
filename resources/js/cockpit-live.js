/** Cockpit exécutif live — drill-down ApexCharts + polling / WebSocket (P3-7). */

const nf = new Intl.NumberFormat('fr-FR');

function formatMoney(v) {
    return nf.format(Math.round(v)) + ' XAF';
}

function initCockpitLive(root) {
    if (!root) return;

    const exerciceId = root.dataset.exerciceId;
    const snapshotUrl = root.dataset.snapshotUrl;
    const drilldownUrl = root.dataset.drilldownUrl;
    const pingUrl = root.dataset.pingUrl;
    const pollSeconds = parseInt(root.dataset.pollSeconds || '15', 10);
    let cacheVersion = parseInt(root.dataset.cacheVersion || '0', 10);

    const badge = document.getElementById('cockpit-live-badge');
    const panel = document.getElementById('cockpit-drill-panel');
    const panelTitle = document.getElementById('cockpit-drill-title');
    const panelBody = document.getElementById('cockpit-drill-body');

    const setLive = (on) => {
        if (!badge) return;
        badge.classList.toggle('bg-success', on);
        badge.classList.toggle('bg-secondary', !on);
        badge.textContent = on ? 'Live' : 'Sync…';
    };

    // Formats français alignés sur le rendu serveur : « 40,3 Md », « 68,3 M », « 0,2 % ».
    const compact = (v) => {
        const a = Math.abs(v ?? 0);
        const un = (x) => x.toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
        return a >= 1e9 ? `${un(v / 1e9)} Md` : (a >= 1e6 ? `${un(v / 1e6)} M` : nf.format(Math.round(v ?? 0)));
    };
    const pct = (v) => `${(v ?? 0).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 })} %`;

    const updateKpis = (data) => {
        const g = data.global || {};
        const map = {
            'kpi-ouvert': compact(g.alloue ?? g.ouvert),
            'kpi-engage': compact(g.engage),
            'kpi-engage-detail': `${pct(g.taux_engagement)} du crédit alloué`,
            'kpi-paye': compact(g.paye),
            'kpi-paye-detail': `exécution ${pct(g.taux_execution)} du crédit`,
            'kpi-reste-payer': compact(g.reste_a_payer),
            'kpi-pap-physique': pct(data.pap?.physique),
        };
        if (g.disponible !== null && g.disponible !== undefined) map['kpi-disponible'] = compact(g.disponible);
        (data.chaine || []).forEach((etape, i) => {
            map[`ck-chaine-${i}`] = compact(etape.montant);
            map[`ck-chaine-taux-${i}`] = pct(etape.taux);
        });
        Object.entries(map).forEach(([id, val]) => {
            const el = document.getElementById(id);
            if (el) el.textContent = val;
        });
    };

    const refreshCharts = (data) => {
        const charts = window.__cockpitCharts || {};
        const series = data.series || {};
        if (charts.cCat && series.categories) {
            charts.cCat.updateSeries(series.categories.map((c) => c.ouvert));
            charts.cCat.updateOptions({ labels: series.categories.map((c) => c.label) });
        }
        if (charts.cAxe && series.axes) {
            charts.cAxe.updateSeries([
                { name: 'Ouvert', data: series.axes.map((a) => a.ouvert) },
                { name: 'Payé', data: series.axes.map((a) => a.paye) },
            ]);
            charts.cAxe.updateOptions({ xaxis: { categories: series.axes.map((a) => a.label) } });
        }
        if (charts.cStruct && series.structures) {
            charts.cStruct.updateSeries([
                { name: 'Ouvert', data: series.structures.map((s) => s.ouvert) },
                { name: 'Payé', data: series.structures.map((s) => s.paye) },
            ]);
            charts.cStruct.updateOptions({ xaxis: { categories: series.structures.map((s) => s.label) } });
        }
    };

    const fetchSnapshot = async () => {
        const res = await fetch(`${snapshotUrl}?exercice=${exerciceId}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) return null;
        const json = await res.json();
        return json.data;
    };

    const applySnapshot = (data) => {
        if (!data) return;
        cacheVersion = data.cache_version ?? cacheVersion;
        updateKpis(data);
        refreshCharts(data);
        setLive(true);
    };

    const showDrill = async (niveau, parent, title) => {
        if (!panel || !panelBody) return;
        panelTitle.textContent = title || 'Détail';
        panelBody.innerHTML = '<div class="text-muted small">Chargement…</div>';
        panel.classList.remove('d-none');

        const params = new URLSearchParams({ exercice: exerciceId, niveau, parent });
        const res = await fetch(`${drilldownUrl}?${params}`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            panelBody.innerHTML = '<div class="text-danger small">Impossible de charger le détail.</div>';
            return;
        }
        const json = await res.json();
        const lignes = json.data?.lignes || [];
        if (!lignes.length) {
            panelBody.innerHTML = '<div class="text-muted small">Aucune donnée pour ce niveau.</div>';
            return;
        }
        const rows = lignes.map((r) => {
            const label = r.intitule || r.libelle || r.label || r.code || '—';
            const ouvert = r.ouvert ?? r.budget_prevu ?? 0;
            const paye = r.paye ?? r.budget_paye ?? 0;
            return `<tr><td>${label}</td><td class="text-end">${formatMoney(ouvert)}</td><td class="text-end">${formatMoney(paye)}</td></tr>`;
        }).join('');
        panelBody.innerHTML = `<div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><th>Libellé</th><th class="text-end">Ouvert</th><th class="text-end">Payé</th></tr></thead><tbody>${rows}</tbody></table></div>`;
    };

    window.cockpitDrill = showDrill;

    const poll = async () => {
        try {
            const res = await fetch(`${pingUrl}?since=${cacheVersion}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            if (!res.ok) return;
            const json = await res.json();
            if (json.data?.changed) {
                setLive(false);
                applySnapshot(await fetchSnapshot());
            }
        } catch (_) {
            setLive(false);
        }
    };

    if (window.Echo && root.dataset.broadcastChannel) {
        window.Echo.private(root.dataset.broadcastChannel)
            .listen('.metrics.updated', (payload) => {
                if (payload.cache_version > cacheVersion) {
                    setLive(false);
                    fetchSnapshot().then(applySnapshot);
                }
            });
    }

    setInterval(poll, Math.max(5, pollSeconds) * 1000);
}

document.addEventListener('DOMContentLoaded', () => {
    initCockpitLive(document.getElementById('cockpit-live-root'));
});

export { initCockpitLive };
