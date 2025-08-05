function goBack() {
  window.location.href = "home.html";
}

const dropArea = document.getElementById("dropArea");
const fileInput = document.getElementById("fileInput");
const csvInput = document.getElementById("csvInput");
const browseButton = document.querySelector(".browse-button");
const lineCountDisplay = document.querySelector(".line-count");
const paperCodeInput = document.getElementById("paperCode");
const modal = document.getElementById("uploadModal");
const closeModal = document.querySelector(".close");

document.addEventListener("DOMContentLoaded", function () {
  // Prevent default drag behaviors
  ["dragenter", "dragover", "dragleave", "drop"].forEach((eventName) => {
    dropArea.addEventListener(eventName, preventDefaults, false);
    document.body.addEventListener(eventName, preventDefaults, false);
  });

  // Highlight drop area when item is dragged over it
  ["dragenter", "dragover"].forEach((eventName) => {
    dropArea.addEventListener(eventName, highlight, false);
  });

  ["dragleave", "drop"].forEach((eventName) => {
    dropArea.addEventListener(eventName, unhighlight, false);
  });

  // Handle dropped files
  dropArea.addEventListener("drop", handleDrop, false);

  // Handle file input via button
  browseButton.addEventListener("click", () => fileInput.click());
  fileInput.addEventListener("change", handleFiles);

  // Update line count when text changes
  csvInput.addEventListener("input", updateLineCount);

  // Close modal
  closeModal.addEventListener("click", () => (modal.style.display = "none"));
  window.addEventListener("click", (event) => {
    if (event.target === modal) {
      modal.style.display = "none";
    }
  });

  function preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  function highlight() {
    dropArea.classList.add("highlight");
  }

  function unhighlight() {
    dropArea.classList.remove("highlight");
  }

  function handleDrop(e) {
    const dt = e.dataTransfer;
    const files = dt.files;
    handleFiles({ target: { files } });
  }

  function handleFiles(e) {
    const files = e.target.files;
    if (files.length) {
      const file = files[0];

      // Extract paper code from filename
      const fileName = file.name;
      const codeMatch = fileName.match(
        /^([A-Za-z0-9]+-[0-9]+[A-Z]? \([\w]+\))/
      );
      if (codeMatch && codeMatch[1]) {
        paperCodeInput.value = codeMatch[1];
      } else {
        const fallbackMatch = fileName.split(" Grades")[0];
        paperCodeInput.value = fallbackMatch || fileName.split(".")[0];
      }

      const reader = new FileReader();
      reader.onload = function (e) {
        csvInput.value = e.target.result;
        updateLineCount();
      };
      reader.readAsText(file);
    }
  }
});

function updateLineCount() {
  const lines = csvInput.value.split("\n").filter((line) => line.trim() !== "");
  if (lines.length === 0) {
    lineCountDisplay.textContent = "Results: 0";
    return;
  }
  lineCountDisplay.textContent = `Results: ${lines.length - 1}`;
}

function previewData() {
  const csvInput = document.getElementById("csvInput");
  const paperCodeInput = document.getElementById("paperCode");

  if (!csvInput.value.trim()) {
    alert("Please enter CSV data or upload a file");
    return;
  }

  if (!paperCodeInput.value.trim()) {
    alert("Please enter a paper code");
    return;
  }

  // Set paper code in preview header
  document.getElementById("previewPaperCode").textContent =
    paperCodeInput.value.trim();

  const csvData = csvInput.value.trim();
  const lines = csvData.split("\n");
  const headers = lines[0].split(",");
  const dataRows = lines.slice(1).filter((row) => row.trim() !== "");

  // Build the preview table
  const headerRow = document.getElementById("headerRow");
  const tableBody = document.getElementById("tableBody");

  // Clear existing content
  headerRow.innerHTML = "";
  tableBody.innerHTML = "";

  // Add headers
  headers.forEach((header) => {
    const th = document.createElement("th");
    th.textContent = header.trim();
    headerRow.appendChild(th);
  });

  // Add data rows with editable cells
  dataRows.forEach((row, rowIndex) => {
    const tr = document.createElement("tr");
    const cells = row.split(",");

    cells.forEach((cell, cellIndex) => {
      const td = document.createElement("td");
      const input = document.createElement("input");
      input.type = "text";
      input.value = cell.trim();
      input.dataset.row = rowIndex;
      input.dataset.col = cellIndex;

      // Store original value for change detection
      input.dataset.original = cell.trim();
      input.addEventListener("change", handleCellEdit);

      td.appendChild(input);
      tr.appendChild(td);
    });

    tableBody.appendChild(tr);
  });

  // Update row count
  document.getElementById("rowCount").textContent = dataRows.length;

  // Show preview section (full screen)
  document.getElementById("previewSection").style.display = "flex";
  document.getElementById("uploadSection").style.display = "none";
}

function backToEdit() {
  document.getElementById("previewSection").style.display = "none";
  document.getElementById("uploadSection").style.display = "block";
}

function handleCellEdit(e) {
  const input = e.target;
  const originalValue = input.dataset.original;
  const currentValue = input.value;

  // Highlight if changed
  if (currentValue !== originalValue) {
    input.style.backgroundColor = "#fff3cd";
  } else {
    input.style.backgroundColor = "";
  }
}

async function submitCsv() {
  const paperCodeInput = document.getElementById("paperCode");
  const modal = document.getElementById("uploadModal");

  try {
    // Reconstruct CSV from edited table
    const headers = Array.from(document.querySelectorAll("#headerRow th")).map(
      (th) => th.textContent
    );
    const rows = Array.from(document.querySelectorAll("#tableBody tr"));

    let csvContent = headers.join(",") + "\n";

    rows.forEach((row) => {
      const cells = Array.from(row.querySelectorAll("input")).map(
        (input) => input.value
      );
      csvContent += cells.join(",") + "\n";
    });

    const formData = new FormData();
    formData.append("csvData", csvContent);
    formData.append("paperCode", paperCodeInput.value.trim());

    const response = await fetch("/api/upload.php", {
      method: "POST",
      body: formData,
    });

    const responseData = await response.json();

    if (response.ok && responseData.success) {
      alert(
        `Upload successful!\nFile: ${responseData.filename}\nPaper Code: ${responseData.paper_code}\nLines processed: ${responseData.line_count}`
      );
      modal.style.display = "block";

      // Reset the form
      document.getElementById("csvInput").value = "";
      paperCodeInput.value = "";
      document.getElementById("previewSection").style.display = "none";
      document.getElementById("uploadSection").style.display = "block";
      updateLineCount();
    } else {
      throw new Error(responseData.error || "Upload failed");
    }
  } catch (error) {
    alert("Error during upload: " + error.message);
  }
}
