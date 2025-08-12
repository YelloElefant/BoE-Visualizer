let paperList = [];
let currentPaperCode = null;
let myChart;

// Parse CSV data (finds the first column as label, last numeric column as value)
function parseCSV(data) {
  const lines = data.trim().split("\n");
  if (lines.length < 2) return { labels: [], values: [] };
  console.log(data)
  // Support both quoted and unquoted CSV
  const headers = lines[0].split(/,(?=(?:[^"]*"[^"]*")*[^"]*$)/);

  // Exclude columns that are names, id, email, timestamp, or 'Last downloaded from this paper' (handle quotes and whitespace)
  const exclude = /^"?(first name|last name|ID number|email address|timestamp|last downloaded from this paper)"?\s*$/i;

  // converts the headers to a object of headername and its index then filter for the ones we want
  const firstDataRow = lines[1].split(/,(?=(?:[^"]*"[^"]*")*[^"]*$)/);
  console.log(headers)
  const assessmentCols = headers
    .map((h, i) => ({ h, i }))
    .filter(obj => {
      if (exclude.test(obj.h)) return false;
      const val = parseFloat(firstDataRow[obj.i]?.replace(/"/g, ''));
      return !isNaN(val);
    });

  // parse the rest of the rows and sum the values for each assessment column
  if (assessmentCols.length === 0) return { labels: [], values: [] };
  const sums = Array(assessmentCols.length).fill(0);
  let count = 0;
  for (let i = 1; i < lines.length; i++) {
    const row = lines[i].split(/,(?=(?:[^"]*"[^"]*")*[^"]*$)/);
    if (row.length < headers.length) continue;
    assessmentCols.forEach((col, idx) => {
      const val = parseFloat(row[col.i].replace(/"/g, ''));
      if (!isNaN(val)) sums[idx] += val;
    });
    count++;
  }

  const labels = assessmentCols.map(col => col.h.replace(/"/g, ''));
  const values = sums.map(sum => count > 0 ? sum / count : 0);
  return { labels, values };
}

// Render or update Chart
function renderChart(csv, title) {
  const { labels, values } = parseCSV(csv);
  document.querySelector("h1").textContent = title || "Paper Data";
  const ctx = document.getElementById("myChart").getContext("2d");
  if (myChart) {
    myChart.data.labels = labels;
    myChart.data.datasets[0].data = values;
    myChart.update();
  } else {
    myChart = new Chart(ctx, {
      type: "bar",
      data: {
        labels: labels,
        datasets: [
          {
            label: "Data",
            data: values,
            backgroundColor: "#b0bfffff",
            borderColor: "#3a56d4",
            borderWidth: 1,
          },
        ],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
          },
        },
      },
    });
  }
}

// Load selected paper
function loadDataset() {
  const select = document.getElementById("dataset");
  const idx = select.selectedIndex;
  if (idx < 0 || !paperList[idx]) return;
  const paper = paperList[idx];
  currentPaperCode = paper.paper_code;
  fetchCSVAndRender(paper.paper_code, paper.filename);
}

function fetchCSVAndRender(paperCode, filename) {
  fetch(`api/getData.php?paperCode=${encodeURIComponent(paperCode)}`)
    .then(r => r.json())
    .then(data => {
      if (data.success && data.csv_content) {
        renderChart(data.csv_content, `${paperCode}`);
      } else {
        renderChart('', 'No data');
      }
    })
    .catch(() => renderChart('', 'Error loading data'));
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
    .then(list => {
      if (!Array.isArray(list)) throw new Error();
      paperList = list;
      const select = document.getElementById("dataset");
      select.innerHTML = '';
      paperList.forEach((paper, i) => {
        const opt = document.createElement('option');
        opt.value = paper.paper_code;
        opt.textContent = `${paper.paper_code}`;
        select.appendChild(opt);
      });
      if (paperList.length > 0) {
        select.selectedIndex = 0;
        loadDataset();
      } else {
        renderChart('', 'No papers uploaded');
      }
    })
    .catch(() => {
      renderChart('', 'Error loading paper list');
    });
});
