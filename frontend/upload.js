function goBack() {
  window.location.href = "home.html";
}

document.addEventListener("DOMContentLoaded", function () {
  const dropArea = document.getElementById("dropArea");
  const fileInput = document.getElementById("fileInput");
  const csvInput = document.getElementById("csvInput");
  const browseButton = document.querySelector(".browse-button");
  const lineCountDisplay = document.querySelector(".line-count");
  const paperCodeInput = document.getElementById("paperCode");
  const modal = document.getElementById("uploadModal");
  const closeModal = document.querySelector(".close");

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

      // Extract paper code from filename (format like "COMPX123-22A (HAM) Grades-20240814_0336-comma_separated")
      const fileName = file.name;
      const codeMatch = fileName.match(
        /^([A-Za-z0-9]+-[0-9]+[A-Z]? \([\w]+\))/
      ); // Matches COMPX123-22A (HAM)
      if (codeMatch && codeMatch[1]) {
        paperCodeInput.value = codeMatch[1];
      } else {
        // Fallback - try to get everything before " Grades"
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

  function updateLineCount() {
    const lines = csvInput.value
      .split("\n")
      .filter((line) => line.trim() !== "");
    lineCountDisplay.textContent = `Results: ${lines.length - 1}`;
  }
});

async function submitCsv() {
  const csvInput = document.getElementById("csvInput");
  const paperCodeInput = document.getElementById("paperCode");
  const modal = document.getElementById("uploadModal");

  if (!csvInput.value.trim()) {
    alert("Please enter CSV data or upload a file");
    return;
  }

  if (!paperCodeInput.value.trim()) {
    alert("Please enter a paper code");
    return;
  }

  try {
    const formData = new FormData();
    formData.append("csvData", csvInput.value);
    formData.append("paperCode", paperCodeInput.value.trim());

    const response = await fetch("/api/upload.php", {
      method: "POST",
      body: formData,
    });

    if (response.ok) {
      modal.style.display = "block";
    } else {
      throw new Error("Upload failed");
    }
  } catch (error) {
    alert("Error during upload: " + error.message);
  }
}
