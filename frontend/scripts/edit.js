// CSV Editor JavaScript

let currentCsvData = [];
let currentPaperCode = '';
let availablePapers = [];

function goBack() {
   window.location.href = "home.html";
}

// Load available papers from backend
async function loadPaperList() {
   try {
      const response = await fetch('api/getPaperList.php');
      const data = await response.json();

      const select = document.getElementById('paperCodeSelect');
      const loadButton = document.getElementById('loadButton');

      // Clear existing options
      select.innerHTML = '';

      // Check if response has the expected structure
      if (!data.success || !Array.isArray(data.papers)) {
         select.innerHTML = '<option value="">Error loading papers</option>';
         loadButton.disabled = true;
         console.error('Invalid response format:', data);
         return;
      }

      const papers = data.papers;

      if (papers.length === 0) {
         select.innerHTML = '<option value="">No papers available</option>';
         loadButton.disabled = true;
         return;
      }

      // Add default option
      select.innerHTML = '<option value="">Select a paper...</option>';

      // Remove duplicates by paper_code
      const uniquePapers = [];
      const seen = new Set();

      for (const paper of papers) {
         if (!seen.has(paper.paper_code)) {
            seen.add(paper.paper_code);
            uniquePapers.push(paper);
         }
      }

      // Sort papers alphabetically
      uniquePapers.sort((a, b) => a.paper_code.localeCompare(b.paper_code));

      // Add paper options
      uniquePapers.forEach(paper => {
         const option = document.createElement('option');
         option.value = paper.paper_code;
         option.textContent = paper.paper_code;
         select.appendChild(option);
      });

      availablePapers = uniquePapers;
      loadButton.disabled = false;

      // Enable load button when a paper is selected
      select.addEventListener('change', function () {
         loadButton.disabled = !this.value;
      });

   } catch (error) {
      console.error('Error loading paper list:', error);
      const select = document.getElementById('paperCodeSelect');
      select.innerHTML = '<option value="">Error loading papers</option>';
      document.getElementById('loadButton').disabled = true;
   }
}

// Load CSV data from backend
async function loadCsvData() {
   const paperCodeSelect = document.getElementById('paperCodeSelect');
   const paperCode = paperCodeSelect.value.trim();

   if (!paperCode) {
      showError('Please select a paper code');
      return;
   }

   showLoading(true);
   hideError();

   try {
      const response = await fetch(`api/getData.php?paperCode=${encodeURIComponent(paperCode)}`);
      const data = await response.json();

      if (data.success) {
         currentPaperCode = paperCode;
         currentCsvData = parseCsvContent(data.csv_content);
         displayCsvTable();
         showEditorSection(true);
         document.getElementById('currentPaperCode').textContent = `Editing: ${paperCode}`;
      } else {
         showError(data.error || 'Failed to load CSV data');
      }
   } catch (error) {
      showError('Error loading CSV data: ' + error.message);
   }

   showLoading(false);
}

// Parse CSV content into array format
function parseCsvContent(csvContent) {
   const lines = csvContent.trim().split('\n');
   return lines.map(line => {
      // Simple CSV parsing - handles basic cases
      const values = [];
      let current = '';
      let inQuotes = false;

      for (let i = 0; i < line.length; i++) {
         const char = line[i];

         if (char === '"') {
            inQuotes = !inQuotes;
         } else if (char === ',' && !inQuotes) {
            values.push(current.trim());
            current = '';
         } else {
            current += char;
         }
      }
      values.push(current.trim());

      return values;
   });
}

// Display CSV data in editable table
function displayCsvTable() {
   const table = document.getElementById('csvTable');
   const thead = document.getElementById('csvTableHead');
   const tbody = document.getElementById('csvTableBody');

   // Clear existing content
   thead.innerHTML = '';
   tbody.innerHTML = '';

   if (currentCsvData.length === 0) {
      showError('No data to display');
      return;
   }

   // Create header row
   const headerRow = document.createElement('tr');
   const headers = currentCsvData[0];

   headers.forEach((header, index) => {
      const th = document.createElement('th');
      const input = document.createElement('input');
      input.type = 'text';
      input.value = header;
      input.addEventListener('input', (e) => updateCellValue(0, index, e.target.value));
      th.appendChild(input);

      // Add delete column button
      if (headers.length > 1) {
         const deleteBtn = document.createElement('button');
         deleteBtn.textContent = '×';
         deleteBtn.className = 'delete-col';
         deleteBtn.title = 'Delete Column';
         deleteBtn.onclick = () => deleteColumn(index);
         th.appendChild(deleteBtn);
      }

      headerRow.appendChild(th);
   });

   // Add actions column header
   const actionsHeader = document.createElement('th');
   actionsHeader.textContent = 'Actions';
   actionsHeader.style.width = '80px';
   headerRow.appendChild(actionsHeader);

   thead.appendChild(headerRow);

   // Create data rows
   for (let rowIndex = 1; rowIndex < currentCsvData.length; rowIndex++) {
      const row = currentCsvData[rowIndex];
      const tr = document.createElement('tr');

      // Ensure row has same number of columns as header
      while (row.length < headers.length) {
         row.push('');
      }

      row.forEach((cell, colIndex) => {
         const td = document.createElement('td');
         const input = document.createElement('input');
         input.type = 'text';
         input.value = cell || '';
         input.addEventListener('input', (e) => updateCellValue(rowIndex, colIndex, e.target.value));
         td.appendChild(input);
         tr.appendChild(td);
      });

      // Add actions column
      const actionsCell = document.createElement('td');
      actionsCell.className = 'row-actions';

      if (currentCsvData.length > 2) { // Keep at least header + 1 data row
         const deleteBtn = document.createElement('button');
         deleteBtn.textContent = '×';
         deleteBtn.className = 'delete-row';
         deleteBtn.title = 'Delete Row';
         deleteBtn.onclick = () => deleteRow(rowIndex);
         actionsCell.appendChild(deleteBtn);
      }

      tr.appendChild(actionsCell);
      tbody.appendChild(tr);
   }
}

