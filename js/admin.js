(function () {
    var defaults = {
        merchantName: "Sadad General Trading Co",
        amount: "2000.000",
        currency: "KD",
        customerMessage: ""
    };
    var form = document.getElementById("invoiceSettings");
    var feedback = document.getElementById("feedback");
    var invoiceList = document.getElementById("invoiceList");
    var invoiceCount = document.getElementById("invoiceCount");
    var emptyState = document.getElementById("emptyState");
    var saveButton = document.getElementById("saveButton");
    var cancelEdit = document.getElementById("cancelEdit");
    var exportInvoices = document.getElementById("exportInvoices");
    var importInvoices = document.getElementById("importInvoices");
    var editingId = null;

    function getConfig() {
        try {
            return Object.assign({}, defaults, JSON.parse(localStorage.getItem("invoiceConfig") || "null") || {});
        } catch (error) {
            return Object.assign({}, defaults);
        }
    }

    function getInvoices() {
        try {
            return JSON.parse(localStorage.getItem("invoices") || "[]");
        } catch (error) {
            return [];
        }
    }

    function loadFileInvoices() {
        if (localStorage.getItem("invoices")) {
            return Promise.resolve();
        }
        return fetch("data/invoices.json")
            .then(function (response) { return response.ok ? response.json() : []; })
            .then(function (invoices) {
                if (Array.isArray(invoices) && invoices.length) {
                    saveInvoices(invoices);
                }
            })
            .catch(function () {});
    }

    function downloadInvoices() {
        var file = new Blob([JSON.stringify(getInvoices(), null, 2)], { type: "application/json" });
        var link = document.createElement("a");
        link.href = URL.createObjectURL(file);
        link.download = "invoices.json";
        link.click();
        URL.revokeObjectURL(link.href);
    }

    function saveInvoices(invoices) {
        localStorage.setItem("invoices", JSON.stringify(invoices));
    }

    function makeId() {
        return "INV-" + Date.now().toString(36).toUpperCase() + "-" + Math.random().toString(36).slice(2, 6).toUpperCase();
    }

    function formatDate(value) {
        var date = value ? new Date(value) : new Date();
        if (Number.isNaN(date.getTime())) {
            date = new Date();
        }
        return new Intl.DateTimeFormat("ar", { dateStyle: "medium", timeStyle: "short" }).format(date);
    }

    function invoiceLink(invoice) {
        return new URL("page14632.html?invoice=" + encodeURIComponent(invoice.id), window.location.href).href;
    }

    function render(config) {
        document.getElementById("merchantName").value = config.merchantName;
        document.getElementById("amount").value = config.amount;
        document.getElementById("currency").value = config.currency;
        document.getElementById("customerMessage").value = config.customerMessage;
        document.getElementById("previewMerchant").textContent = config.merchantName;
        document.getElementById("previewAmount").textContent = config.amount;
        document.getElementById("previewCurrency").textContent = config.currency;
        document.getElementById("previewMessage").textContent = config.customerMessage;
        document.getElementById("previewMessage").hidden = !config.customerMessage;
    }

    function renderInvoices() {
        var invoices = getInvoices();
        invoiceCount.textContent = invoices.length + " فواتير";
        emptyState.hidden = invoices.length > 0;
        invoiceList.innerHTML = invoices.map(function (invoice) {
            var isActive = invoice.active !== false;
            return '<article class="invoice-item">' +
                '<div class="invoice-item-main"><span class="invoice-id">' + invoice.id + '</span>' +
                '<h3>' + escapeHtml(invoice.merchantName) + '</h3>' +
                '<p>' + escapeHtml(invoice.amount + " " + invoice.currency) + ' <span>•</span> ' + formatDate(invoice.createdAt) + '</p></div>' +
                '<div class="invoice-item-actions"><button class="icon-action open-action" data-id="' + invoice.id + '" type="button">فتح الرابط</button>' +
                '<button class="icon-action copy-action" data-id="' + invoice.id + '" type="button">نسخ الرابط</button>' +
                '<button class="icon-action toggle-action" data-id="' + invoice.id + '" type="button">' + (isActive ? 'إيقاف' : 'تشغيل') + '</button>' +
                '<button class="icon-action edit-action" data-id="' + invoice.id + '" type="button">تعديل</button>' +
                '<button class="icon-action delete-action" data-id="' + invoice.id + '" type="button">حذف</button></div>' +
                '</article>';
        }).join("");
    }

    function escapeHtml(value) {
        return String(value).replace(/[&<>'"]/g, function (character) {
            return { "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" }[character];
        });
    }

    function startEdit(invoice) {
        editingId = invoice.id;
        render(invoice);
        saveButton.querySelector("span").textContent = "حفظ التعديلات";
        cancelEdit.hidden = false;
        form.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    function resetForm() {
        editingId = null;
        render(getConfig());
        saveButton.querySelector("span").textContent = "إضافة فاتورة";
        cancelEdit.hidden = true;
    }

    render(getConfig());

    form.addEventListener("input", function () {
        render({
            merchantName: form.merchantName.value,
            amount: form.amount.value,
            currency: form.currency.value,
            customerMessage: form.customerMessage.value
        });
    });

    form.addEventListener("submit", function (event) {
        event.preventDefault();
        if (!form.reportValidity()) {
            return;
        }
        var amount = Number(form.amount.value);
        if (!Number.isFinite(amount) || amount <= 0) {
            form.amount.setCustomValidity("أدخل مبلغًا صحيحًا");
            form.amount.reportValidity();
            form.amount.setCustomValidity("");
            return;
        }
        var config = {
            merchantName: form.merchantName.value.trim(),
            amount: amount.toFixed(3),
            currency: form.currency.value,
            customerMessage: form.customerMessage.value.trim()
        };
        var invoices = getInvoices();
        if (editingId) {
            invoices = invoices.map(function (invoice) {
                return invoice.id === editingId ? Object.assign({}, invoice, config, { updatedAt: new Date().toISOString() }) : invoice;
            });
        } else {
            invoices.unshift(Object.assign({}, config, { id: makeId(), createdAt: new Date().toISOString() }));
        }
        saveInvoices(invoices);
        localStorage.setItem("invoiceConfig", JSON.stringify(config));
        render(config);
        renderInvoices();
        feedback.textContent = editingId ? "تم تعديل الفاتورة بنجاح" : "تمت إضافة الفاتورة بنجاح";
        feedback.className = "feedback success";
        resetForm();
        window.setTimeout(function () { feedback.textContent = ""; }, 3000);
    });

    cancelEdit.addEventListener("click", resetForm);

    invoiceList.addEventListener("click", function (event) {
        var button = event.target.closest("button[data-id]");
        if (!button) {
            return;
        }
        var id = button.getAttribute("data-id");
        var invoices = getInvoices();
        var invoice = invoices.find(function (item) { return item.id === id; });
        if (!invoice) {
            return;
        }
        if (button.classList.contains("open-action")) {
            window.open(invoiceLink(invoice), "_blank");
        } else if (button.classList.contains("copy-action")) {
            navigator.clipboard.writeText(invoiceLink(invoice)).then(function () {
                feedback.textContent = "تم نسخ رابط الفاتورة";
                feedback.className = "feedback success";
            });
        } else if (button.classList.contains("toggle-action")) {
            invoice.active = invoice.active === false;
            saveInvoices(invoices);
            renderInvoices();
            feedback.textContent = invoice.active ? "تم تشغيل الفاتورة" : "تم إيقاف الفاتورة";
            feedback.className = "feedback success";
        } else if (button.classList.contains("edit-action")) {
            startEdit(invoice);
        } else if (button.classList.contains("delete-action") && window.confirm("هل تريد حذف هذه الفاتورة؟")) {
            saveInvoices(invoices.filter(function (item) { return item.id !== id; }));
            if (editingId === id) {
                resetForm();
            }
            renderInvoices();
        }
    });

    exportInvoices.addEventListener("click", downloadInvoices);
    importInvoices.addEventListener("change", function () {
        var file = importInvoices.files[0];
        if (!file) {
            return;
        }
        var reader = new FileReader();
        reader.onload = function () {
            try {
                var invoices = JSON.parse(reader.result);
                if (!Array.isArray(invoices)) {
                    throw new Error("Invalid invoice file");
                }
                saveInvoices(invoices);
                renderInvoices();
                feedback.textContent = "تم استيراد ملف الفواتير";
                feedback.className = "feedback success";
            } catch (error) {
                feedback.textContent = "ملف الفواتير غير صالح";
                feedback.className = "feedback";
            }
            importInvoices.value = "";
        };
        reader.readAsText(file);
    });

    loadFileInvoices().then(renderInvoices);
})();
