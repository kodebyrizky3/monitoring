// public/assets/js-admin/ac-units.js  (v1.7.0)
(function () {
  "use strict";

  const tbody = document.getElementById("acTbody");
  const pagerEl = document.getElementById("acPager");
  const totalEl = document.getElementById("acTotal");
  const infoEl = document.getElementById("liveInfo");

  const qInput = document.getElementById("qInput");
  const stSel = document.getElementById("statusSelect");
  const perSel = document.getElementById("perPageSelect");
  const exportBtn = document.getElementById("btnExport");

  // Bulk selection UI
  const chkAll = document.getElementById("chkAll");
  const btnBulk = document.getElementById("btnBulkDelete");
  const selCount = document.getElementById("selCount");
  const bulkForm = document.getElementById("bulkDeleteForm");

  // simpan id terpilih lintas pagination & filter
  const selected = new Set();

  let inflightController = null;
  let reqSeq = 0;

  const state = {
    q: qInput?.value || "",
    status: stSel?.value || "",
    perPage: parseInt(perSel?.value || "10", 10),
    page: 1,
    pageCount: 1,
  };

  const escapeHtml = (s) =>
    (s || "").replace(
      /[&<>"']/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          '"': "&quot;",
          "'": "&#39;",
        }[c])
    );
  const debounce = (fn, ms) => {
    let t;
    return (...a) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...a), ms);
    };
  };
  const pageRange = (cur, total, width) => {
    const h = Math.floor(width / 2);
    let s = Math.max(1, cur - h);
    let e = Math.min(total, s + width - 1);
    s = Math.max(1, e - width + 1);
    const arr = [];
    for (let i = s; i <= e; i++) arr.push(i);
    return arr;
  };

  const setInfoLoading = () => {
    if (infoEl) infoEl.textContent = "Memuat…";
  };
  const setInfoCount = (rowsLen, total, page, perPage) => {
    if (!infoEl) {
      return;
    }
    if (!total) {
      infoEl.textContent = "";
      return;
    }
    const start = rowsLen ? (page - 1) * perPage + 1 : 0;
    const end = rowsLen ? (page - 1) * perPage + rowsLen : 0;
    infoEl.textContent =
      `Menampilkan ${start}–${end} dari ${total}` +
      (state.q ? " · Urut: terbaru" : "");
  };

  function updateCards(stats) {
    if (!stats) return;
    const set = (id, val) => {
      const el = document.getElementById(id);
      if (el) el.textContent = val ?? 0;
    };
    set("statTotal", stats.total);
    set("statRingan", stats.ringan);
    set("statBerat", stats.berat);
    set("statNormal", stats.normal);
  }

  function renderPager() {
    if (!pagerEl) {
      return;
    }
    const p = state.page,
      n = state.pageCount;
    if (n <= 1) {
      pagerEl.innerHTML = "";
      return;
    }

    const btn = (label, page, disabled = false, active = false) =>
      `<li class="page-item ${disabled ? "disabled" : ""} ${
        active ? "active" : ""
      }">
         <a class="page-link" href="#" data-page="${page}">${label}</a>
       </li>`;
    let html = `<ul class="pagination mb-0 justify-content-end">`;
    html += btn("&laquo;", Math.max(1, p - 1), p === 1);
    pageRange(p, n, 5).forEach((pg) => {
      html += btn(pg, pg, false, pg === p);
    });
    html += btn("&raquo;", Math.min(n, p + 1), p === n);
    html += `</ul>`;
    pagerEl.innerHTML = html;

    pagerEl.querySelectorAll("a.page-link").forEach((a) => {
      a.addEventListener("click", (e) => {
        e.preventDefault();
        const pg = parseInt(a.dataset.page, 10);
        if (
          !isNaN(pg) &&
          pg >= 1 &&
          pg <= state.pageCount &&
          pg !== state.page
        ) {
          state.page = pg;
          fetchList();
        }
      });
    });
  }

  function bindDeleteButtons() {
    document.querySelectorAll(".btn-delete").forEach((btn) => {
      btn.onclick = async (e) => {
        e.preventDefault();
        const url = btn.dataset.url;
        const name = btn.dataset.name || "perangkat";
        if (!url) return;

        const res = await Swal.fire({
          icon: "warning",
          title: "Hapus data?",
          html: `AC <b>${escapeHtml(
            name
          )}</b> akan dihapus beserta riwayat dan file terkait.`,
          showCancelButton: true,
          confirmButtonText: "Hapus",
          cancelButtonText: "Tidak",
          reverseButtons: true,
        });
        if (!res.isConfirmed) return;

        const form = document.getElementById("deleteForm");
        form.setAttribute("action", url);
        form.submit();
      };
    });
  }

  // ====== Selection helpers ======
  function updateBulkUI() {
    const n = selected.size;
    if (selCount) selCount.textContent = String(n);
    if (btnBulk) btnBulk.disabled = n === 0;
  }
  function syncHeaderCheck() {
    if (!chkAll || !tbody) return;
    const cbs = Array.from(tbody.querySelectorAll("input.row-check"));
    const all = cbs.length > 0 && cbs.every((cb) => cb.checked);
    chkAll.checked = all;
    chkAll.indeterminate =
      cbs.length > 0 && !all && cbs.some((cb) => cb.checked);
  }
  function bindRowChecks() {
    if (!tbody) return;
    tbody.querySelectorAll("input.row-check").forEach((cb) => {
      const id = parseInt(cb.value, 10);
      cb.checked = selected.has(id);
      cb.addEventListener("change", () => {
        if (cb.checked) selected.add(id);
        else selected.delete(id);
        syncHeaderCheck();
        updateBulkUI();
      });
    });
  }

  chkAll?.addEventListener("change", () => {
    const cbs = tbody ? tbody.querySelectorAll("input.row-check") : [];
    cbs.forEach((cb) => {
      cb.checked = chkAll.checked;
      const id = parseInt(cb.value, 10);
      if (chkAll.checked) selected.add(id);
      else selected.delete(id);
    });
    updateBulkUI();
  });

  btnBulk?.addEventListener("click", async () => {
    if (selected.size === 0) return;

    const res = await Swal.fire({
      icon: "warning",
      title: "Hapus data?",
      html: `Anda akan menghapus <b>${selected.size}</b> perangkat beserta foto, QR, riwayat perbaikan, dan tiketnya.`,
      showCancelButton: true,
      confirmButtonText: "Hapus Semua",
      cancelButtonText: "Tidak",
      reverseButtons: true,
    });
    if (!res.isConfirmed) return;

    // submit via hidden form (aman CSRF)
    if (!bulkForm) return;
    // bersihkan input lama
    Array.from(bulkForm.querySelectorAll('input[name="ids[]"]')).forEach((el) =>
      el.remove()
    );
    // tambah ids[]
    selected.forEach((id) => {
      const inp = document.createElement("input");
      inp.type = "hidden";
      inp.name = "ids[]";
      inp.value = String(id);
      bulkForm.appendChild(inp);
    });
    bulkForm.submit();
  });

  // ====== Fetch List ======
  async function fetchList() {
    const mySeq = ++reqSeq;
    inflightController?.abort();
    inflightController = new AbortController();

    const u = new URL(
      window.APP?.acSearch || "/admin/data-alat/ac/search",
      window.location.origin
    );
    u.searchParams.set("q", state.q || "");
    u.searchParams.set("status", state.status || "");
    u.searchParams.set("perPage", String(state.perPage || 10));
    u.searchParams.set("page", String(state.page || 1));

    setInfoLoading();

    try {
      const res = await fetch(u.toString(), {
        headers: { Accept: "application/json" },
        signal: inflightController.signal,
        cache: "no-store",
      });
      const json = await res.json();
      if (mySeq !== reqSeq) return;
      if (!res.ok || !json.success)
        throw new Error(json.message || "Gagal memuat");

      updateCards(json.stats);

      const rows = json.rows || [];
      const total = parseInt(json.total ?? 0, 10);
      state.pageCount = parseInt(json.pageCount ?? 1, 10) || 1;
      if (state.page > state.pageCount) {
        state.page = state.pageCount;
        return fetchList();
      }

      if (totalEl) totalEl.textContent = total;

      const badge = (st) => {
        const m = {
          NORMAL: "success",
          RUSAK_RINGAN: "warning",
          RUSAK_BERAT: "danger",
        };
        return `<span class="badge bg-${m[st] || "secondary"}">${escapeHtml(
          st || ""
        )}</span>`;
      };

      // render: with checkbox column + actions
      tbody.innerHTML = rows.length
        ? rows
            .map(
              (r) => `
        <tr>
          <td class="col-select">
            <input type="checkbox" class="form-check-input table-check row-check" value="${
              r.id
            }">
          </td>
          <td class="col-id">${r.id}</td>
          <td class="col-nama">${escapeHtml(r.nomor_unik || "")}</td>
          <td class="col-tipe">${escapeHtml(r.tipe_model || "")}</td>
          <td class="col-btu">${escapeHtml(r.kapasitas_btu || "-")}</td>
          <td class="col-bmn">${escapeHtml(r.bmn_no_display || "-")}</td>
          <td class="col-lokasi">${escapeHtml(r.lokasi || "")}</td>
          <td class="col-status">${badge(r.status_ac)}</td>
          <td class="text-end col-aksi">
            <div class="d-flex flex-wrap justify-content-end gap-1 action-wrap">
              <a class="btn btn-outline-secondary btn-sm" href="${
                r.show_url
              }" title="Detail"><i class="bi bi-eye"></i></a>
              <a class="btn btn-outline-primary btn-sm"   href="${
                r.edit_url
              }" title="Edit"><i class="bi bi-pencil"></i></a>
              <a class="btn btn-outline-success btn-sm"   href="${
                r.dl_qr_url
              }" title="Unduh QR"><i class="bi bi-download"></i></a>
              <button class="btn btn-outline-danger btn-sm btn-delete" data-url="${
                r.del_url
              }" data-name="${escapeHtml(
                r.nomor_unik || ""
              )}" title="Hapus"><i class="bi bi-trash"></i></button>
            </div>
          </td>
        </tr>
      `
            )
            .join("")
        : `<tr><td colspan="9" class="text-center text-muted">Tidak ada data.</td></tr>`;

      renderPager();
      bindDeleteButtons();
      bindRowChecks();
      syncHeaderCheck();
      updateBulkUI();

      setInfoCount(rows.length, total, state.page, state.perPage);
    } catch (err) {
      if (err.name === "AbortError") return;
      if (infoEl) infoEl.textContent = err.message || "Gagal memuat";
    }
  }

  // Filter handlers
  qInput &&
    qInput.addEventListener(
      "input",
      debounce(() => {
        state.q = qInput.value || "";
        state.page = 1;
        fetchList();
      }, 250)
    );
  stSel &&
    stSel.addEventListener("change", () => {
      state.status = stSel.value || "";
      state.page = 1;
      fetchList();
    });
  perSel &&
    perSel.addEventListener("change", () => {
      state.perPage = parseInt(perSel.value || "10", 10);
      state.page = 1;
      fetchList();
    });

  // Export (ikut filter aktif)
  exportBtn?.addEventListener("click", () => {
    const u = new URL(
      window.APP?.acExport || "/admin/data-alat/ac/export",
      window.location.origin
    );
    u.searchParams.set("q", state.q || "");
    u.searchParams.set("status", state.status || "");
    window.location.href = u.toString();
  });

  // Flash → Swal
  (function showFlash() {
    const ok = window.APP?.flash?.ok || "";
    const err = window.APP?.flash?.err || "";
    if (ok) {
      Swal.fire({
        icon: "success",
        title: "Berhasil",
        text: ok,
        timer: 1600,
        showConfirmButton: false,
        timerProgressBar: true,
      });
    } else if (err) {
      Swal.fire({ icon: "error", title: "Gagal", text: err });
    }
  })();

  // Init
  if (infoEl) infoEl.textContent = "";
  fetchList();
})();