// Update cell value in data array
function updateCellValue(row, col, value) {
   if (currentCsvData[row]) {
      currentCsvData[row][col] = value;
   }
}

// Add new row
function addRow() {
   if (currentCsvData.length === 0) return;

   const columnCount = currentCsvData[0].length;
   const newRow = new Array(columnCount).fill('');
   currentCsvData.push(newRow);
   displayCsvTable();
}

// Add new column
function addColumn() {
   if (currentCsvData.length === 0) {
      currentCsvData.push(['New Column']);
   } else {
      currentCsvData.forEach((row, index) => {
         row.push(index === 0 ? 'New Column' : '');
      });
   }
   displayCsvTable();
}

// Delete row
function deleteRow(rowIndex) {
   if (currentCsvData.length > 2) { // Keep at least header + 1 data row
      currentCsvData.splice(rowIndex, 1);
      displayCsvTable();
   }
}

// Delete column
function deleteColumn(colIndex) {
   if (currentCsvData.length > 0 && currentCsvData[0].length > 1) {
      currentCsvData.forEach(row => {
         row.splice(colIndex, 1);
      });
      displayCsvTable();
   }
}

// Convert array back to CSV format
function arrayToCsv(data) {
   return data.map(row =>
      row.map(cell => {
         // Escape quotes and wrap in quotes if contains comma or quote
         const cellStr = String(cell || '');
         if (cellStr.includes(',') || cellStr.includes('"') || cellStr.includes('\n')) {
            return '"' + cellStr.replace(/"/g, '""') + '"';
         }
         return cellStr;
      }).join(',')
   ).join('\n');
}

// Save CSV data back to backend
async function saveCsv() {
   if (!currentPaperCode || currentCsvData.length === 0) {
      showError('No data to save');
      return;
   }

   showLoading(true);

   try {
      const csvContent = arrayToCsv(currentCsvData);

      const formData = new FormData();
      formData.append('csvData', csvContent);
      formData.append('paperCode', currentPaperCode);

      const response = await fetch('api/updateCsv.php', {
         method: 'POST',
         body: formData
      });

      const result = await response.json();

      if (result.success) {
         showSuccessModal(`CSV has been updated successfully! Paper Code: ${currentPaperCode}`);
      } else {
         showError(result.error || 'Failed to save CSV');
      }
   } catch (error) {
      showError('Error saving CSV: ' + error.message);
   }

   showLoading(false);
}

// Utility functions
function showLoading(show) {
   document.getElementById('loadingIndicator').style.display = show ? 'block' : 'none';
}

function showEditorSection(show) {
   document.getElementById('editorSection').style.display = show ? 'block' : 'none';
}

function showError(message) {
   const errorDiv = document.getElementById('errorMessage');
   const errorText = document.getElementById('errorText');
   errorText.textContent = message;
   errorDiv.style.display = 'block';
}

function hideError() {
   document.getElementById('errorMessage').style.display = 'none';
}

function showSuccessModal(message) {
   const modal = document.getElementById('successModal');
   const messageElement = document.getElementById('successMessage');
   messageElement.textContent = message;
   modal.style.display = 'block';
}

function hideSuccessModal() {
   document.getElementById('successModal').style.display = 'none';
}

// Handle URL parameters for direct loading and initialize page
document.addEventListener('DOMContentLoaded', async function () {
   // Load the paper list first
   await loadPaperList();

   // Check URL parameters for direct loading
   const urlParams = new URLSearchParams(window.location.search);
   const paperCode = urlParams.get('paperCode');

   if (paperCode) {
      const select = document.getElementById('paperCodeSelect');
      select.value = paperCode;

      // Enable load button if paper exists in dropdown
      if (select.value === paperCode) {
         document.getElementById('loadButton').disabled = false;
         loadCsvData();
      }
   }
});

// Keyboard shortcuts
document.addEventListener('keydown', function (e) {
   if (e.ctrlKey || e.metaKey) {
      switch (e.key) {
         case 's':
            e.preventDefault();
            saveCsv();
            break;
         case 'Enter':
            if (e.shiftKey) {
               e.preventDefault();
               addRow();
            }
            break;
      }
   }
});
