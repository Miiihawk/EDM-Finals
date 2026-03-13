let adminView = "table";

function setAdminView(view) {
  adminView = view;
  const tableView = document.getElementById("adminTableView");
  const cardView = document.getElementById("adminCardView");
  const tableBtn = document.getElementById("tableViewBtn");
  const cardBtn = document.getElementById("cardViewBtn");

  if (!tableView || !cardView || !tableBtn || !cardBtn) {
    return;
  }

  const isTable = view === "table";
  tableView.style.display = isTable ? "block" : "none";
  cardView.style.display = isTable ? "none" : "block";
  tableBtn.classList.toggle("active", isTable);
  cardBtn.classList.toggle("active", !isTable);

  searchProducts();
  sortProducts();
}

function searchProducts() {
  const input = document.getElementById("searchInput");
  const filter = (input?.value || "").toLowerCase();

  const table = document.getElementById("productsTable");
  if (table?.tBodies?.[0]) {
    const rows = Array.from(table.tBodies[0].rows);
    rows.forEach((row) => {
      const textValue = row.textContent?.toLowerCase() || "";
      row.style.display = textValue.includes(filter) ? "" : "none";
    });
  }

  const grid = document.getElementById("adminProductsGrid");
  if (grid) {
    const cards = Array.from(grid.querySelectorAll(".admin-product-card"));
    cards.forEach((card) => {
      const textValue = card.textContent?.toLowerCase() || "";
      card.style.display = textValue.includes(filter) ? "" : "none";
    });
  }
}

function sortProducts() {
  const select = document.getElementById("sortBy");
  const sortBy = select?.value || "";

  if (adminView === "card") {
    sortCardView(sortBy);
  } else {
    sortTableView(sortBy);
  }
}

function sortTableView(sortBy) {
  const table = document.getElementById("productsTable");
  if (!table?.tBodies?.[0]) {
    return;
  }

  const tbody = table.tBodies[0];
  const rows = Array.from(tbody.rows);

  if (!sortBy) {
    return;
  }

  const visibleRows = rows.filter((row) => row.style.display !== "none");
  const hiddenRows = rows.filter((row) => row.style.display === "none");

  visibleRows.sort((a, b) => {
    switch (sortBy) {
      case "name": {
        const aValue = (a.cells[1]?.textContent || "").toLowerCase().trim();
        const bValue = (b.cells[1]?.textContent || "").toLowerCase().trim();
        return aValue.localeCompare(bValue);
      }
      case "price": {
        const aValue = parseFloat(
          (a.cells[3]?.textContent || "0")
            .replace("₱", "")
            .replace(/,/g, "")
            .trim(),
        );
        const bValue = parseFloat(
          (b.cells[3]?.textContent || "0")
            .replace("₱", "")
            .replace(/,/g, "")
            .trim(),
        );
        return aValue - bValue;
      }
      case "stock": {
        const aValue = parseInt((a.cells[5]?.textContent || "0").trim(), 10);
        const bValue = parseInt((b.cells[5]?.textContent || "0").trim(), 10);
        return aValue - bValue;
      }
      case "category": {
        const aValue = (a.cells[2]?.textContent || "").toLowerCase().trim();
        const bValue = (b.cells[2]?.textContent || "").toLowerCase().trim();
        return aValue.localeCompare(bValue);
      }
      default:
        return 0;
    }
  });

  tbody.innerHTML = "";
  visibleRows.forEach((row) => tbody.appendChild(row));
  hiddenRows.forEach((row) => tbody.appendChild(row));
}

function sortCardView(sortBy) {
  const grid = document.getElementById("adminProductsGrid");
  if (!grid) {
    return;
  }

  const cards = Array.from(grid.querySelectorAll(".admin-product-card"));
  if (!cards.length || !sortBy) {
    return;
  }

  const visibleCards = cards.filter((card) => card.style.display !== "none");
  const hiddenCards = cards.filter((card) => card.style.display === "none");

  visibleCards.sort((a, b) => {
    switch (sortBy) {
      case "name":
        return (a.dataset.name || "")
          .toLowerCase()
          .localeCompare((b.dataset.name || "").toLowerCase());
      case "price":
        return (
          parseFloat(a.dataset.price || "0") -
          parseFloat(b.dataset.price || "0")
        );
      case "stock":
        return (
          parseInt(a.dataset.stock || "0", 10) -
          parseInt(b.dataset.stock || "0", 10)
        );
      case "category":
        return (a.dataset.category || "")
          .toLowerCase()
          .localeCompare((b.dataset.category || "").toLowerCase());
      default:
        return 0;
    }
  });

  grid.innerHTML = "";
  visibleCards.forEach((card) => grid.appendChild(card));
  hiddenCards.forEach((card) => grid.appendChild(card));
}

document.addEventListener("DOMContentLoaded", () => {
  setAdminView("table");
});
