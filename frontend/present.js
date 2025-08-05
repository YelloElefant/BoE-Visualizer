// Fake CSV data
const csvData = `Category,Value
A,30
B,50
C,20
D,80
E,60`;

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

// Render Chart
const { labels, values } = parseCSV(csvData);
const ctx = document.getElementById("myChart").getContext("2d");
const myChart = new Chart(ctx, {
  type: "bar",
  data: {
    labels: labels,
    datasets: [
      {
        label: "Sample Data",
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

function goBack() {
  window.location.href = "home.html";
}
