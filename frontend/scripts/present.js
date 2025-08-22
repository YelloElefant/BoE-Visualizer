let paperList = [];
let currentPaperCode = null;
let myChart;
let chartType = 'scoreDistribution'; // 'scoreDistribution', 'gradeDistribution', 'statistics'

// Parse data from the new database API
function parseData(data, type = 'scoreDistribution') {
  if (!data || !data.success) return { labels: [], values: [] };

  switch (type) {
    case 'scoreDistribution':
      return parseScoreDistribution(data);
    case 'gradeDistribution':
      return parseGradeDistribution(data);
    case 'statistics':
      return parseStatistics(data);
    default:
      return { labels: [], values: [] };
  }
}

function parseScoreDistribution(data) {
  if (!data.csv_content) return { labels: [], values: [] };

  // Extract scores from CSV and create histogram
  const lines = data.csv_content.trim().split("\n");
  if (lines.length < 2) return { labels: [], values: [] };

  const scores = [];
  for (let i = 1; i < lines.length; i++) {
    const row = lines[i].split(',');
    const score = parseFloat(row[5]); // Score is in column 5
    if (!isNaN(score)) scores.push(score);
  }

  if (scores.length === 0) return { labels: [], values: [] };

  // Create score bins (0-9, 10-19, 20-29, etc.)
  const bins = Array(10).fill(0);
  const binLabels = [];
  for (let i = 0; i < 10; i++) {
    binLabels.push(`${i * 10}-${i * 10 + 9}`);
  }

  scores.forEach(score => {
    const binIndex = Math.min(Math.floor(score / 10), 9);
    bins[binIndex]++;
  });

  return { labels: binLabels, values: bins };
}

function parseGradeDistribution(data) {
  if (!data.statistics || !data.statistics.grade_distribution) {
    return { labels: [], values: [] };
  }

  let gradeData = data.statistics.grade_distribution;

  // If grade_distribution is a string, parse it as JSON
  if (typeof gradeData === 'string') {
    try {
      gradeData = JSON.parse(gradeData);
    } catch (e) {
      console.error('Error parsing grade distribution JSON:', e);
      return { labels: [], values: [] };
    }
  }

  const labels = Object.keys(gradeData);
  const values = Object.values(gradeData);

  return { labels, values };
}

function parseStatistics(data) {
  if (!data.statistics) return { labels: [], values: [] };

  const stats = data.statistics;
  const labels = ['Total Students', 'Average Score', 'Highest Score', 'Lowest Score', 'Pass Rate (%)'];
  const values = [
    stats.total_students || 0,
    parseFloat(stats.average_score) || 0,
    parseFloat(stats.highest_score) || 0,
    parseFloat(stats.lowest_score) || 0,
    parseFloat(stats.pass_rate) || 0
  ];

  return { labels, values };
}

function getShowGrid() {
  const v = localStorage.getItem('boe.showGrid');
  return v === null ? true : v === 'true';
}

// Render or update Chart
function renderChart(data, title) {
  const { labels, values } = parseData(data, chartType);

  // Update title with paper info
  let chartTitle = title || "Paper Data";
  if (data && data.statistics) {
    chartTitle += ` (${data.statistics.total_students} students, Avg: ${data.statistics.average_score || 'N/A'})`;
  }
  document.querySelector("h1").textContent = chartTitle;

  const ctx = document.getElementById("myChart").getContext("2d");

  // Determine chart type and colors based on current view
  let chartTypeConfig = 'bar';
  let backgroundColor = "#b0bfffff";
  let borderColor = "#3a56d4";

  if (chartType === 'gradeDistribution') {
    chartTypeConfig = 'pie';
    backgroundColor = [
      '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0',
      '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
    ];
    borderColor = '#fff';
  }

  if (myChart) {
    myChart.destroy();
  }

  const config = {
    type: chartTypeConfig,
    data: {
      labels: labels,
      datasets: [
        {
          label: getDatasetLabel(),
          data: values,
          backgroundColor: backgroundColor,
          borderColor: borderColor,
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: chartType === 'gradeDistribution'
        },
        title: {
          display: true,
          text: getChartTitle()
        }
      }
    }
  };

  if (chartTypeConfig === 'bar') {
  const showGrid = getShowGrid();
  config.options.scales = {
    y: {
      beginAtZero: true,
      grid: { display: showGrid },
      title: { display: true, text: getYAxisLabel() }
    },
    x: {
      grid: { display: showGrid },
      title: { display: true, text: getXAxisLabel() }
    }
  };
}

  myChart = new Chart(ctx, config);
}

