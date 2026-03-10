/**
 * =============================================================
 * WCR Digital Signage – Shared JavaScript Utilities
 * =============================================================
 * DATEI:    wcr-ds-utils.js
 * ABLAGE:   /wp-content/plugins/wcr-digital-signage/assets/js/wcr-ds-utils.js
 * =============================================================
 */

window.WCR = window.WCR || {};

WCR.fmtPreis = function(v) {
    if (v === null || v === undefined || v === '') return '';
    return parseFloat(v).toFixed(2).replace('.', ',');
};

WCR.byTyp = function(items, typ) {
    return items.filter(function(i) {
        return (i.typ || '').toLowerCase().trim() === typ;
    });
};

WCR.capitalize = function(str) {
    return str.split('-').map(function(w) {
        return w.charAt(0).toUpperCase() + w.slice(1);
    }).join('-');
};

WCR.showError = function(el, msg) {
    el.innerHTML = '<div style="color:#dc2626;font-size:0.9rem;padding:20px 0">⚠ ' + msg + '</div>';
};

WCR.fetchPreise = async function(endpoint) {
    try {
        const res  = await fetch(endpoint);
        const data = await res.json();
        const map  = {};
        data.forEach(function(d) { map[d.nummer] = d.preis; });
        return map;
    } catch(e) {
        console.warn('[WCR] Preise konnten nicht geladen werden:', e);
        return {};
    }
};

WCR.renderDrinksList = function(containerId, columns, apiEndpoint, gruppenEndpoint) {
    var wrap = document.getElementById(containerId);
    if (!wrap) return;

    var colClasses = ['list_one', 'list_two', 'list_tree'];

    wrap.innerHTML =
        '<div class="cols-wrap">' +
        columns.map(function(col, i) {
            return '<div class="' + colClasses[i] + '">' +
                   '<div id="ds-col-' + i + '"></div>' +
                   '</div>';
        }).join('') +
        '</div>';

    var promises = [fetch(apiEndpoint).then(function(r) { return r.json(); })];
    if (gruppenEndpoint) {
        promises.push(fetch(gruppenEndpoint).then(function(r) { return r.json(); }));
    }

    Promise.all(promises)
        .then(function(results) {
            var items   = results[0];
            var gruppen = results[1] || [];

            var gruppenMap = {};
            gruppen.forEach(function(g) {
                gruppenMap[g.typ.toLowerCase()] = parseInt(g.aktiv) === 1;
            });

            columns.forEach(function(col, ci) {
                var container = document.getElementById('ds-col-' + ci);
                var html      = '';

                col.types.forEach(function(typ) {
                    var typKey   = typ.toLowerCase();
                    var istOffen = gruppenMap.hasOwnProperty(typKey) ? gruppenMap[typKey] : true;

                    if (!istOffen) {
                        html += '<h3 class="ds-subhead">' + WCR.capitalize(typ) +
                                ' <span class="food-kat-badge">Geschlossen</span>' +
                                '</h3><hr class="ds-hr">';
                        return;
                    }

                    var filtered = WCR.byTyp(items, typ);
                    if (!filtered.length) return;

                    html += '<h3 class="ds-subhead">' + WCR.capitalize(typ) + '</h3>' +
                            '<hr class="ds-hr">' +
                            '<table class="ds-table">' +
                            filtered.map(function(item) {
                                return '<tr>' +
                                       '<td class="produkt">' + item.produkt + '</td>' +
                                       '<td class="preis">' + WCR.fmtPreis(item.preis) +
                                       '<span class="euro">&nbsp;€</span></td>' +
                                       '</tr>';
                            }).join('') +
                            '</table>';
                });

                container.innerHTML = html || '<p class="ds-empty">–</p>';
            });
        })
        .catch(function() {
            WCR.showError(wrap, 'Daten konnten nicht geladen werden.');
        });
};
