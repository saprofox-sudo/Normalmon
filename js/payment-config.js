(function () {
    var defaults = {
        merchantName: "Sadad General Trading Co",
        amount: "2000.000",
        currency: "KD",
        customerMessage: ""
    };
    var params = new URLSearchParams(window.location.search);
    var invoiceId = params.get("invoice");

    function readLocalInvoice() {
        try {
            var invoices = JSON.parse(localStorage.getItem("invoices") || "[]");
            return invoices.find(function (invoice) { return invoice.id === invoiceId; }) || null;
        } catch (error) {
            return null;
        }
    }

    function readSavedConfig() {
        try {
            return JSON.parse(localStorage.getItem("invoiceConfig") || "null") || {};
        } catch (error) {
            return {};
        }
    }

    function applyConfig(config) {
        window.invoiceConfig = config;
        var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        var node;
        var textNodes = [];

        while (node = walker.nextNode()) {
            textNodes.push(node);
        }

        textNodes.forEach(function (textNode) {
            textNode.nodeValue = textNode.nodeValue
                .replace(/555\.000/g, config.amount)
                .replace(/KWD/g, config.currency)
                .replace(/Sadad General Trading Co/g, config.merchantName);
        });
    }

    function loadInvoice() {
        var localInvoice = readLocalInvoice();
        if (localInvoice) {
            return Promise.resolve(localInvoice);
        }

        if (!invoiceId) {
            return Promise.resolve(Object.assign({}, defaults, readSavedConfig()));
        }

        return fetch("data/invoices.json?ts=" + Date.now(), { cache: "no-store" })
            .then(function (response) { return response.ok ? response.json() : []; })
            .then(function (invoices) {
                return invoices.find(function (invoice) { return invoice.id === invoiceId; }) || Object.assign({}, defaults, readSavedConfig());
            })
            .catch(function () { return Object.assign({}, defaults, readSavedConfig()); });
    }

    document.addEventListener("DOMContentLoaded", function () {
        loadInvoice().then(function (invoice) {
            if (invoice.active === false) {
                window.location.replace("payment-return.html?invoice=" + encodeURIComponent(invoiceId || ""));
                return;
            }
            applyConfig(Object.assign({}, defaults, invoice));
        });
    });
})();
