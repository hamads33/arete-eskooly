(function () {
  "use strict";

  if (typeof Chart === "undefined") {
    console.warn("Chart.js is unavailable; dashboard charts were not rendered.");
    return;
  }

  const data = window.dashboardData;
  const fontFamily = 'Inter, "Segoe UI", Roboto, Helvetica, Arial, sans-serif';
  const gridColor = "rgba(236, 229, 232, .82)";
  const tickColor = "#8B8185";

  Chart.defaults.font.family = fontFamily;
  Chart.defaults.color = tickColor;
  Chart.defaults.animation.duration = 650;

  const currency = (value) => "$" + Number(value).toLocaleString("en-US");
  const sharedScales = {
    x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 } } },
    y: { beginAtZero: true, grid: { color: gridColor, drawTicks: false }, border: { display: false }, ticks: { padding: 10, font: { size: 10 } } }
  };

  const incomeCanvas = document.getElementById("incomeExpenseChart");
  let incomeChart;
  if (incomeCanvas) {
    const context = incomeCanvas.getContext("2d");
    const gradient = context.createLinearGradient(0, 0, 0, 280);
    gradient.addColorStop(0, "rgba(122, 22, 50, .18)");
    gradient.addColorStop(1, "rgba(122, 22, 50, 0)");
    incomeChart = new Chart(context, {
      type: "line",
      data: {
        labels: data.months,
        datasets: [
          { label: "Income", data: data.income, borderColor: "#7A1632", backgroundColor: gradient, borderWidth: 2.4, fill: true, tension: .38, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: "#7A1632" },
          { label: "Expense", data: data.expense, borderColor: "#D9A0AF", backgroundColor: "transparent", borderWidth: 2.2, fill: false, tension: .38, pointRadius: 0, pointHoverRadius: 5, pointHoverBackgroundColor: "#D9A0AF" }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false, interaction: { mode: "index", intersect: false },
        plugins: { legend: { display: false }, tooltip: { backgroundColor: "#3B0B18", padding: 11, displayColors: true, callbacks: { label: (item) => ` ${item.dataset.label}: ${currency(item.raw)}` } } },
        scales: { x: sharedScales.x, y: { ...sharedScales.y, ticks: { ...sharedScales.y.ticks, callback: (value) => "$" + value / 1000 + "k" } } }
      }
    });
  }

  const feeCanvas = document.getElementById("feeStatusChart");
  if (feeCanvas) {
    new Chart(feeCanvas, {
      type: "doughnut",
      data: { labels: ["Collected", "Remaining"], datasets: [{ data: [data.feeCollected, 100 - data.feeCollected], backgroundColor: ["#7A1632", "#F2E9EC"], borderWidth: 0, hoverOffset: 3 }] },
      options: { responsive: true, maintainAspectRatio: false, cutout: "76%", plugins: { legend: { display: false }, tooltip: { callbacks: { label: (item) => ` ${item.label}: ${item.raw}%` } } } }
    });
  }

  const strengthCanvas = document.getElementById("classStrengthChart");
  if (strengthCanvas) {
    new Chart(strengthCanvas, {
      type: "bar",
      data: { labels: data.classLabels, datasets: [{ label: "Students", data: data.classStrength, backgroundColor: ["#EBC7D1", "#E3B0BF", "#D593A8", "#C87591", "#B95675", "#A93A59", "#941C42", "#7A1632"], borderRadius: 6, borderSkipped: false, maxBarThickness: 34 }] },
      options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false }, tooltip: { backgroundColor: "#3B0B18", padding: 10 } }, scales: { x: sharedScales.x, y: { ...sharedScales.y, suggestedMax: 200 } } }
    });
  }

  document.getElementById("incomeRange")?.addEventListener("change", (event) => {
    if (!incomeChart) return;
    const count = Number(event.target.value);
    incomeChart.data.labels = data.months.slice(-count);
    incomeChart.data.datasets[0].data = data.income.slice(-count);
    incomeChart.data.datasets[1].data = data.expense.slice(-count);
    incomeChart.update();
  });
})();
