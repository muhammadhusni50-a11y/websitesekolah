const ppdbForm = document.querySelector("#ppdb-form");
const ppdbStatus = document.querySelector("[data-ppdb-status]");
const ppdbSubmit = document.querySelector("[data-ppdb-submit]");
const ppdbSubmitLabel = ppdbSubmit?.querySelector("[data-submit-label]");
const ppdbDefaultSubmitLabel = ppdbSubmitLabel?.textContent ?? "Kirim formulir";

function showPpdbStatus(message, isError = false) {
  if (!ppdbStatus) return;
  ppdbStatus.textContent = message;
  ppdbStatus.classList.toggle("is-error", isError);
  ppdbStatus.hidden = false;
}

ppdbForm?.addEventListener("submit", async (event) => {
  event.preventDefault();
  if (!ppdbForm.reportValidity() || !ppdbSubmit || !ppdbStatus) return;

  ppdbSubmit.disabled = true;
  ppdbForm.setAttribute("aria-busy", "true");
  if (ppdbSubmitLabel) ppdbSubmitLabel.textContent = "Mengirim...";
  ppdbStatus.hidden = true;
  ppdbStatus.classList.remove("is-error");

  try {
    const response = await fetch(ppdbForm.action, {
      method: "POST",
      headers: { Accept: "application/json" },
      body: new FormData(ppdbForm),
      credentials: "same-origin",
    });
    const result = await response.json();
    if (!response.ok || result.ok !== true) {
      throw new Error(result.message || "Formulir belum dapat dikirim. Periksa kembali data yang diisi.");
    }
    ppdbForm.reset();
    showPpdbStatus(result.message || "Terima kasih. Minat PPDB Anda sudah tercatat.");
    ppdbStatus.focus();
  } catch (error) {
    const message = error instanceof Error && error.name === "Error" && error.message
      ? error.message
      : "Pendaftaran belum dapat dikirim. Periksa koneksi, lalu coba kembali atau hubungi sekolah melalui email.";
    showPpdbStatus(message, true);
    ppdbStatus.focus();
  } finally {
    ppdbSubmit.disabled = false;
    ppdbForm.removeAttribute("aria-busy");
    if (ppdbSubmitLabel) ppdbSubmitLabel.textContent = ppdbDefaultSubmitLabel;
  }
});