function getDatasetLabel() {
  switch (chartType) {
    case 'scoreDistribution': return 'Number of Students';
    case 'gradeDistribution': return 'Students by Grade';
    case 'statistics': return 'Value';
    default: return 'Data';
  }
}

function getChartTitle() {
  switch (chartType) {
    case 'scoreDistribution': return 'Score Distribution';
    case 'gradeDistribution': return 'Grade Distribution';
    case 'statistics': return 'Paper Statistics';
    default: return 'Paper Data';
  }
}

function getYAxisLabel() {
  switch (chartType) {
    case 'scoreDistribution': return 'Number of Students';
    case 'statistics': return 'Value';
    default: return 'Count';
  }
}

function getXAxisLabel() {
  switch (chartType) {
    case 'scoreDistribution': return 'Score Range';
    case 'statistics': return 'Metric';
    default: return 'Category';
  }
}

// Load selected paper
function loadDataset() {
  const select = document.getElementById("dataset");
  const idx = select.selectedIndex;
  if (idx < 0 || !paperList[idx]) return;
  const paper = paperList[idx];
  currentPaperCode = paper.paper_code;
  fetchDataAndRender(paper.paper_code);
}

function fetchDataAndRender(paperCode) {
  fetch(`api/getData.php?paperCode=${encodeURIComponent(paperCode)}`)
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        renderChart(data, paperCode);
      } else {
        renderChart(null, 'No data: ' + (data.error || 'Unknown error'));
      }
    })
    .catch(err => {
      console.error('Error fetching data:', err);
      renderChart(null, 'Error loading data');
    });
}

// Chart type switching functions
function switchChartType(newType) {
  if (chartType !== newType) {
    chartType = newType;

    // Update button states
    document.querySelectorAll('.chart-type-btn').forEach(btn => {
      btn.classList.remove('active');
    });
    document.getElementById(newType + '-btn').classList.add('active');

    if (currentPaperCode) {
      fetchDataAndRender(currentPaperCode);
    }
  }
}

// Navigate between papers
function navigate(direction) {
  const select = document.getElementById("dataset");
  let idx = select.selectedIndex;
  if (idx === -1) idx = 0;
  let newIndex = idx + direction;
  if (newIndex < 0) newIndex = paperList.length - 1;
  if (newIndex >= paperList.length) newIndex = 0;
  select.selectedIndex = newIndex;
  loadDataset();
}

function goBack() {
  window.location.href = "home.html";
}

// Fetch paper list and initialize
document.addEventListener("DOMContentLoaded", () => {
  fetch("api/getPaperList.php")
    .then(r => r.json())
    .then(data => {
      if (data.success && Array.isArray(data.papers)) {
        paperList = data.papers;
        const select = document.getElementById("dataset");
        select.innerHTML = '';
        paperList.forEach((paper, i) => {
          const opt = document.createElement('option');
          opt.value = i; // Use index as value
          opt.textContent = `${paper.paper_code} - ${paper.paper_name || 'Unnamed Paper'}`;
          select.appendChild(opt);
        });
        if (paperList.length > 0) {
          select.selectedIndex = 0;
          loadDataset();
        } else {
          renderChart(null, 'No papers uploaded');
        }
      } else {
        throw new Error(data.error || 'Invalid response format');
      }
    })
    .catch(err => {
      console.error('Error loading paper list:', err);
      renderChart(null, 'Error loading paper list');
    });
});
