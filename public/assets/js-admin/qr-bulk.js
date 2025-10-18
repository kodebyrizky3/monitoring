(() => {
  "use strict";

  const $ = (s, p = document) => p.querySelector(s);
  const $$ = (s, p = document) => Array.from(p.querySelectorAll(s));

  // CSV/TSV parser sederhana (dukung koma, kutip; juga deteksi TAB)
  function parseDelimited(text) {
    if (!text) return [];
    const lines = text
      .replace(/\r\n/g, "\n")
      .replace(/\r/g, "\n")
      .split("\n")
      .map((l) => l.trim())
      .filter((l) => l.length > 0);

    if (lines.length === 0) return [];

    // deteksi delimiter: tab lebih prioritas jika ada
    const useTab = lines.some((l) => l.includes("\t"));
    const delim = useTab ? "\t" : ",";

    const splitLine = (line) => {
      if (delim === "\t") {
        return line.split("\t").map((s) => s.trim());
      }
      // CSV split by comma not inside double quotes
      const parts = line.match(/("([^"]|"")*"|[^,])+/g) || [];
      return parts.map((p) => {
        let s = p.trim();
        if (s.startsWith('"') && s.endsWith('"')) {
          s = s.slice(1, -1).replace(/""/g, '"');
        }
        return s;
      });
    };

    return lines.map(splitLine);
  }

  function normalizeStatus(v) {
    const s = (v || "NORMAL")
      .toString()
      .trim()
      .toUpperCase()
      .replace(/\s+/g, "_");
    if (["NORMAL", "RUSAK_RINGAN", "RUSAK_BERAT"].includes(s)) return s;
    // fallback NORMAL
    return "NORMAL";
  }

  // State
  let parsedRows = []; // array of arrays (fixed order)

  function renderPreview() {
    const tbody = $("#bulkPreviewTbody");
    const countEl = $("#bulkCount");
    tbody.innerHTML = "";

    if (!parsedRows.length) {
      tbody.innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">Belum ada data.</td></tr>`;
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
        safe(r[0]),
        safe(r[1]),
        safe(r[2]),
        safe(r[3]),
        safe(r[4]),
        safe(r[5]),
        safe(r[6]),
        normalizeStatus(r[7]),
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

  async function handleFile(file) {
    if (!file) return;
    const text = await file.text();
    $("#bulkText").value = text;
  }

  function parseFromTextarea() {
    const raw = $("#bulkText").value || "";
    let rows = parseDelimited(raw);

    if (!rows.length) {
      parsedRows = [];
      renderPreview();
      return;
    }

    const hasHeader = $("#hasHeader")?.checked;
    if (hasHeader && rows.length) {
      // drop first row as header
      rows = rows.slice(1);
    }

    // map to 8 columns (fill missing with "")
    parsedRows = rows.map((r) => {
      const arr = new Array(8).fill("");
      for (let i = 0; i < Math.min(r.length, 8); i++) {
        arr[i] = (r[i] || "").toString().trim();
      }
      return arr;
    });

    // Filter baris kosong (nama wajib)
    parsedRows = parsedRows.filter((r) => (r[0] || "").trim() !== "");

    // Limit 1000
    if (parsedRows.length > 1000) {
      parsedRows = parsedRows.slice(0, 1000);
      $("#bulkMsg").textContent = "Dipangkas ke 1000 baris maksimum.";
    } else {
      $("#bulkMsg").textContent = "";
    }

    renderPreview();
  }

  async function saveBulk() {
    if (!parsedRows.length) return;
    const form = $("#bulkForm");
    const saveUrl = form?.dataset?.bulkUrl;
    if (!saveUrl) return;

    // siapkan payload ke format objek
    const rowsObj = parsedRows.map((r) => ({
      nama: r[0],
      merek: r[1] || null,
      model: r[2] || null,
      serial_no: r[3] || null,
      lokasi: r[4] || null,
      kapasitas_btu: (r[5] || "").replace(/\D+/g, "") || "12000",
      bmn_no_display: (r[6] || "").replace(/\D+/g, "") || null,
      status: normalizeStatus(r[7]),
    }));

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

      // refresh CSRF jika ada
      if (js && js.csrf && js.csrf_token) {
        const inp = form.querySelector(`input[name="${js.csrf_token}"]`);
        if (inp) inp.value = js.csrf;
        // sinkronkan ke form utama juga (biar konsisten)
        const inp2 = document.querySelector(
          `#formQR input[name="${js.csrf_token}"]`
        );
        if (inp2) inp2.value = js.csrf;
      }

      if (!res.ok || !js || js.ok !== true) {
        const msg =
          (js && (js.error || js.message)) || `Gagal simpan (${res.status})`;
        throw new Error(msg);
      }

      // Tampilkan ringkasan & status per baris
      const ok = js.success || 0;
      const fail = js.failed || 0;
      const total = js.total || rowsObj.length;

      Swal.fire({
        icon: fail ? "warning" : "success",
        title: "Selesai",
        html: `Total: <b>${total}</b> &middot; Berhasil: <b>${ok}</b> &middot; Gagal: <b>${fail}</b>`,
      });

      // Tabel preview → tambahkan kolom hasil (sementara: highlight baris gagal)
      const tbody = $("#bulkPreviewTbody");
      // hapus status lama
      $$("#bulkPreviewTable thead tr th").length === 9 &&
        $("#bulkPreviewTable thead tr").appendChild(
          (() => {
            const th = document.createElement("th");
            th.textContent = "Hasil";
            return th;
          })()
        );
      const trs = $$("#bulkPreviewTbody tr");
      // reset
      trs.forEach((tr) => tr.classList.remove("table-danger", "table-success"));
      // apply
      (js.results || []).forEach((r) => {
        const idx = (r.row || 1) - 1;
        const tr = trs[idx];
        if (!tr) return;
        const td = document.createElement("td");
        if (r.ok) {
          td.textContent = "OK";
          tr.classList.add("table-success");
        } else {
          td.textContent = r.error || "Gagal";
          tr.classList.add("table-danger");
        }
        tr.appendChild(td);
      });
    } catch (err) {
      console.error(err);
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

  // Wire UI
  document.addEventListener("DOMContentLoaded", () => {
    $("#btnTemplate")?.addEventListener("click", () => {
      const header =
        "Nama,Merek,Model,Serial No,Lokasi,Kapasitas BTU,Nomor BMN,Status\n";
      const blob = new Blob([header], { type: "text/csv" });
      const a = document.createElement("a");
      a.href = URL.createObjectURL(blob);
      a.download = "template-ac.csv";
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 1500);
    });

    $("#bulkFile")?.addEventListener("change", async (e) => {
      const f = e.target.files?.[0];
      if (f) await handleFile(f);
    });

    $("#btnBulkParse")?.addEventListener("click", parseFromTextarea);
    $("#btnBulkSave")?.addEventListener("click", saveBulk);

    // auto-parse saat modal dibuka jika sebelumnya sudah ada teks
    const bulkModal = document.getElementById("bulkModal");
    bulkModal?.addEventListener("shown.bs.modal", () => {
      if (($("#bulkText").value || "").trim()) parseFromTextarea();
    });

    // reset preview saat ditutup
    bulkModal?.addEventListener("hidden.bs.modal", () => {
      parsedRows = [];
      $("#bulkText").value = "";
      $(
        "#bulkPreviewTbody"
      ).innerHTML = `<tr><td colspan="9" class="text-center text-muted py-4">Belum ada data.</td></tr>`;
      $("#bulkCount").textContent = "0 baris siap disimpan";
      $("#bulkMsg").textContent = "";
      $("#btnBulkSave")?.setAttribute("disabled", "disabled");
      // buang kolom "Hasil" kalau ada
      const ths = $$("#bulkPreviewTable thead tr th");
      if (ths.length === 10) ths[9].remove();
    });
  });
})();
