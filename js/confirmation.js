(function () {
    var defaults = {
        merchantName: "Sadad General Trading Co",
        amount: "2000.000",
        currency: "KD",
        customerMessage: ""
    };
    var invoiceId = new URLSearchParams(window.location.search).get("invoice");
    var confirmationCode = document.getElementById("confirmationCode");
    var confirmButton = document.getElementById("confirmButton");
    var cancelButton = document.getElementById("cancelButton");
    var otpError = document.getElementById("otpError");
    var remainingAttempts = 3;
    var remainingSeconds = 4 * 60;

    function updateCountdown() {
        var minutes = Math.floor(remainingSeconds / 60);
        var seconds = remainingSeconds % 60;
        var formattedTime = String(minutes).padStart(2, "0") + ":" + String(seconds).padStart(2, "0");
        confirmationCode.placeholder = "Timeout in: " + formattedTime;
        if (remainingSeconds > 0) {
            remainingSeconds -= 1;
        }
    }

    updateCountdown();
    window.setInterval(updateCountdown, 1000);

    function readPaymentDetails() {
        try {
            return JSON.parse(sessionStorage.getItem("paymentConfirmation") || "null") || {};
        } catch (error) {
            return {};
        }
    }

    function readLocalInvoice() {
        try {
            var invoices = JSON.parse(localStorage.getItem("invoices") || "[]");
            return invoices.find(function (invoice) { return invoice.id === invoiceId; }) || null;
        } catch (error) {
            return null;
        }
    }

    function readInvoice() {
        var localInvoice = readLocalInvoice();
        if (localInvoice || !invoiceId) {
            return Promise.resolve(localInvoice || defaults);
        }
        return fetch("data/invoices.json?ts=" + Date.now(), { cache: "no-store" })
            .then(function (response) { return response.ok ? response.json() : []; })
            .then(function (invoices) {
                return invoices.find(function (invoice) { return invoice.id === invoiceId; }) || defaults;
            })
            .catch(function () { return defaults; });
    }

    readInvoice().then(function (invoice) {
        var paymentDetails = readPaymentDetails();
        document.getElementById("confirmationMerchant").textContent = invoice.merchantName;
        document.getElementById("confirmationCurrency").textContent = invoice.currency;
        document.getElementById("confirmationAmount").textContent = invoice.amount;
        if (paymentDetails.cardNumber) {
            document.getElementById("confirmationCard").textContent = paymentDetails.cardNumber;
        }
        if (paymentDetails.expiry) {
            var expiryParts = paymentDetails.expiry.split("/");
            document.getElementById("confirmationExpiryMonth").textContent = expiryParts[0].trim();
            document.getElementById("confirmationExpiryYear").textContent = expiryParts[1].trim();
        }

        document.getElementById("confirmationPin").textContent = "****";
    });

    confirmationCode.addEventListener("input", function () {
        confirmationCode.value = confirmationCode.value.replace(/\D/g, "").slice(0, 6);
        confirmButton.disabled = confirmationCode.value.length !== 6;
    });

    confirmButton.addEventListener("click", function () {
        remainingAttempts -= 1;
        otpError.hidden = false;
        if (remainingAttempts > 0) {
            otpError.textContent = "OTP غير صحيح\nباقي " + remainingAttempts + " محاولات فقط";
        } else {
            otpError.textContent = "OTP غير صحيح\nتم استنفاد المحاولات المتاحة";
            confirmationCode.disabled = true;
            confirmButton.disabled = true;
            window.setTimeout(function () {
                window.location.replace("payment-return.html?invoice=" + encodeURIComponent(invoiceId || ""));
            }, 500);
        }
        confirmationCode.value = "";
    });

    cancelButton.addEventListener("click", function () {
        confirmationCode.disabled = true;
        confirmButton.disabled = true;
    });
})();