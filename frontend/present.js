// Sample datasets
const datasets = {
  sample1: {
    csv: `Category,Value
A,30
B,50
C,20
D,80
E,60`,
    title: "Sample Data 1",
  },
  sample2: {
    csv: `Category,Value
Red,45
Green,75
Blue,30
Yellow,60
Purple,25`,
    title: "Sample Data 2",
  },
  sample3: {
    csv: `Category,Value
January,120
February,85
March,110
April,95
May,130`,
    title: "Sample Data 3",
  },
  sample4: {
    csv: `Category,Value
North,42
South,68
East,55
West,37
Central,61`,
    title: "Sample Data 4",
  },
};

let currentDataset = "sample1";
let myChart;

// Parse CSV data
function parseCSV(data) {
  const lines = data.trim().split("\n");
  const headers = lines[0].split(",");
  const labels = [];
  const values = [];

  for (let i = 1; i < lines.length; i++) {
    const row = lines[i].split(",");
    labels.push(row[0]);
    values.push(parseFloat(row[1]));
  }

  return { labels, values };
}

// Render or update Chart
function renderChart() {
  const dataset = datasets[currentDataset];
  const { labels, values } = parseCSV(dataset.csv);
  document.querySelector("h1").textContent = dataset.title;

  const ctx = document.getElementById("myChart").getContext("2d");

  if (myChart) {
    // Update existing chart
    myChart.data.labels = labels;
    myChart.data.datasets[0].data = values;
    myChart.update();
  } else {
    // Create new chart
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

// Load selected dataset
function loadDataset() {
  currentDataset = document.getElementById("dataset").value;
  renderChart();
}

// Navigate between datasets
function navigate(direction) {
  const options = document.getElementById("dataset").options;
  const currentIndex = Array.from(options).findIndex(
    (opt) => opt.value === currentDataset
  );
  let newIndex = currentIndex + direction;

  // Wrap around if at beginning or end
  if (newIndex < 0) newIndex = options.length - 1;
  if (newIndex >= options.length) newIndex = 0;

  document.getElementById("dataset").selectedIndex = newIndex;
  currentDataset = options[newIndex].value;
  renderChart();
}

function goBack() {
  window.location.href = "home.html";
}

// Initialize with first dataset
document.addEventListener("DOMContentLoaded", () => {
  renderChart();
});
