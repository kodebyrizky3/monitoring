(() => {
  const meta = document.querySelector('meta[name="base-url"]');
  const BASE_URL = meta ? meta.content : '/';
  const canvas   = document.getElementById('kendalaChart');

  // Kalau elemen tidak ada, stop.
  if (!canvas) return;

  // Pastikan Chart.js sudah ada
  if (typeof Chart === 'undefined') {
    console.error('Chart.js belum dimuat.');
    return;
  }

  async function loadChart() {
    const res  = await fetch(`${BASE_URL}dashboard/chart-data`);
    const json = await res.json();

    new Chart(canvas, {
      type: 'line',
      data: {
        labels: json.labels,
        datasets: [{
          label: `Jumlah Kendala ${json.year}`,
          data: json.data,
          fill: true,
          tension: 0.35
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: { y: { beginAtZero: true, ticks: { precision: 0 } } }
      }
    });
  }

  document.addEventListener('DOMContentLoaded', loadChart);
})();
