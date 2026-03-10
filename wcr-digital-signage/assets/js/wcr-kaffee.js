/* =======================================================
   WCR Kaffeekarte – wcr-kaffee.js
   Laedt Preise via REST API, rendert 10 Kaffee-Karten
======================================================= */
(function () {

    var BASE = 'https://wcr-webpage.de/be/img/drinks/kaffee/';

    var ITEMS = [
        { n: 'Americano',        img: BASE + 'americano.png',  id: 51  },
        { n: 'Americano Grande', img: BASE + 'americano.png',  id: 109 },
        { n: 'Cappuccino',       img: BASE + 'cappuccino.png', id: 52  },
        { n: 'Latte Macchiato',  img: BASE + 'Latte.png',      id: 56  },
        { n: 'Espresso',         img: BASE + 'espresso.png',   id: 55  },
        { n: 'Matcha Latte',     img: BASE + 'chocolate.png',  id: 122 },
        { n: 'Tonic Coffee',     img: BASE + 'irish.png',      id: 58  },
        { n: 'Iced Cappuccino',  img: BASE + 'cappuccino.png', id: 108 },
        { n: 'Iced Matcha',      img: BASE + 'mocha.png',      id: 123 },
        { n: 'Flat White',       img: BASE + 'macchiato.png',  id: 124 }
    ];

    async function init() {
        var grid = document.getElementById('kaffee-grid');
        if (!grid) return;

        var prices = {};
        try {
            var res  = await fetch('/wp-json/wakecamp/v1/drinks');
            var data = await res.json();
            // FIX: War item.id – muss item.nummer sein (so liefert die REST API das Feld)
            data.forEach(function(item) {
                prices[item.nummer] = item.preis;
            });
        } catch(e) {
            console.warn('Preise konnten nicht geladen werden:', e);
        }

        grid.innerHTML = ITEMS.map(function(item) {
            var p = prices[item.id];
            var priceHtml = '';
            if (p !== undefined && p !== null) {
                var formatted = parseFloat(p).toFixed(2).replace('.', ',');
                priceHtml = '<div class="k-price">' + formatted + '<span class="k-euro"> \u20AC</span></div>';
            }
            return '<div class="ds-card k-card">'
                 + '<div class="k-img-wrap"><img src="' + item.img + '" alt="' + item.n + '" loading="lazy"></div>'
                 + '<div class="k-name">' + item.n + '</div>'
                 + priceHtml
                 + '</div>';
        }).join('');
    }

    document.addEventListener('DOMContentLoaded', init);

})();
