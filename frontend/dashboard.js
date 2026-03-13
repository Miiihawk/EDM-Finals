function searchProducts() {
  const input = document.getElementById("searchInput");
  const filter = input.value.toLowerCase();
  const table = document.getElementById("productsTable");
  const rows = table.getElementsByTagName("tr");

  for (let i = 1; i < rows.length; i++) {
    const cells = rows[i].getElementsByTagName("td");
    let found = false;

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

    rows[i].style.display = found ? "" : "none";
  }
}

function sortProducts() {
  const select = document.getElementById("sortBy");
  const sortBy = select.value;

  if (!sortBy) {
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

  const visibleRows = rows.filter((row) => {
    const cells = row.getElementsByTagName("td");
    return cells.length > 0 && row.style.display !== "none";
  });

  visibleRows.sort((a, b) => {
    let aValue, bValue;

    switch (sortBy) {
      case "name":
        aValue = (a.cells[1]?.textContent || "").toLowerCase().trim();
        bValue = (b.cells[1]?.textContent || "").toLowerCase().trim();
        return aValue.localeCompare(bValue);

      case "price":
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
        aValue = parseInt((a.cells[5]?.textContent || "0").trim());
        bValue = parseInt((b.cells[5]?.textContent || "0").trim());
        return aValue - bValue;

      case "category":
        aValue = (a.cells[2]?.textContent || "").toLowerCase().trim();
        bValue = (b.cells[2]?.textContent || "").toLowerCase().trim();
        return aValue.localeCompare(bValue);

      default:
        return 0;
    }
  });

  while (tbody.firstChild) {
    tbody.removeChild(tbody.firstChild);
  }

  const hiddenRows = rows.filter((row) => row.style.display === "none");
  visibleRows.forEach((row) => tbody.appendChild(row));
  hiddenRows.forEach((row) => tbody.appendChild(row));
}
