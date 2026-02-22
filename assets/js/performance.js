/**
 * Performance Dashboard Analytics & Charts
 * Pure Vanilla JS - No External Libraries
 */

const PerformanceCharts = {
  // 1. Line Chart: Score Trend
  drawLineChart(containerId, data, options = {}) {
    const container = document.getElementById(containerId);
    if (!container || !data || data.length === 0) return;

    const width = container.clientWidth;
    const height = 250;
    const padding = 40;

    const maxY = 100;
    const pts = data.map((d, i) => {
      const x = padding + i * ((width - padding * 2) / (data.length - 1 || 1));
      const y = height - padding - d.score * ((height - padding * 2) / maxY);
      return { x, y, score: d.score, label: d.label };
    });

    let pathD = `M ${pts[0].x} ${pts[0].y}`;
    for (let i = 1; i < pts.length; i++) {
      pathD += ` L ${pts[i].x} ${pts[i].y}`;
    }

    const svg = `
            <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                <!-- Grid Lines -->
                <line x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}" stroke="#eee" stroke-width="1" />
                <line x1="${padding}" y1="${padding}" x2="${padding}" y2="${height - padding}" stroke="#eee" stroke-width="1" />
                
                <!-- Axis Labels -->
                <text x="${padding - 10}" y="${height - padding + 5}" text-anchor="end" font-size="10" fill="#999">0</text>
                <text x="${padding - 10}" y="${padding + 5}" text-anchor="end" font-size="10" fill="#999">100</text>

                <!-- Data Line -->
                <path d="${pathD}" fill="none" stroke="var(--primary)" stroke-width="3" stroke-linejoin="round" />
                
                <!-- Data Points -->
                ${pts
                  .map(
                    (p) => `
                    <circle cx="${p.x}" cy="${p.y}" r="4" fill="white" stroke="var(--primary)" stroke-width="2" />
                `,
                  )
                  .join("")}
            </svg>
        `;
    container.innerHTML = svg;
  },

  // 2. Bar Chart: Subject-wise Performance
  drawBarChart(containerId, data) {
    const container = document.getElementById(containerId);
    if (!container || !data || data.length === 0) return;

    const width = container.clientWidth;
    const height = 250;
    const padding = 40;
    const barGap = 20;
    const barWidth =
      (width - padding * 2 - (data.length - 1) * barGap) / data.length;

    const maxY = 100;

    const svg = `
            <svg width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
                <line x1="${padding}" y1="${height - padding}" x2="${width - padding}" y2="${height - padding}" stroke="#eee" />
                
                ${data
                  .map((d, i) => {
                    const barH = d.score * ((height - padding * 2) / maxY);
                    const x = padding + i * (barWidth + barGap);
                    const y = height - padding - barH;
                    return `
                        <rect x="${x}" y="${y}" width="${barWidth}" height="${barH}" fill="${d.color || "var(--sky)"}" rx="4">
                            <title>${d.label}: ${d.score}%</title>
                        </rect>
                        <text x="${x + barWidth / 2}" y="${height - padding + 15}" text-anchor="middle" font-size="10" fill="#999">${d.label.substring(0, 5)}...</text>
                    `;
                  })
                  .join("")}
            </svg>
        `;
    container.innerHTML = svg;
  },

  // 3. Doughnut Chart: ratio
  drawDoughnut(containerId, percentage, color = "var(--primary)") {
    const container = document.getElementById(containerId);
    if (!container) return;

    const size = 150;
    const center = size / 2;
    const radius = 60;
    const circ = 2 * Math.PI * radius;
    const offset = circ - (percentage / 100) * circ;

    const svg = `
            <svg width="${size}" height="${size}" viewBox="0 0 ${size} ${size}">
                <circle cx="${center}" cy="${center}" r="${radius}" fill="none" stroke="#f0f0f0" stroke-width="12" />
                <circle cx="${center}" cy="${center}" r="${radius}" fill="none" stroke="${color}" stroke-width="12" 
                    stroke-dasharray="${circ}" stroke-dashoffset="${offset}" 
                    transform="rotate(-90 ${center} ${center})" stroke-linecap="round" />
                <text x="${center}" y="${center + 5}" text-anchor="middle" font-size="24" font-weight="800" fill="var(--text)">${Math.round(percentage)}%</text>
            </svg>
        `;
    container.innerHTML = svg;
  },
};

// Search & Filter Logic
function initPerformanceFilters() {
  const searchInput = document.getElementById("hist-search");
  const tableRows = document.querySelectorAll("#quiz-history-body tr");

  if (searchInput) {
    searchInput.addEventListener("input", (e) => {
      const val = e.target.value.toLowerCase();
      tableRows.forEach((row) => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(val) ? "" : "none";
      });
    });
  }
}

// Export functions
function exportCSV() {
  const rows = document.querySelectorAll("table tr");
  let csv = [];
  for (const row of rows) {
    let cols = row.querySelectorAll("td, th");
    let csvRow = [];
    for (const col of cols) {
      csvRow.push('"' + col.innerText.replace(/"/g, '""') + '"');
    }
    csv.push(csvRow.join(","));
  }

  const csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
  const encodedUri = encodeURI(csvContent);
  const link = document.createElement("a");
  link.setAttribute("href", encodedUri);
  link.setAttribute("download", "performance_report.csv");
  document.body.appendChild(link);
  link.click();
}

function printReport() {
  window.print();
}

document.addEventListener("DOMContentLoaded", () => {
  initPerformanceFilters();
});
