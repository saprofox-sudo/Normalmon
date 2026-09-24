(function () {
    var defaults = {
        merchantName: "Sadad General Trading Co",
        amount: "2000.000",
        currency: "KD",
        customerMessage: ""
    };
    var params = new URLSearchParams(window.location.search);
    var invoiceId = params.get("invoice");
    var payButton = document.getElementById("payButton");
    var paymentLoading = document.getElementById("paymentLoading");

    function readLocalInvoice() {
        try {
            var invoices = JSON.parse(localStorage.getItem("invoices") || "[]");
            var selected = invoices.find(function (invoice) { return invoice.id === invoiceId; });
            if (selected) {
                return selected;
            }
            return Object.assign({}, defaults, JSON.parse(localStorage.getItem("invoiceConfig") || "null") || {});
        } catch (error) {
            return Object.assign({}, defaults);
        }
    }

    function readInvoice() {
        var localInvoice = readLocalInvoice();
        if (localInvoice.id === invoiceId) {
            return Promise.resolve(localInvoice);
        }
        return fetch("data/invoices.json?ts=" + Date.now(), { cache: "no-store" })
            .then(function (response) { return response.ok ? response.json() : []; })
            .then(function (invoices) {
                return invoices.find(function (invoice) { return invoice.id === invoiceId; }) || localInvoice;
            })
            .catch(function () { return localInvoice; });
    }

    function formatDate(value) {
        var date = value ? new Date(value) : new Date();
        return new Intl.DateTimeFormat("en-GB", {
            day: "numeric",
            month: "long",
            year: "numeric",
            hour: "numeric",
            minute: "2-digit",
            second: "2-digit",
            hour12: true
        }).format(date);
    }

    readInvoice().then(function (invoice) {
        if (invoice.active === false) {
                window.location.replace("payment-return.html?invoice=" + encodeURIComponent(invoiceId || ""));
            return;
        }
        document.getElementById("invoiceDate").textContent = formatDate(invoice.createdAt);
        document.getElementById("merchantName").textContent = invoice.merchantName;
        document.getElementById("currency").textContent = invoice.currency;
        document.getElementById("amount").textContent = invoice.amount;
        payButton.href = "payment.html?invoice=" + encodeURIComponent(invoiceId || "") + "&pay=1";

        if (invoice.customerMessage) {
            var message = document.getElementById("customerMessage");
            message.textContent = invoice.customerMessage;
            message.hidden = false;
        }
    });

    payButton.addEventListener("click", function (event) {
        event.preventDefault();
        if (payButton.classList.contains("is-loading")) {
            return;
        }
        payButton.classList.add("is-loading");
        paymentLoading.classList.add("is-visible");
        paymentLoading.setAttribute("aria-hidden", "false");
        var loadingDuration = 300;
        window.setTimeout(function () {
            window.location.replace(payButton.href);
        }, loadingDuration);
    });

    document.getElementById("rejectButton").addEventListener("click", function () {
        var feedback = document.getElementById("feedback");
        feedback.textContent = "تم رفض طلب الدفع";
        feedback.style.color = "#d0072f";
    });
})();
