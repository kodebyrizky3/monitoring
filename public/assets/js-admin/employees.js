(function () {
  "use strict";

  const modalEl = document.getElementById("empModal");
  const modal = modalEl ? new bootstrap.Modal(modalEl) : null;
  const detailEl = document.getElementById("empDetailModal");
  const detailModal = detailEl ? new bootstrap.Modal(detailEl) : null;

  const form = document.getElementById("empForm");
  const titleEl = document.getElementById("empModalTitle");
  const tbody = document.getElementById("empTbody");
  const totalEl = document.getElementById("empTotal");
  const infoEl = document.getElementById("liveInfo");
  const pagerEl = document.getElementById("empPager");

  const qInput = document.getElementById("qInput");
  const perSel = document.getElementById("perPageSelect");
  const filterSel = document.getElementById("filterSelect");

  const btnAdd = document.getElementById("btnAdd");
  const btnExport = document.getElementById("btnExport");
  const togglePwd = document.getElementById("togglePwd");

  let currentId = null;
  let state = {
    q: qInput?.value || "",
    perPage: parseInt(perSel?.value || "10", 10),
    page: 1,
    pageCount: 1,
    filter: filterSel?.value || "all",
  };
  let inflightController = null;
  let reqSeq = 0;

  const AVATAR_PLACEHOLDER =
    window.APP && window.APP.avatarPlaceholder
      ? window.APP.avatarPlaceholder
      : "/assets/img/avatar-placeholder.png";

  function debounce(fn, ms) {
    let t;
    return (...a) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...a), ms);
    };
  }
  function escapeHtml(s) {
    return (s || "").replace(
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
  }
  function pageRange(current, total, width) {
    const half = Math.floor(width / 2);
    let start = Math.max(1, current - half);
    let end = Math.min(total, start + width - 1);
    start = Math.max(1, end - width + 1);
    const arr = [];
    for (let i = start; i <= end; i++) arr.push(i);
    return arr;
  }
  function setInfoLoading() {
    if (infoEl) infoEl.textContent = "Memuat…";
  }
  function setInfoCount(rowsLen) {
    if (!infoEl) return;
    if (!rowsLen) {
      infoEl.textContent = "";
      return;
    }
    const start = rowsLen ? (state.page - 1) * state.perPage + 1 : 0;
    const end = rowsLen ? (state.page - 1) * state.perPage + rowsLen : 0;
    infoEl.textContent =
      `Menampilkan ${start}–${end}` + (state.q ? " · Urut: relevansi" : "");
  }

  function syncAllCsrfInputs() {
    if (!window.CSRF?.name || !window.CSRF?.hash) return;
    document
      .querySelectorAll(`input[name="${window.CSRF.name}"]`)
      .forEach((i) => {
        i.value = window.CSRF.hash;
      });
  }

  function handleCsrfFromJson(json) {
    if (json && json.csrf) {
      window.CSRF.hash = json.csrf;
      syncAllCsrfInputs();
    }
  }

  function buildScope() {
    if (state.filter === "archived") return "archived";
    if (state.filter === "inactive") return "inactive";
    if (state.filter === "active") return "active";
    return "all";
  }

  async function fetchList() {
    const mySeq = ++reqSeq;
    inflightController?.abort();
    inflightController = new AbortController();
    if (state.page < 1) state.page = 1;

    const u = new URL(
      window.APP?.pegawaiSearch || "/admin/pegawai/search",
      window.location.origin
    );
    u.searchParams.set("q", state.q || "");
    u.searchParams.set("perPage", String(state.perPage || 10));
    u.searchParams.set("page", String(state.page || 1));
    u.searchParams.set("scope", buildScope());

    setInfoLoading();

    try {
      const res = await fetch(u.toString(), {
        headers: { Accept: "application/json" },
        signal: inflightController.signal,
        cache: "no-store",
      });
      const json = await res.json();

      handleCsrfFromJson(json);
      if (mySeq !== reqSeq) return;
      if (!res.ok || !json.success)
        throw new Error(json.message || "Gagal memuat data");

      state.pageCount = parseInt(json.pageCount ?? 1, 10) || 1;
      if (state.page > state.pageCount) {
        state.page = state.pageCount;
        return fetchList();
      }

      const rows = json.rows || [];

      tbody.innerHTML = rows.length
        ? rows
            .map((r, i) => {
              const no = (state.page - 1) * state.perPage + (i + 1);
              const aksiActive = `
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-info btn-detail" data-id="${
              r.id
            }"><i class="bi bi-card-text"></i></button>
            <button class="btn btn-outline-primary btn-edit" data-id="${
              r.id
            }"><i class="bi bi-pencil"></i></button>
            <button class="btn btn-outline-danger btn-delete" data-id="${
              r.id
            }" data-url="${r.delete_url}" data-name="${escapeHtml(
                r.nama
              )}"><i class="bi bi-archive"></i></button>
          </div>`;
              const aksiArchived = `
          <div class="btn-group btn-group-sm">
            <button class="btn btn-outline-success btn-restore" data-id="${r.id}"><i class="bi bi-arrow-counterclockwise"></i></button>
            <button class="btn btn-outline-info btn-detail" data-id="${r.id}"><i class="bi bi-card-text"></i></button>
          </div>`;
              const aksi =
                state.filter === "archived" ? aksiArchived : aksiActive;

              return `
          <tr>
            <td>${no}</td>
            <td><code>${escapeHtml(r.kode_pegawai)}</code></td>
            <td>${escapeHtml(r.nama)}</td>
            <td>${escapeHtml(r.username || "")}</td>
            <td>${
              r.is_active
                ? '<span class="badge bg-success">Ya</span>'
                : '<span class="badge bg-secondary">Tidak</span>'
            }</td>
            <td class="text-end">${aksi}</td>
          </tr>`;
            })
            .join("")
        : `<tr><td colspan="6" class="text-center text-muted">Tidak ada data.</td></tr>`;

      if (totalEl) totalEl.textContent = parseInt(json.total ?? 0, 10);

      renderPager();
      bindRowActions();
      setInfoCount(rows.length);
    } catch (e) {
      if (e.name === "AbortError") return;
      if (infoEl)
        infoEl.textContent = e && e.message ? e.message : "Gagal memuat";
    }
  }

  function renderPager() {
    if (!pagerEl) return;
    const p = state.page,
      n = state.pageCount;
    if (n <= 1) {
      pagerEl.innerHTML = "";
      return;
    }
    let html = `<ul class="pagination mb-0 justify-content-end">`;
    const btn = (label, page, disabled = false, active = false) =>
      `<li class="page-item ${disabled ? "disabled" : ""} ${
        active ? "active" : ""
      }"><a class="page-link" href="#" data-page="${page}">${label}</a></li>`;
    html += btn("&laquo;", Math.max(1, p - 1), p === 1);
    pageRange(p, n, 5).forEach((pg) => {
      html += btn(pg, pg, false, pg === p);
    });
    html += btn("&raquo;", Math.min(n, p + 1), p === n);
    html += `</ul>`;
    pagerEl.innerHTML = html;
    pagerEl.querySelectorAll("a.page-link").forEach((a) =>
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
      })
    );
  }

  function clearErrors() {
    form
      ?.querySelectorAll(".is-invalid")
      .forEach((e) => e.classList.remove("is-invalid"));
    form?.querySelectorAll("[data-err]").forEach((e) => (e.textContent = ""));
  }

  function showErrors(errs) {
    Object.entries(errs || {}).forEach(([k, v]) => {
      const i = form?.querySelector('[name="' + k + '"]');
      const h = form?.querySelector('[data-err="' + k + '"]');
      if (i) i.classList.add("is-invalid");
      if (h) h.textContent = v;
    });
  }

  function fillForm(d) {
    const map = {
      kode_pegawai: d?.kode_pegawai || "",
      nama: d?.nama || "",
      email: d?.email || "",
      no_telp: d?.no_telp || "",
      bidang_id: d?.bidang_id ?? "",
      is_active: d?.is_active ?? "1",
      username: d?.username || "",
      instagram_username: d?.instagram_username || "",
    };
    Object.keys(map).forEach((k) => {
      const el = form?.querySelector('[name="' + k + '"]');
      if (el) el.value = map[k];
    });
    if (form?.password) form.password.value = "";
  }

  function linkInstagram(u) {
    if (!u) return "—";
    const handle = u.replace(/^@/, "");
    const href = "https://instagram.com/" + encodeURIComponent(handle);
    return `<a href="${href}" target="_blank" rel="noopener">@${escapeHtml(
      handle
    )}</a>`;
  }

  function renderDetail(d) {
    const avatar = document.getElementById("empAvatar");
    const nameEl = document.getElementById("empName");
    const kodeEl = document.getElementById("empKode");
    const bidEl = document.getElementById("empBidang");

    if (avatar) avatar.src = d.foto_url || AVATAR_PLACEHOLDER;
    if (nameEl) nameEl.textContent = d.nama || "—";
    if (kodeEl)
      kodeEl.textContent = d.kode_pegawai
        ? `Kode: ${d.kode_pegawai}`
        : "Kode: —";
    if (bidEl)
      bidEl.textContent = d.bidang_nama
        ? `Bidang: ${d.bidang_nama}`
        : "Bidang: —";

    const grid = document.getElementById("empDetailGrid");
    if (!grid) return;

    const rows = [
      ["Username", d.username || "—"],
      ["Email", d.email || "—"],
      ["No. Telp", d.no_telp || "—"],
      ["Instagram", linkInstagram(d.instagram_username)],
      ["Aktif", d.is_active ? "Ya" : "Tidak"],
      ["Dihapus?", d.deleted_at ? "Ya" : "Tidak"],
      ["Dibuat", d.created_at || "—"],
      ["Diubah", d.updated_at || "—"],
    ];
    grid.innerHTML = rows
      .map(
        ([k, v]) => `
      <div class="col-5 text-muted">${escapeHtml(String(k))}</div>
      <div class="col-7">${v}</div>
    `
      )
      .join("");
  }

  function bindRowActions() {
    document.querySelectorAll(".btn-detail").forEach(
      (btn) =>
        (btn.onclick = async () => {
          const id = btn.dataset.id;
          if (!id) return;
          try {
            const url = (window.APP?.pegawai || "/admin/pegawai") + "/" + id;
            const res = await fetch(url, {
              headers: { Accept: "application/json" },
              cache: "no-store",
            });
            const json = await res.json();
            handleCsrfFromJson(json);
            if (!res.ok || !json.success)
              throw new Error(json.message || "Gagal memuat data");
            renderDetail(json.data || {});
            detailModal?.show();
          } catch (e) {
            if (window.Swal)
              Swal.fire({
                icon: "error",
                title: "Gagal",
                text: e.message || "Terjadi kesalahan",
              });
            else alert(e.message || "Terjadi kesalahan");
          }
        })
    );

    document.querySelectorAll(".btn-edit").forEach(
      (btn) =>
        (btn.onclick = async () => {
          currentId = btn.dataset.id;
          if (!currentId) return;
          titleEl && (titleEl.textContent = "Edit Pegawai");
          form?.querySelector("#_method")?.setAttribute("value", "PUT");
          clearErrors();
          try {
            const url =
              (window.APP?.pegawai || "/admin/pegawai") + "/" + currentId;
            const res = await fetch(url, {
              headers: { Accept: "application/json" },
              cache: "no-store",
            });
            const json = await res.json();
            handleCsrfFromJson(json);
            if (!res.ok || !json.success)
              throw new Error(json.message || "Gagal memuat data");
            fillForm(json.data);
            modal?.show();
            setTimeout(() => {
              document
                .querySelector("#empModal .modal-body")
                ?.scrollTo({ top: 0 });
            }, 100);
          } catch (e) {
            if (window.Swal)
              Swal.fire({
                icon: "error",
                title: "Gagal",
                text: e.message || "Terjadi kesalahan",
              });
            else alert(e.message || "Terjadi kesalahan");
          }
        })
    );

    document.querySelectorAll(".btn-delete").forEach(
      (btn) =>
        (btn.onclick = async (e) => {
          e.preventDefault();
          const url = btn.dataset.url;
          const name = btn.dataset.name || "pegawai";
          if (!url) return;

          if (window.Swal) {
            const result = await Swal.fire({
              icon: "warning",
              title: "Arsipkan data?",
              html: `Pegawai <b>${escapeHtml(
                name
              )}</b> akan diarsip (soft delete).`,
              showCancelButton: true,
              confirmButtonText: "Ya, arsipkan",
              cancelButtonText: "Batal",
              reverseButtons: true,
            });
            if (!result.isConfirmed) return;
          } else {
            if (!confirm("Arsipkan data?")) return;
          }

          const f = document.getElementById("deleteForm");
          if (!f) return;
          f.setAttribute("action", url);

          if (window.CSRF?.name && window.CSRF?.hash) {
            let tokenInput = f.querySelector(
              `input[name="${window.CSRF.name}"]`
            );
            if (!tokenInput) {
              tokenInput = document.createElement("input");
              tokenInput.type = "hidden";
              tokenInput.name = window.CSRF.name;
              f.appendChild(tokenInput);
            }
            tokenInput.value = window.CSRF.hash;
          }
          f.submit();
        })
    );

    document.querySelectorAll(".btn-restore").forEach(
      (btn) =>
        (btn.onclick = async () => {
          const id = btn.dataset.id;
          if (!id) return;
          const url =
            (window.APP?.pegawai || "/admin/pegawai") + "/" + id + "/restore";

          const fd = new FormData();
          if (window.CSRF?.name && window.CSRF?.hash)
            fd.set(window.CSRF.name, window.CSRF.hash);

          try {
            const res = await fetch(url, {
              method: "POST",
              body: fd,
              headers: { Accept: "application/json" },
              cache: "no-store",
            });
            const json = await res.json();
            handleCsrfFromJson(json);
            if (!res.ok || !json.success)
              throw new Error(json.message || "Gagal memulihkan data");
            if (window.Swal)
              Swal.fire({
                icon: "success",
                title: "Dipulihkan",
                timer: 1200,
                showConfirmButton: false,
              });
            fetchList();
          } catch (e) {
            if (window.Swal)
              Swal.fire({
                icon: "error",
                title: "Gagal",
                text: e.message || "Terjadi kesalahan",
              });
            else alert(e.message || "Terjadi kesalahan");
          }
        })
    );
  }

  qInput &&
    qInput.addEventListener(
      "input",
      debounce(() => {
        state.q = qInput.value || "";
        state.page = 1;
        fetchList();
      }, 250)
    );
  perSel &&
    perSel.addEventListener("change", () => {
      state.perPage = parseInt(perSel.value || "10", 10);
      state.page = 1;
      fetchList();
    });
  filterSel &&
    filterSel.addEventListener("change", () => {
      state.filter = filterSel.value || "all";
      state.page = 1;
      fetchList();
    });

  btnAdd &&
    btnAdd.addEventListener("click", () => {
      currentId = null;
      titleEl && (titleEl.textContent = "Tambah Pegawai");
      form?.reset();
      const m = form?.querySelector("#_method");
      if (m) m.value = "POST";
      clearErrors();
      modal?.show();
      setTimeout(() => {
        document.querySelector("#empModal .modal-body")?.scrollTo({ top: 0 });
      }, 100);
    });

  btnExport &&
    btnExport.addEventListener("click", () => {
      const u = new URL(
        window.APP?.pegawaiExport || "/admin/pegawai/export",
        window.location.origin
      );
      u.searchParams.set("q", state.q || "");
      window.location.href = u.toString();
    });

  togglePwd?.addEventListener("click", () => {
    const i = form?.password;
    if (!i) return;
    i.type = i.type === "password" ? "text" : "password";
  });

  form?.addEventListener("submit", async (e) => {
    e.preventDefault();
    clearErrors();

    // Validasi klien utk foto (maks 1MB)
    const fInput = form?.querySelector('input[name="foto"]');
    if (fInput && fInput.files && fInput.files[0]) {
      if (fInput.files[0].size > 1024 * 1024) {
        const err = form?.querySelector('[data-err="foto"]');
        if (err) err.textContent = "Ukuran foto maksimal 1 MB";
        fInput.classList.add("is-invalid");
        return;
      }
    }

    const fd = new FormData(form);
    if (window.CSRF?.name && window.CSRF?.hash)
      fd.set(window.CSRF.name, window.CSRF.hash);

    let url = window.APP?.pegawai || "/admin/pegawai";
    const isUpdate =
      form.querySelector("#_method")?.value === "PUT" && currentId;
    if (isUpdate) {
      url = url + "/" + currentId;
      fd.set("_method", "PUT");
    }

    try {
      const res = await fetch(url, {
        method: "POST",
        body: fd,
        headers: { Accept: "application/json" },
        cache: "no-store",
      });
      const json = await res.json();

      handleCsrfFromJson(json);

      if (!res.ok || !json.success) {
        if (json.errors) showErrors(json.errors);
        if (window.Swal)
          Swal.fire({
            icon: "error",
            title: "Gagal",
            text: json.message || "Validasi gagal",
          });
        else alert(json.message || "Validasi gagal");
        return;
      }

      modal?.hide();
      if (window.Swal)
        Swal.fire({
          icon: "success",
          title: isUpdate ? "Data diperbarui" : "Data ditambahkan",
          timer: 1200,
          showConfirmButton: false,
          timerProgressBar: true,
        });
      fetchList();
    } catch (err) {
      if (window.Swal)
        Swal.fire({
          icon: "error",
          title: "Kesalahan",
          text: err.message || "Terjadi kesalahan",
        });
      else alert(err.message || "Terjadi kesalahan");
    }
  });

  // init
  syncAllCsrfInputs();
  bindRowActions();
  if (infoEl) infoEl.textContent = "";
})();
