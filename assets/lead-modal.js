(function () {
  const BOT_USERNAME = "OasisDefenderBot";

  function createModal() {
    if (document.getElementById("lead-modal-overlay")) return;

    const overlay = document.createElement("div");
    overlay.id = "lead-modal-overlay";
    overlay.className = "lead-modal-overlay";
    overlay.setAttribute("aria-hidden", "true");
    overlay.innerHTML = [
      '<div class="lead-modal" role="dialog" aria-modal="true" aria-labelledby="lead-modal-title">',
      '  <div class="lead-modal-head">',
      '    <h3 class="lead-modal-title" id="lead-modal-title">Запросить демо / пилот</h3>',
      '    <button class="lead-modal-close" type="button" data-close-lead-modal aria-label="Закрыть">×</button>',
      '  </div>',
      '  <div class="lead-modal-body">',
      '    <p class="lead-modal-intro">Оставьте контакт, и мы вернёмся с предложением по демо или пилоту</p>',
      '    <form class="lead-form" id="lead-form">',
      '      <div class="lead-field">',
      '        <label for="lead-name">Имя</label>',
      '        <input id="lead-name" name="name" type="text" required />',
      '      </div>',
      '      <div class="lead-field">',
      '        <label for="lead-company">Компания</label>',
      '        <input id="lead-company" name="company" type="text" />',
      '      </div>',
      '      <div class="lead-field">',
      '        <label for="lead-contact">Контакт (email / Telegram / телефон)</label>',
      '        <input id="lead-contact" name="contact" type="text" required />',
      '      </div>',
      '      <div class="lead-field">',
      '        <label for="lead-note">Комментарий</label>',
      '        <textarea id="lead-note" name="note"></textarea>',
      '      </div>',
      '      <div class="lead-modal-actions">',
      '        <button class="btn primary" type="submit" id="lead-submit-btn">Отправить</button>',
      '        <button class="btn" type="button" data-close-lead-modal>Отмена</button>',
      '      </div>',
      '      <p class="lead-modal-status" id="lead-modal-status" aria-live="polite"></p>',
      '    </form>',
      '  </div>',
      '</div>'
    ].join("\n");

    document.body.appendChild(overlay);
  }

  function setOpenState(open) {
    const overlay = document.getElementById("lead-modal-overlay");
    if (!overlay) return;
    overlay.classList.toggle("is-open", open);
    overlay.setAttribute("aria-hidden", open ? "false" : "true");
  }

  function setStatus(text, type) {
    const status = document.getElementById("lead-modal-status");
    if (!status) return;
    status.textContent = text || "";
    status.classList.remove("error", "success");
    if (type) status.classList.add(type);
  }

  function buildLeadMessage(payload) {
    return [
      "Новая заявка с сайта Oasis Defender",
      "",
      "Тип: " + (payload.intent || "демо / пилот"),
      "Страница: " + payload.page,
      "Источник кнопки: " + (payload.source || "unknown"),
      "Имя: " + payload.name,
      "Компания: " + (payload.company || "-"),
      "Контакт: " + payload.contact,
      "Комментарий: " + (payload.note || "-"),
      "Время: " + payload.timestamp
    ].join("\n");
  }

  async function sendLead(payload) {
    const endpoint = window.OASIS_LEAD_ENDPOINT || "";
    const tgToken = window.OASIS_TG_BOT_TOKEN || "";
    const tgChatId = window.OASIS_TG_CHAT_ID || "";

    if (endpoint) {
      const response = await fetch(endpoint, {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
      });
      if (!response.ok) {
        throw new Error("endpoint_error");
      }
      return;
    }

    if (tgToken && tgChatId) {
      const response = await fetch("https://api.telegram.org/bot" + tgToken + "/sendMessage", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          chat_id: tgChatId,
          text: buildLeadMessage(payload)
        })
      });
      if (!response.ok) {
        throw new Error("telegram_error");
      }
      return;
    }

    throw new Error("not_configured");
  }

  function wireModal() {
    const overlay = document.getElementById("lead-modal-overlay");
    const form = document.getElementById("lead-form");
    const submitBtn = document.getElementById("lead-submit-btn");
    if (!overlay || !form || !submitBtn) return;

    let currentSource = "";
    let currentIntent = "";

    document.addEventListener("click", function (event) {
      const opener = event.target.closest("[data-open-lead-modal]");
      if (opener) {
        event.preventDefault();
        currentSource = opener.getAttribute("data-lead-source") || "";
        currentIntent = opener.getAttribute("data-lead-intent") || "";
        form.reset();
        setStatus("");
        setOpenState(true);
        const nameField = document.getElementById("lead-name");
        if (nameField) nameField.focus();
        return;
      }

      if (event.target.matches("[data-close-lead-modal]") || event.target === overlay) {
        event.preventDefault();
        setOpenState(false);
      }
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") setOpenState(false);
    });

    form.addEventListener("submit", async function (event) {
      event.preventDefault();
      setStatus("");
      submitBtn.disabled = true;

      const payload = {
        intent: currentIntent || "Запросить демо / пилот",
        source: currentSource || "",
        page: window.location.pathname,
        name: form.name.value.trim(),
        company: form.company.value.trim(),
        contact: form.contact.value.trim(),
        note: form.note.value.trim(),
        timestamp: new Date().toISOString()
      };

      try {
        await sendLead(payload);
        setStatus("Контакт отправлен. Мы свяжемся с вами.", "success");
        form.reset();
      } catch (error) {
        if (String(error.message) === "not_configured") {
          setStatus("Не настроен приём заявок. Откройте Telegram-бота и напишите нам вручную.", "error");
          window.open("https://t.me/" + BOT_USERNAME, "_blank", "noopener,noreferrer");
        } else {
          setStatus("Не удалось отправить заявку. Попробуйте еще раз.", "error");
        }
      } finally {
        submitBtn.disabled = false;
      }
    });
  }

  document.addEventListener("DOMContentLoaded", function () {
    createModal();
    wireModal();
  });
})();
