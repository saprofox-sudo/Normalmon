(function () {
    var defaults = {
        merchantName: "Sadad General Trading Co",
        amount: "2000.000",
        currency: "KD",
        customerMessage: ""
    };

    function readConfig() {
        try {
            var invoiceId = new URLSearchParams(window.location.search).get("invoice");
            var invoices = JSON.parse(localStorage.getItem("invoices") || "[]");
            var selected = invoices.find(function (invoice) { return invoice.id === invoiceId; });
            if (selected) {
                return selected;
            }
            var saved = JSON.parse(localStorage.getItem("invoiceConfig") || "null");
            return Object.assign({}, defaults, saved || {});
        } catch (error) {
            return Object.assign({}, defaults);
        }
    }

    window.invoiceConfig = readConfig();

    function loadHostedInvoice() {
        var invoiceId = new URLSearchParams(window.location.search).get("invoice");
        var localInvoices = [];
        try {
            localInvoices = JSON.parse(localStorage.getItem("invoices") || "[]");
        } catch (error) {}
        if (!invoiceId || localInvoices.some(function (invoice) { return invoice.id === invoiceId; })) {
            return Promise.resolve();
        }
        return fetch("data/invoices.json?ts=" + Date.now(), { cache: "no-store" })
            .then(function (response) { return response.ok ? response.json() : []; })
            .then(function (invoices) {
                var selected = invoices.find(function (invoice) { return invoice.id === invoiceId; });
                if (selected) {
                    window.invoiceConfig = selected;
                }
            })
            .catch(function () {});
    }

    window.applyInvoiceConfig = function () {
        var config = window.invoiceConfig;
        document.querySelectorAll("img.logoHead-mob").forEach(function (logo) {
            logo.src = "images/logo.jpg";
        });
        var walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        var textNode;
        var nodes = [];

        while (textNode = walker.nextNode()) {
            nodes.push(textNode);
        }

        nodes.forEach(function (node) {
            if (node.nodeValue.indexOf("2000.000") !== -1) {
                node.nodeValue = node.nodeValue.replace(/2000\.000/g, config.amount);
            }
            if (node.nodeValue.indexOf(defaults.merchantName) !== -1) {
                node.nodeValue = node.nodeValue.replace(new RegExp(defaults.merchantName, "g"), config.merchantName);
            }
            if (node.nodeValue.indexOf("KD") !== -1) {
                node.nodeValue = node.nodeValue.replace(/KD/g, config.currency);
            }
        });

        var message = document.getElementById("invoiceCustomerMessage");
        if (message) {
            message.textContent = config.customerMessage;
            message.style.display = config.customerMessage ? "block" : "none";
        }
    };

    document.addEventListener("DOMContentLoaded", function () {
        loadHostedInvoice().then(window.applyInvoiceConfig);
    });
})();
