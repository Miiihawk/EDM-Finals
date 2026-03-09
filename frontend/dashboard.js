// Dashboard JavaScript for PHP/MySQL version
// Contains search and sort functions for admin dashboard

// Search Products
function searchProducts() {
  const input = document.getElementById("searchInput");
  const filter = input.value.toLowerCase();
  const table = document.getElementById("productsTable");
  const rows = table.getElementsByTagName("tr");

  // Start from 1 to skip the header row
  for (let i = 1; i < rows.length; i++) {
    const cells = rows[i].getElementsByTagName("td");
    let found = false;

    // Search through all cells in the row
    for (let j = 0; j < cells.length; j++) {
      const cell = cells[j];
      if (cell) {
        const textValue = cell.textContent || cell.innerText;
        if (textValue.toLowerCase().indexOf(filter) > -1) {
          found = true;
          break;
        }
      }
    }

    // Show or hide row based on search result
    rows[i].style.display = found ? "" : "none";
  }
}

// Sort Products
function sortProducts() {
  const select = document.getElementById("sortBy");
  const sortBy = select.value;

  if (!sortBy) {
    // If no sort option selected, show all rows
    const table = document.getElementById("productsTable");
    const rows = table.getElementsByTagName("tr");
    for (let i = 1; i < rows.length; i++) {
      rows[i].style.display = "";
    }
    return;
  }

  const table = document.getElementById("productsTable");
  const tbody = table.getElementsByTagName("tbody")[0];
  const rows = Array.from(tbody.getElementsByTagName("tr"));

  // Filter out hidden rows (from search) and empty rows
  const visibleRows = rows.filter((row) => {
    const cells = row.getElementsByTagName("td");
    return cells.length > 0 && row.style.display !== "none";
  });

  visibleRows.sort((a, b) => {
    let aValue, bValue;

    switch (sortBy) {
      case "name":
        // Column index 1 is Product Name
        aValue = (a.cells[1]?.textContent || "").toLowerCase().trim();
        bValue = (b.cells[1]?.textContent || "").toLowerCase().trim();
        return aValue.localeCompare(bValue);

      case "price":
        // Column index 3 is Payments/Price
        aValue = parseFloat(
          (a.cells[3]?.textContent || "0")
            .replace("₱", "")
            .replace(/,/g, "")
            .trim(),
        );
        bValue = parseFloat(
          (b.cells[3]?.textContent || "0")
            .replace("₱", "")
            .replace(/,/g, "")
            .trim(),
        );
        return aValue - bValue;

      case "stock":
        // Column index 5 is Stock
        aValue = parseInt((a.cells[5]?.textContent || "0").trim());
        bValue = parseInt((b.cells[5]?.textContent || "0").trim());
        return aValue - bValue;

      case "category":
        // Column index 2 is Category
        aValue = (a.cells[2]?.textContent || "").toLowerCase().trim();
        bValue = (b.cells[2]?.textContent || "").toLowerCase().trim();
        return aValue.localeCompare(bValue);

      default:
        return 0;
    }
  });

  // Clear tbody
  while (tbody.firstChild) {
    tbody.removeChild(tbody.firstChild);
  }

  // Add back all rows (visible sorted ones first, then hidden ones)
  const hiddenRows = rows.filter((row) => row.style.display === "none");
  visibleRows.forEach((row) => tbody.appendChild(row));
  hiddenRows.forEach((row) => tbody.appendChild(row));
}
