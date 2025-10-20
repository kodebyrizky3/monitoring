(() => {
  "use strict";

  const $ = (s, p = document) => p.querySelector(s);
  const DEBUG_BULK = false;
  if (DEBUG_BULK) window.__bulkDebug = { parsed: [], payload: [] };

  // ========= CSV parsing =========
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
    const delim = candidates
      .map((d) => ({
        d,
        c: (sample.match(new RegExp("\\" + d, "g")) || []).length,
      }))
      .sort((a, b) => b.c - a.c)[0].d;

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

  // ========= Helpers =========
  const normalizeStatus = (v) =>
    ["NORMAL", "RUSAK_RINGAN", "RUSAK_BERAT"].includes(
      (v || "NORMAL").toString().trim().toUpperCase().replace(/\s+/g, "_")
    )
      ? (v || "NORMAL").toString().trim().toUpperCase().replace(/\s+/g, "_")
      : "NORMAL";

  // Deteksi baris pertama itu header
  function looksLikeHeader(row) {
    if (!row || !row.length) return false;
    const j = row.join(" ").toLowerCase();
    const must = ["nama", "lokasi", "bmn"]; // istilah kunci
    let hit = 0;
    must.forEach((k) => (j.includes(k) ? hit++ : null));
    return hit >= 2;
  }

  // E+ → plain string (jaga2 kalau user upload CSV dari Excel)
  function sciToPlain(str) {
    const s = String(str).trim().replace(/\s+/g, "");
    const m = s.match(/^([+-]?)(\d+)(?:\.(\d*))?[eE]([+-]?\d+)$/);
    if (!m) return null;
    const sign = m[1] === "-" ? "-" : "";
    let int = m[2];
    let frac = m[3] || "";
    let exp = parseInt(m[4], 10);
    if (!Number.isFinite(exp)) return null;
    let digits = int + frac;
    const pointPos = int.length;
    const newPos = pointPos + exp;
    if (exp >= 0) {
      if (newPos >= digits.length) {
        digits = digits + "0".repeat(newPos - digits.length);
        return sign + digits.replace(/^0+$/, "0");
      } else {
        const left = digits.slice(0, newPos);
        const right = digits.slice(newPos);
        return sign + (right ? (left || "0") + "." + right : left);
      }
    } else {
      if (newPos <= 0) {
        return sign + "0." + "0".repeat(-newPos) + digits.replace(/^0+/, "");
      } else {
        const left = digits.slice(0, newPos);
        const right = digits.slice(newPos);
        return sign + (right ? left + "." + right : left);
      }
    }
  }

  // BMN display: biarkan angka . - spasi, tangani E+
  function sanitizeBmn(s, stats) {
    let raw = (s || "").toString().trim();
    if (!raw) return "";
    if (/^[+-]?\d+(?:\.\d+)?\s*[eE]\s*[+-]?\d+$/.test(raw)) {
      const plain = sciToPlain(raw);
      if (plain) {
        raw = plain;
        stats && stats.sci++;
      }
    }
    raw = raw.replace(/[^0-9.\-\s]+/g, "");
    if (raw.length > 64) {
      raw = raw.slice(0, 64);
      stats && stats.trimmed++;
    }
    return raw;
  }

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

  let parsedRows = [];
  let fileText = "";

  // ========= Preview =========
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
      const safe = (x) => (x && String(x).length ? x : "—");
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
        safe(r[8]),
        safe(r[9]),
        safe(r[10]),
        safe(r[11]),
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

    // Buang header: auto detect, tanpa checkbox
    let msg = "";
    if (rows.length && looksLikeHeader(rows[0])) {
      rows = rows.slice(1);
      msg += "Baris header terdeteksi dan diabaikan. ";
    }

    const stats = { sci: 0, trimmed: 0 };
    parsedRows = rows
      .map((r) => {
        const arr = new Array(12).fill("");
        for (let i = 0; i < Math.min(r.length, 12); i++)
          arr[i] = (r[i] || "").toString().trim();
        // BMN (index 6)
        arr[6] = sanitizeBmn(arr[6], stats);
        return arr;
      })
      .filter((r) => r.some((v) => (v || "").trim() !== ""));

    if (parsedRows.length > 1000) {
      parsedRows = parsedRows.slice(0, 1000);
      msg += "Dipangkas ke 1000 baris maksimum. ";
    }
    if (stats.sci)
      msg += `Ditemukan ${stats.sci} BMN notasi ilmiah (diubah ke angka biasa). `;
    if (stats.trimmed)
      msg += `Ada ${stats.trimmed} BMN dipangkas ke 64 karakter. `;
    $("#bulkMsg").textContent = msg.trim();

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

  // ========= Submit =========
  async function saveBulk() {
    if (!parsedRows.length) return;
    const form = $("#bulkForm");
    const saveUrl = form?.dataset?.bulkUrl;
    if (!saveUrl) return;

    await refreshCsrf(form);

    const rowsObj = parsedRows.map((r) => ({
      nama: r[0],
      merek: r[1] || null,
      model: r[2] || null,
      serial_no: r[3] || null,
      lokasi: r[4] || null,
      kapasitas_btu: (r[5] || "").replace(/\D+/g, "") || "12000",
      bmn_no_display: sanitizeBmn(r[6]) || null, // display (separator boleh)
      status: normalizeStatus(r[7]),
      tekanan_freon_terakhir: r[8] || null,
      amper_terakhir: r[9] || null,
      terakhir_service: r[10] || null, // DD-MM-YYYY
      terakhir_perawatan: r[11] || null,
    }));

    if (DEBUG_BULK) {
      window.__bulkDebug.payload = rowsObj;
      console.log("rowsObj →", rowsObj);
    }

    const btn = $("#btnBulkSave");
    const oldHtml = btn.innerHTML;
    btn.setAttribute("disabled", "disabled");
    btn.innerHTML = `<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...`;

    try {
      const fd = new FormData(form);
      fd.set("rows", JSON.stringify(rowsObj));

      // ZIP (opsional)
      const zipInp = $("#imagesZip");
      if (zipInp?.files?.[0]) fd.set("images_zip", zipInp.files[0]);

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

      const ok = js.success || 0,
        fail = js.failed || 0,
        total = js.total || rowsObj.length;
      const dupl = js.zip_dup_count || 0;
      let html = `Total: <b>${total}</b><br>Berhasil: <b>${ok}</b> · Gagal: <b>${fail}</b>`;
      if (dupl)
        html += `<br>Foto duplikat di ZIP (BMN sama): <b>${dupl}</b> (diabaikan)`;

      await Swal.fire({
        icon: fail ? "warning" : "success",
        title: "Selesai",
        html,
      });

      // Tutup modal + reset UI
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
    // Download template CSV sederhana (dengan header)
    $("#btnTemplate")?.addEventListener("click", () => {
      const header =
        "Nama,Merek,Model,Serial No,Lokasi,Kapasitas BTU,Nomor BMN,Status,Tekanan Freon Terakhir,Amper Terakhir,Terakhir Service,Terakhir Perawatan\n";
      const sample =
        'AC Ruang Rapat,Daikin,FTKC25U,SN001,"Lantai 2",12000,"2.19.06.43.001 - 357",NORMAL,70,3.2,20-09-2025,10-09-2025\n';
      const blob = new Blob([header + sample], { type: "text/csv" });
      const a = document.createElement("a");
      a.href = URL.createObjectURL(blob);
      a.download = "template-ac.csv";
      a.click();
      setTimeout(() => URL.revokeObjectURL(a.href), 1500);
    });

    $("#bulkFile")?.addEventListener("change", async (e) => {
      await handleFile(e.target.files?.[0]);
    });
    $("#btnBulkSave")?.addEventListener("click", saveBulk);

    const bulkModal = document.getElementById("bulkModal");
    bulkModal?.addEventListener("hidden.bs.modal", resetBulkUI);
  });
})();
