(function () {
  const $ = (id) => document.getElementById(id);
  const setText = (id, v) => {
    const el = $(id);
    if (el) el.textContent = v;
  };

  function badgeClass(status) {
    switch ((status || "").toUpperCase()) {
      case "NORMAL":
        return "text-bg-success";
      case "RUSAK_RINGAN":
        return "text-bg-warning";
      case "RUSAK_BERAT":
        return "text-bg-danger";
      default:
        return "text-bg-secondary";
    }
  }
  function splitTipeModel(s) {
    if (!s) return { merek: "—", model: "—" };
    const p = s.trim().split(/\s+/);
    if (p.length === 1) return { merek: p[0], model: "—" };
    return { merek: p[0], model: p.slice(1).join(" ") };
  }
  function absUrl(path) {
    if (!path) return null;
    try {
      if (/^https?:\/\//i.test(path)) return path;
      if (path.startsWith("/")) return location.origin + path;
      return location.origin + "/" + path.replace(/^\/+/, "");
    } catch {
      return path;
    }
  }
  function fmt(ts) {
    if (!ts) return "—";
    try {
      const d = new Date(ts.replace(" ", "T"));
      if (isNaN(d)) return ts;
      const pad = (n) => String(n).padStart(2, "0");
      const months = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "Mei",
        "Jun",
        "Jul",
        "Agu",
        "Sep",
        "Okt",
        "Nov",
        "Des",
      ];
      return `${pad(d.getDate())} ${
        months[d.getMonth()]
      } ${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
    } catch {
      return ts;
    }
  }
  function fmtGauge(val, unit) {
    if (val === null || val === undefined) return "—";
    const s = String(val).trim();
    if (!s) return "—";
    // kalau sudah ada huruf (mungkin ada unit), tampilkan apa adanya
    if (/[a-zA-Z]/.test(s)) return s;
    return s + " " + unit;
  }

  async function fetchDetail(token) {
    const url = `${location.origin}/ac/${encodeURIComponent(
      token
    )}?format=json`;
    const res = await fetch(url, {
      headers: { "X-Requested-With": "XMLHttpRequest" },
    });
    if (!res.ok)
      throw new Error((await res.text()) || `Gagal memuat (${res.status})`);
    const js = await res.json();
    if (!js || !js.ok || !js.ac) throw new Error("Payload tidak valid");
    return js;
  }

  function wirePhotoZoom(src) {
    const img = $("acPhoto");
    const sk = $("photoSkeleton");
    const btn = $("btnZoom");
    const mod = $("photoModal");
    const mi = $("modalPhoto");

    if (!src) {
      img?.classList.add("d-none");
      sk?.classList.add("d-none");
      btn?.classList.add("d-none");
      return;
    }

    img.onload = () => {
      img.classList.remove("d-none");
      sk?.classList.add("d-none");
      btn?.classList.remove("d-none");
    };
    img.onerror = () => {
      sk?.classList.add("d-none");
    };
    img.src = src;
    if (mi) mi.src = src;

    const open = () => {
      if (typeof bootstrap !== "undefined" && bootstrap.Modal)
        new bootstrap.Modal(mod).show();
      else window.open(src, "_blank");
    };
    img.addEventListener("click", open);
    btn?.addEventListener("click", open);
  }

  function renderAC(ac, tickets) {
    setText("namaAlat", ac.nomor_unik || ac.kode_qr || "Perangkat");
    setText("kodeQr", ac.kode_qr || "—");

    const tm = splitTipeModel(ac.tipe_model);
    setText("merek", tm.merek || "—");
    setText("modelOnly", tm.model || "—");
    setText("serialNo", ac.serial_no || "—");
    setText("noBmn", ac.bmn_no_display || "—");
    setText("lokasi", ac.lokasi || "—");

    setText(
      "lastPerawatan",
      ac.last_perawatan_at ? fmt(ac.last_perawatan_at) : "—"
    );
    setText("lastService", ac.last_service_at ? fmt(ac.last_service_at) : "—");

    setText("lastFreon", fmtGauge(ac.last_freon, "PSI"));
    setText("lastAmper", fmtGauge(ac.last_amper, "A"));

    const badge = $("badgeStatus");
    if (badge) {
      badge.className = `badge rounded-pill px-3 py-2 ${badgeClass(
        ac.status_ac
      )}`;
      badge.textContent = ac.status_ac || "NORMAL";
    }

    wirePhotoZoom(absUrl(ac.foto_url));

    const btnPerbaikan = $("btnPerbaikan");
    if (btnPerbaikan) {
      const tok = ac.kode_qr || ac.nomor_unik;
      btnPerbaikan.href = `${location.origin}/ac/${encodeURIComponent(
        tok
      )}/perbaikan`;
    }

    const list = $("laporanList");
    if (list) {
      list.innerHTML = "";
      if (!tickets || tickets.length === 0) {
        list.innerHTML =
          '<div class="list-group-item text-center text-muted py-4">Tidak ada laporan aktif.</div>';
      } else {
        tickets.forEach((t) => {
          const st = (t.status || "").toUpperCase();
          const m = { AKTIF: "RUSAK_RINGAN" }; // fallback sederhana
          const bcls = badgeClass(m[st] || st);
          const item = document.createElement("div");
          item.className = "list-group-item";
          item.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold">${t.judul || "Laporan"}</div>
                <div class="small text-muted">${t.deskripsi || ""}</div>
              </div>
              <span class="badge ${bcls}">${t.status || "AKTIF"}</span>
            </div>`;
          list.appendChild(item);
        });
      }
    }
  }

  document.addEventListener("DOMContentLoaded", async () => {
    const holder = $("__page");
    let token = holder?.dataset?.token || "";
    if (!token) {
      const seg = (location.pathname || "/").split("/").filter(Boolean);
      const idx = seg.indexOf("ac");
      if (idx >= 0 && seg[idx + 1]) token = decodeURIComponent(seg[idx + 1]);
    }
    if (!token) return;

    try {
      const data = await fetchDetail(token);
      renderAC(data.ac, data.tickets || []);
    } catch (err) {
      console.error(err);
      $("photoSkeleton")?.classList.add("d-none");
      const list = $("laporanList");
      if (list)
        list.innerHTML =
          '<div class="list-group-item text-danger">Gagal memuat data. Coba scan ulang.</div>';
    }

    // aktifkan tooltip bootstrap (opsional)
    try {
      document
        .querySelectorAll('[data-bs-toggle="tooltip"]')
        .forEach((el) => new bootstrap.Tooltip(el));
    } catch {}
  });
})();
