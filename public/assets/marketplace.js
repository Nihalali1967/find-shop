/* ==========================================================================
   Material / Space — plain browser JavaScript (no bundler, no framework)
   All DOM text is inserted with textContent. Never innerHTML for user data.
   ========================================================================== */
(function () {
    "use strict";

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.prototype.slice.call((root || document).querySelectorAll(sel));

    function token() {
        const meta = $('meta[name="csrf-token"]');
        return meta ? meta.getAttribute("content") : "";
    }

    /* ------------------------------------------------------------- toasts */

    function toast(message, isError) {
        let stack = $(".toast-stack");
        if (!stack) {
            stack = document.createElement("div");
            stack.className = "toast-stack";
            stack.setAttribute("role", "status");
            document.body.appendChild(stack);
        }
        const el = document.createElement("div");
        el.className = "toast" + (isError ? " is-error" : "");
        el.textContent = message;
        stack.appendChild(el);
        window.setTimeout(() => el.remove(), 5200);
    }

    window.Marketplace = { toast };

    /* --------------------------------------------------- auto-filter forms */

    function initFilterForms() {
        $$("form[data-filter-form]").forEach((form) => {
            $$("select[data-autosubmit], input[data-autosubmit]", form).forEach((el) => {
                el.addEventListener("change", () => form.submit());
            });
            $$("input[type=checkbox][data-autosubmit]", form).forEach((el) => {
                el.addEventListener("change", () => form.submit());
            });

            const category = $("[data-category-select]", form);
            const subcategory = $("[data-subcategory-select]", form);

            if (category && subcategory) {
                category.addEventListener("change", () => {
                    // Changing category clears an incompatible subcategory before submit.
                    subcategory.value = "";
                    filterSubcategories(category, subcategory);
                    form.submit();
                });
                filterSubcategories(category, subcategory);
            }
        });
    }

    function subcategoryMap(select) {
        try {
            return JSON.parse(select.getAttribute("data-map") || "{}");
        } catch (e) {
            return {};
        }
    }

    function filterSubcategories(category, subcategory) {
        const map = subcategoryMap(subcategory);
        const current = subcategory.value;
        const options = Array.prototype.slice.call(subcategory.options);
        const allowed = map[String(category.value)] || null;

        options.forEach((opt) => {
            if (!opt.value) return;
            const show = !allowed || allowed.indexOf(Number(opt.value)) !== -1;
            opt.hidden = !show;
            opt.disabled = !show;
        });

        if (current && subcategory.options[subcategory.selectedIndex] &&
            subcategory.options[subcategory.selectedIndex].disabled) {
            subcategory.value = "";
        }
    }

    function initChips() {
        $$(".chip input[type=checkbox]").forEach((input) => {
            const chip = input.closest(".chip");
            if (!chip) return;
            const sync = () => chip.classList.toggle("is-on", input.checked);
            input.addEventListener("change", sync);
            sync();
        });
    }

    /* --------------------------------------------------- shop name checker */

    function initNameCheck() {
        const input = $("[data-name-check]");
        if (!input) return;

        const url = input.getAttribute("data-check-url");
        const output = $("[data-name-check-result]");
        let timer = null;
        let last = "";

        input.addEventListener("input", () => {
            const value = input.value.trim();
            if (value === last) return;
            last = value;
            window.clearTimeout(timer);
            if (output) {
                output.textContent = value.length < 3 ? "" : "Checking availability…";
                output.className = "hint";
            }
            if (value.length < 3) return;

            timer = window.setTimeout(() => {
                fetch(url + "?name=" + encodeURIComponent(value), {
                    headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" },
                })
                    .then((r) => r.json())
                    .then((json) => {
                        if (!output) return;
                        const data = json.data || {};
                        output.textContent = data.message || "";
                        output.className = "hint " + (data.available ? "" : "field-error");
                    })
                    .catch(() => {
                        if (output) output.textContent = "Could not check that name right now.";
                    });
            }, 420);
        });
    }

    /* ------------------------------------------------------ product form */

    function initProductForm() {
        const unit = $("[data-unit-select]");
        const custom = $("[data-custom-unit-field]");

        if (unit && custom) {
            const toggle = () => {
                const opt = unit.options[unit.selectedIndex];
                const needs = opt && opt.getAttribute("data-requires-custom") === "1";
                custom.hidden = !needs;
                const field = $("input", custom);
                if (field) field.required = !!needs;
            };
            unit.addEventListener("change", toggle);
            toggle();
        }

        const price = $("[data-price-input]");
        const offer = $("[data-offer-input]");
        if (price && offer) {
            const warn = $("[data-offer-warning]");
            const check = () => {
                const p = parseFloat(price.value || "0");
                const o = parseFloat(offer.value || "0");
                const bad = o > 0 && o >= p;
                if (warn) warn.hidden = !bad;
                offer.classList.toggle("is-invalid", bad);
            };
            offer.addEventListener("input", check);
            price.addEventListener("input", check);
        }

        initImageTiles();
    }

    function initImageTiles() {
        const list = $("[data-image-order]");
        if (!list) return;

        const tiles = $$(".image-tile", list);
        if (!tiles.length) return;

        // Each tile carries a hidden input holding its image id; reordering the
        // tiles reorders the submitted array. Values are never rewritten.
        list.addEventListener("click", (event) => {
            const button = event.target.closest("[data-move]");
            if (!button) return;
            const tile = button.closest(".image-tile");
            if (!tile) return;
            const dir = button.getAttribute("data-move");
            const sibling = dir === "up" ? tile.previousElementSibling : tile.nextElementSibling;
            if (!sibling) return;
            if (dir === "up") list.insertBefore(tile, sibling);
            else list.insertBefore(sibling, tile);
        });
    }

    function initImagePreview() {
        $$("input[type=file][data-preview]").forEach((input) => {
            input.addEventListener("change", () => {
                const target = $(input.getAttribute("data-preview"));
                if (!target) return;
                target.textContent = "";
                Array.prototype.slice.call(input.files || []).forEach((file) => {
                    const tile = document.createElement("div");
                    tile.className = "image-tile";
                    const img = document.createElement("img");
                    img.alt = file.name;
                    img.src = URL.createObjectURL(file);
                    tile.appendChild(img);
                    target.appendChild(tile);
                });
            });
        });
    }

    /* ------------------------------------------------------------- gallery */

    function initGallery() {
        const main = $("[data-gallery-main]");
        if (!main) return;
        $$("[data-gallery-thumb]").forEach((thumb) => {
            thumb.addEventListener("click", () => {
                $$("[data-gallery-thumb]").forEach((t) => t.classList.remove("is-on"));
                thumb.classList.add("is-on");
                main.src = thumb.getAttribute("data-gallery-thumb");
            });
        });
    }

    /* --------------------------------------------------------------- modal */

    function initModals() {
        $$("[data-modal-open]").forEach((trigger) => {
            trigger.addEventListener("click", (event) => {
                const modal = $(trigger.getAttribute("data-modal-open"));
                if (!modal) return;
                event.preventDefault();
                modal.hidden = false;
                const first = $("input, select, textarea, button", modal);
                if (first) first.focus();

                $$("[data-fill]", modal).forEach((input) => {
                    const attr = input.getAttribute("data-fill");
                    if (attr) input.value = trigger.getAttribute(attr) || "";
                });
            });
        });

        $$(".modal-backdrop").forEach((backdrop) => {
            backdrop.addEventListener("click", (event) => {
                if (event.target === backdrop) backdrop.hidden = true;
            });
            $$("[data-modal-close]", backdrop).forEach((btn) => {
                btn.addEventListener("click", () => { backdrop.hidden = true; });
            });
        });

        document.addEventListener("keydown", (event) => {
            if (event.key === "Escape") {
                $$(".modal-backdrop").forEach((b) => { b.hidden = true; });
            }
        });
    }

    function initConfirms() {
        $$("form[data-confirm]").forEach((form) => {
            form.addEventListener("submit", (event) => {
                if (!window.confirm(form.getAttribute("data-confirm"))) event.preventDefault();
            });
        });
        $$("[data-confirm-click]").forEach((el) => {
            el.addEventListener("click", (event) => {
                if (!window.confirm(el.getAttribute("data-confirm-click"))) event.preventDefault();
            });
        });
    }

    /* ---------------------------------------------------------------- chat */

    function initChat() {
        const root = $("[data-chat]");
        if (!root) return;

        const list = $("[data-chat-messages]", root);
        const form = $("[data-chat-form]", root);
        const scroller = $("[data-chat-scroll]", root);
        const state = $("[data-conn-state]", root);
        const frozen = root.getAttribute("data-frozen") === "1";

        let after = Number(root.getAttribute("data-after") || "0");
        let timer = null;
        let sending = false;

        const esc = (text) => {
            const el = document.createElement("div");
            el.textContent = text == null ? "" : String(text);
            return el.textContent;
        };

        function nearBottom() {
            if (!scroller) return true;
            return scroller.scrollHeight - scroller.scrollTop - scroller.clientHeight < 120;
        }

        function scrollDown() {
            if (scroller) scroller.scrollTop = scroller.scrollHeight;
        }

        function render(message, pending) {
            const mine = message.sender_type === root.getAttribute("data-my-type") &&
                String(message.sender_id) === root.getAttribute("data-my-id");

            const wrap = document.createElement("div");
            wrap.className = "msg " + (mine ? "msg-out" : "msg-in") + (pending ? " is-pending" : "");
            wrap.setAttribute("data-message-id", String(message.id || ""));
            if (message.client_message_id) wrap.setAttribute("data-client-id", message.client_message_id);

            const body = document.createElement("div");
            body.className = "msg-body";
            body.textContent = message.body;

            const meta = document.createElement("div");
            meta.className = "meta";
            meta.textContent = pending ? "Sending…" : esc(message.created_at || "").replace("T", " ").slice(0, 16);

            wrap.appendChild(body);
            wrap.appendChild(meta);
            if (list) list.appendChild(wrap);

            return wrap;
        }

        function setState(text, cls) {
            if (!state) return;
            state.className = "conn-state " + (cls || "");
            const label = $("[data-conn-label]", state) || state;
            label.textContent = text;
        }

        function appendIncoming(payload) {
            if (!list) return;
            const existing = list.querySelector('[data-message-id="' + payload.id + '"]');
            if (existing) return;
            const hadFocus = nearBottom();
            render(payload, false);
            if (hadFocus) scrollDown();
            if (payload.id > after) after = payload.id;
            root.setAttribute("data-after", String(after));
        }

        function markRead() {
            const url = root.getAttribute("data-read-url");
            if (!url) return;
            fetch(url, {
                method: "PATCH",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": token(),
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({ message_id: after }),
            }).catch(() => {});
        }

        function poll() {
            if (document.hidden || frozen) {
                schedule();
                return;
            }

            const url = root.getAttribute("data-poll-url") + "?after=" + after;

            fetch(url, { headers: { Accept: "application/json", "X-Requested-With": "XMLHttpRequest" } })
                .then((r) => {
                    if (!r.ok) throw new Error("bad status");
                    return r.json();
                })
                .then((json) => {
                    (json.data || []).forEach(appendIncoming);
                    setState("Connected", "");
                    if ((json.data || []).length) markRead();
                })
                .catch(() => setState("Reconnecting…", "is-offline"))
                .then(schedule);
        }

        function schedule() {
            window.clearTimeout(timer);
            const interval = Number(root.getAttribute("data-interval") || "5000");
            timer = window.setTimeout(poll, interval);
        }

        function send(text) {
            if (sending || !text.trim()) return;
            sending = true;

            const clientId = "cm-" + Date.now() + "-" + Math.random().toString(36).slice(2, 8);
            const optimistic = render(
                { id: null, sender_type: root.getAttribute("data-my-type"), sender_id: root.getAttribute("data-my-id"), body: text, created_at: "", client_message_id: clientId },
                true
            );
            scrollDown();

            fetch(root.getAttribute("data-send-url"), {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": token(),
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({ body: text, client_message_id: clientId }),
            })
                .then((r) => r.json().then((json) => ({ ok: r.ok, json: json })))
                .then((result) => {
                    if (!result.ok) throw new Error((result.json && result.json.message) || "Message failed");
                    const data = result.json.data || {};
                    optimistic.classList.remove("is-pending");
                    if (data.id) {
                        optimistic.setAttribute("data-message-id", String(data.id));
                        if (data.id > after) after = data.id;
                        root.setAttribute("data-after", String(after));
                    }
                    setState("Connected", "");
                })
                .catch((err) => {
                    optimistic.classList.remove("is-pending");
                    optimistic.classList.add("is-failed");
                    const meta = $(".meta", optimistic);
                    if (meta) meta.textContent = "Not delivered — tap send to retry";
                    toast(err.message || "Message not delivered.", true);
                })
                .then(() => {
                    sending = false;
                });
        }

        if (form) {
            const field = $("textarea[name=body], input[name=body]", form);
            form.addEventListener("submit", (event) => {
                event.preventDefault();
                if (frozen) {
                    toast("This shop account cannot send messages right now.", true);
                    return;
                }
                const text = field ? field.value : "";
                if (!text.trim()) return;
                if (field) field.value = "";
                send(text);
            });

            if (field) {
                field.addEventListener("keydown", (event) => {
                    if (event.key === "Enter" && !event.shiftKey) {
                        event.preventDefault();
                        form.dispatchEvent(new Event("submit", { cancelable: true }));
                    }
                });
            }
        }

        document.addEventListener("visibilitychange", () => {
            if (!document.hidden) poll();
        });

        window.addEventListener("offline", () => setState("Offline", "is-offline"));
        window.addEventListener("online", () => { setState("Connected", ""); poll(); });

        if (frozen) setState("Read only", "is-frozen");
        scrollDown();
        schedule();
    }

    /* -------------------------------------------------------------- misc */

    function initFlash() {
        const flash = $("[data-flash]");
        if (flash) toast(flash.getAttribute("data-flash"), flash.getAttribute("data-flash-error") === "1");
    }

    function initStylingSelects() {
        $$("select[data-autosubmit]").forEach((select) => {
            select.addEventListener("change", () => {
                const form = select.form;
                if (form && form.hasAttribute("data-filter-form")) return; // handled above
                if (form && form.hasAttribute("data-submit-on-change")) form.submit();
            });
        });
    }

    document.addEventListener("DOMContentLoaded", () => {
        initFilterForms();
        initChips();
        initNameCheck();
        initProductForm();
        initImagePreview();
        initGallery();
        initModals();
        initConfirms();
        initChat();
        initFlash();
        initStylingSelects();
    });
})();
