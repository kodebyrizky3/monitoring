(() => {
  "use strict";

  const $ = (s, p = document) => p.querySelector(s);

  // ===== DEBUG =====
  const DEBUG_BULK = true; // set ke false kalau tidak perlu
  if (DEBUG_BULK) window.__bulkDebug = { parsed: [], payload: [] };

  // ====== Parser CSV/TSV/semicolon dengan dukungan kutip ganda ======
  function parseDelimited(text) {
    if (!text) return [];
    const lines = text
      .replace(/\r\n/g, "\n")
      .replace(/\r/g, "\n")
      .split("\n")
      .filter((l) => l.trim().length > 0);
    if (!lines.length) return [];

    const sample = lines[0];
    const candidates = [",", ";", "\t"];
    const best = candidates
      .map((d) => ({
        d,
        c: (sample.match(new RegExp("\\" + d, "g")) || []).length,
      }))
      .sort((a, b) => b.c - a.c)[0].d;
    const delim = best;

    const splitLine = (line) => {
      const out = [];
      let cur = "",
        inQ = false;
      for (let i = 0; i < line.length; i++) {
        const ch = line[i];
        if (ch === '"') {
          if (inQ && line[i + 1] === '"') {
            cur += '"';
            i++;
          } else inQ = !inQ;
        } else if (ch === delim && !inQ) {
          out.push(cur.trim());
          cur = "";
        } else cur += ch;
      }
      out.push(cur.trim());
      return out;
    };
    return lines.map(splitLine);
  }

  function normalizeStatus(v) {
    const s = (v || "NORMAL")
      .toString()
      .trim()
      .toUpperCase()
      .replace(/\s+/g, "_");
    return ["NORMAL", "RUSAK_RINGAN", "RUSAK_BERAT"].includes(s) ? s : "NORMAL";
  }

  // ===== CSRF refresher =====
  async function refreshCsrf(form) {
    const url = form?.dataset?.diagUrl;
    if (!url) return;
    try {
      const res = await fetch(url, {
        method: "GET",
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        credentials: "same-origin",
      });
      const js = await res.json();
      if (js && js.csrf && js.csrf_token) {
        document
          .querySelectorAll(`input[name="${js.csrf_token}"]`)
          .forEach((inp) => (inp.value = js.csrf));
      }
    } catch {}
  }

  // ===== State =====
  let parsedRows = [];
  let fileText = "";

  function renderPreview() {
    const tbody = $("#bulkPreviewTbody");
    const countEl = $("#bulkCount");
    tbody.innerHTML = "";

    if (!parsedRows.length) {
      tbody.innerHTML = `<tr><td colspan="13" class="text-center text-muted py-4">Belum ada data.</td></tr>`;
      countEl.textContent = "0 baris siap disimpan";
      $("#btnBulkSave")?.setAttribute("disabled", "disabled");
      return;
    }

    const frag = document.createDocumentFragment();
    parsedRows.forEach((r, idx) => {
      const tr = document.createElement("tr");
      const safe = (x) => (x && x.length ? x : "—");
      const cells = [
        idx + 1,
        safe(r[0]), // Nama
        safe(r[1]), // Merek
        safe(r[2]), // Model
        safe(r[3]), // Serial
        safe(r[4]), // Lokasi
        safe(r[5]), // BTU
        safe(r[6]), // BMN
        normalizeStatus(r[7]), // Status
        safe(r[8]), // Freon
        safe(r[9]), // Amper
        safe(r[10]), // Ter. Service (DD-MM-YYYY)
        safe(r[11]), // Ter. Perawatan (DD-MM-YYYY)
      ];
      cells.forEach((c) => {
        const td = document.createElement("td");
        td.textContent = c;
        tr.appendChild(td);
      });
      frag.appendChild(tr);
    });
    tbody.appendChild(frag);
    countEl.textContent = `${parsedRows.length} baris siap disimpan`;
    $("#btnBulkSave")?.removeAttribute("disabled");
  }

  function doParse() {
    if (!fileText) {
      parsedRows = [];
      renderPreview();
      return;
    }
    let rows = parseDelimited(fileText);
    if (!rows.length) {
      parsedRows = [];
      renderPreview();
      return;
    }

    const hasHeader = $("#hasHeader")?.checked;
    if (hasHeader && rows.length) rows = rows.slice(1);

    parsedRows = rows.map((r) => {
      const arr = new Array(12).fill("");
      for (let i = 0; i < Math.min(r.length, 12); i++)
        arr[i] = (r[i] || "").toString().trim();
      return arr;
    });

    // buang baris yang bener-bener kosong semua kolom
    parsedRows = parsedRows.filter((r) =>
      r.some((v) => (v || "").trim() !== "")
    );

    if (parsedRows.length > 1000) {
      parsedRows = parsedRows.slice(0, 1000);
      $("#bulkMsg").textContent = "Dipangkas ke 1000 baris maksimum.";
    } else {
      $("#bulkMsg").textContent = "";
    }

    if (DEBUG_BULK) {
      window.__bulkDebug.parsed = parsedRows;
      console.table(parsedRows);
    }

    renderPreview();
  }

  async function handleFile(file) {
    if (!file) return;
    try {
      fileText = await file.text();
      doParse();
    } catch {
      fileText = "";
      parsedRows = [];
      renderPreview();
      Swal.fire({
        icon: "error",
        title: "Gagal",
        text: "Tidak bisa membaca file ini.",
      });
    }
  }

  async function saveBulk() {
    if (!parsedRows.length) return;
    const form = $("#bulkForm");
    const saveUrl = form?.dataset?.bulkUrl;
    if (!saveUrl) return;

    // refresh CSRF dulu
    await refreshCsrf(form);

    const rowsObj = parsedRows.map((r) => ({
      nama: r[0],
      merek: r[1] || null,
      model: r[2] || null,
      serial_no: r[3] || null,
      lokasi: r[4] || null,
      kapasitas_btu: (r[5] || "").replace(/\D+/g, "") || "12000",
      bmn_no_display: (r[6] || "").replace(/\D+/g, "") || null,
      status: normalizeStatus(r[7]),
      tekanan_freon_terakhir: r[8] || null,
      amper_terakhir: r[9] || null,
      // tanggal DD-MM-YYYY -> backend konversi ke YYYY-MM-DD
      terakhir_service: r[10] || null,
      terakhir_perawatan: r[11] || null,
    }));

    if (DEBUG_BULK) {
      window.__bulkDebug.payload = rowsObj;
      console.log("rowsObj →", rowsObj);
      // akses cepat dari console:
      // __bulkDebug.parsed / __bulkDebug.payload
    }

    const btn = $("#btnBulkSave");
    const oldHtml = btn.innerHTML;
    btn.setAttribute("disabled", "disabled");
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...`;

    try {
      const fd = new FormData(form);
      fd.set("rows", JSON.stringify(rowsObj));

      const res = await fetch(saveUrl, {
        method: "POST",
        body: fd,
        headers: {
          "X-Requested-With": "XMLHttpRequest",
          Accept: "application/json",
        },
        credentials: "same-origin",
      });

      let js = null;
      try {
        js = await res.json();
      } catch {}

      // refresh CSRF untuk next actions
      if (js && js.csrf && js.csrf_token) {
        document
          .querySelectorAll(`input[name="${js.csrf_token}"]`)
          .forEach((inp) => (inp.value = js.csrf));
      }

      if (!res.ok || !js || js.ok !== true) {
        const msg =
          (js && (js.error || js.message)) || `Gagal simpan (${res.status})`;
        throw new Error(msg);
      }

      const ok = js.success || 0;
      const fail = js.failed || 0;
      const total = js.total || rowsObj.length;

      await Swal.fire({
        icon: fail ? "warning" : "success",
        title: "Selesai",
        html: `Total: <b>${total}</b><br>Berhasil: <b>${ok}</b> · Gagal: <b>${fail}</b>`,
      });

      // tutup modal & reset
      const modalEl = document.getElementById("bulkModal");
      bootstrap.Modal.getOrCreateInstance(modalEl).hide();
      resetBulkUI();
    } catch (err) {
      Swal.fire({
        icon: "error",
        title: "Gagal simpan",
        text: err?.message || "Terjadi kesalahan.",
      });
    } finally {
      btn.innerHTML = oldHtml;
      btn.removeAttribute("disabled");
    }
  }

  function resetBulkUI() {
    parsedRows = [];
    fileText = "";
    const file = $("#bulkFile");
    if (file) file.value = "";
    const zip = $("#imagesZip");
    if (zip) zip.value = "";
    $(
      "#bulkPreviewTbody"
    ).innerHTML = `<tr><td colspan="13" class="text-center text-muted py-4">Belum ada data.</td></tr>`;
    $("#bulkCount").textContent = "0 baris siap disimpan";
    $("#bulkMsg").textContent = "";
    $("#btnBulkSave")?.setAttribute("disabled", "disabled");
  }

  document.addEventListener("DOMContentLoaded", () => {
    // Download template: HANYA HEADER
    $("#btnTemplate")?.addEventListener("click", () => {
      const header =
        "Nama,Merek,Model,Serial No,Lokasi,Kapasitas BTU,Nomor BMN,Status,Tekanan Freon Terakhir,Amper Terakhir,Terakhir Service,Terakhir Perawatan\n";
      const blob = new Blob([header], { type: "text/csv" });
      const a = document.createElement("a");
      a.href = URL.createObjectURL(blob);
      a.download = "template-ac.csv";
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 1500);
    });

    $("#bulkFile")?.addEventListener("change", async (e) => {
      const f = e.target.files?.[0];
      await handleFile(f);
    });
    $("#hasHeader")?.addEventListener("change", doParse);
    $("#btnBulkSave")?.addEventListener("click", saveBulk);

    const bulkModal = document.getElementById("bulkModal");
    bulkModal?.addEventListener("hidden.bs.modal", resetBulkUI);
  });
})();
