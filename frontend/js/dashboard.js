let adminView = "table";
let activeAdminOrder = null;

function setAdminSection(section) {
  const productsSection = document.getElementById("adminProductsSection");
  const ordersSection = document.getElementById("adminOrdersSection");
  const logsSection = document.getElementById("adminLogsSection");
  const productsTab = document.getElementById("adminTabProducts");
  const ordersTab = document.getElementById("adminTabOrders");

  if (!productsSection || !ordersSection || !productsTab || !ordersTab) {
    return;
  }

  const showOrders = section === "orders";

  productsSection.style.display = showOrders ? "none" : "block";
  ordersSection.style.display = showOrders ? "block" : "none";
  if (logsSection) {
    logsSection.style.display = showOrders ? "none" : "flex";
  }

  productsTab.classList.toggle("active", !showOrders);
  ordersTab.classList.toggle("active", showOrders);

  if (showOrders) {
    filterOrders();
  } else {
    searchProducts();
    sortProducts();
  }
}

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

function filterOrders() {
  const table = document.getElementById("ordersTable");
  if (!table || !table.tBodies || !table.tBodies[0]) {
    return;
  }

  const dateValue = document.getElementById("orderDateFilter")?.value || "";
  const paymentValue =
    document.getElementById("orderPaymentFilter")?.value || "";

  const rows = Array.from(table.tBodies[0].rows);
  rows.forEach((row) => {
    const rowDate = row.dataset.orderDate || "";
    const rowPayment = row.dataset.paymentMethod || "";

    const dateMatch = !dateValue || rowDate === dateValue;
    const paymentMatch = !paymentValue || rowPayment === paymentValue;
    row.style.display = dateMatch && paymentMatch ? "" : "none";
  });
}

function buildAdminOrderHtml(order, items) {
  const hasCustomerDetails = !!order.has_customer_details;
  const itemRows = items
    .map(
      (item) => `
      <tr>
        <td>${item.product_name || "N/A"}</td>
        <td>${item.quantity}</td>
        <td>₱${Number(item.price || 0).toFixed(2)}</td>
        <td>₱${Number(item.subtotal || 0).toFixed(2)}</td>
      </tr>
    `,
    )
    .join("");

  const cashDetails =
    order.payment_method === "Cash"
      ? `<div>Cash Received: ₱${Number(order.cash_received || 0).toFixed(2)}</div>
         <div>Change: ₱${Number(order.cash_change || 0).toFixed(2)}</div>`
      : "";

  return `
    ${
      hasCustomerDetails
        ? `<h4>Customer Details</h4>
    <div>Name: ${order.full_name || "N/A"}</div>
    <div>Phone: ${order.phone || "N/A"}</div>
    <div>Email: ${order.email || "N/A"}</div>`
        : ""
    }

    <h4 style="margin-top:${hasCustomerDetails ? "12px" : "0"};">Invoice Details</h4>
    <div>Tracking No: ${order.tracking_no}</div>
    <div>Order Date: ${order.order_date}</div>
    <div>Payment Mode: ${order.payment_method}</div>
    <div>Total: ₱${Number(order.total_amount || 0).toFixed(2)}</div>
    <div>Created By: ${order.created_by || "N/A"}</div>
    ${cashDetails}

    <h4 style="margin-top:12px;">Products</h4>
    <table class="summary-products-table">
      <thead><tr><th>Item</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
      <tbody>${itemRows}</tbody>
    </table>
  `;
}

async function viewOrderDetails(orderId) {
  try {
    const response = await fetch(
      `../backend/get_order_details.php?order_id=${orderId}`,
    );
    const data = await response.json();
    if (!data.success) {
      alert(data.error || "Unable to load order details.");
      return;
    }

    activeAdminOrder = data;
    const block = document.getElementById("adminOrderSummaryBlock");
    const modal = document.getElementById("adminOrderModal");
    if (!block || !modal) {
      return;
    }

    block.innerHTML = buildAdminOrderHtml(data.order, data.items || []);
    modal.style.display = "flex";
  } catch (error) {
    console.error(error);
    alert("Error loading order details.");
  }
}

function closeAdminOrderModal() {
  const modal = document.getElementById("adminOrderModal");
  if (modal) {
    modal.style.display = "none";
  }
}

function formatCurrency(value) {
  return Number(value || 0).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function escapeHtml(text) {
  return String(text ?? "N/A")
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/\"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function getCompanyDetails() {
  return {
    name: "9toFive Convenience Store",
    address1: "#9 Rizal Street, Barangay Central, Philippines",
    address2: "9toFive Retail Solutions Inc.",
  };
}

function buildInvoiceHtml(order, items) {
  const company = getCompanyDetails();
  const hasCustomerDetails = !!order.has_customer_details;
  const rows = (items || [])
    .map((item, index) => {
      const subtotal = Number(item.subtotal || 0);
      return `
        <tr>
          <td>${index + 1}</td>
          <td>${escapeHtml(item.product_name || "N/A")}</td>
          <td>${formatCurrency(item.price)}</td>
          <td>${item.quantity}</td>
          <td><strong>${formatCurrency(subtotal)}</strong></td>
        </tr>
      `;
    })
    .join("");

  return `
    <html>
      <head>
        <title>Order ${escapeHtml(order.tracking_no)}</title>
        <style>
          body { font-family: Arial, sans-serif; margin: 18px; color: #222; }
          .header { text-align: center; margin-bottom: 14px; }
          .header h2 { margin: 0 0 6px; }
          .header p { margin: 2px 0; color: #444; font-size: 14px; }
          .top-grid { display: grid; grid-template-columns: 1fr 1fr; margin: 18px 0 10px; }
          .top-grid h3 { margin: 0 0 6px; font-size: 20px; }
          .top-grid p { margin: 3px 0; font-size: 14px; }
          .top-grid .right { text-align: right; }
          table { width: 100%; border-collapse: collapse; margin-top: 8px; }
          thead th { text-align: left; border-bottom: 1px solid #999; padding: 8px 6px; }
          td { border-bottom: 1px solid #ddd; padding: 8px 6px; }
          .bottom { display: flex; justify-content: space-between; margin-top: 12px; font-size: 20px; }
          .grand { font-weight: 700; }
        </style>
      </head>
      <body>
        <div class="header">
          <h2>${escapeHtml(company.name)}</h2>
          <p>${escapeHtml(company.address1)}</p>
          <p>${escapeHtml(company.address2)}</p>
        </div>

        <div class="top-grid" style="grid-template-columns: ${hasCustomerDetails ? "1fr 1fr" : "1fr"};">
          ${
            hasCustomerDetails
              ? `<div>
            <h3>Customer Details</h3>
            <p>Customer Name: ${escapeHtml(order.full_name)}</p>
            <p>Customer Phone No: ${escapeHtml(order.phone)}</p>
            <p>Customer Email Id: ${escapeHtml(order.email || "N/A")}</p>
          </div>`
              : ""
          }
          <div class="right">
            <h3>Invoice Details</h3>
            <p>Invoice No: ${escapeHtml(order.tracking_no)}</p>
            <p>Invoice Date: ${escapeHtml(order.order_date)}</p>
          </div>
        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Product Name</th>
              <th>Price</th>
              <th>Quantity</th>
              <th>Total Price</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>

        <div class="bottom">
          <div>Payment Mode: ${escapeHtml(order.payment_method || "N/A")}</div>
          <div class="grand">Grand Total: ${formatCurrency(order.total_amount)}</div>
        </div>
      </body>
    </html>
  `;
}

function printAdminOrder() {
  if (!activeAdminOrder) {
    alert("No order selected.");
    return;
  }

  const html = buildInvoiceHtml(
    activeAdminOrder.order,
    activeAdminOrder.items || [],
  );

  const printWindow = window.open("", "_blank");
  if (!printWindow) {
    return;
  }
  printWindow.document.write(html);
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
}

function downloadAdminOrderPdf() {
  if (!activeAdminOrder) {
    alert("No order selected.");
    return;
  }

  const jspdfRef = window.jspdf;
  if (!jspdfRef || !jspdfRef.jsPDF) {
    alert("PDF library is not loaded.");
    return;
  }

  const doc = new jspdfRef.jsPDF();
  const company = getCompanyDetails();
  const pageWidth = doc.internal.pageSize.getWidth();
  const leftX = 14;
  const rightX = pageWidth - 14;
  const order = activeAdminOrder.order;
  const items = activeAdminOrder.items || [];
  const hasCustomerDetails = !!order.has_customer_details;
  let y = 16;

  doc.setFontSize(17);
  doc.text(company.name, pageWidth / 2, y, { align: "center" });
  y += 7;
  doc.setFontSize(10);
  doc.text(company.address1, pageWidth / 2, y, { align: "center" });
  y += 5;
  doc.text(company.address2, pageWidth / 2, y, { align: "center" });
  y += 10;

  doc.setFontSize(12);
  if (hasCustomerDetails) {
    doc.text("Customer Details", leftX, y);
    doc.text("Invoice Details", rightX, y, { align: "right" });
  } else {
    doc.text("Invoice Details", leftX, y);
  }
  y += 6;

  doc.setFontSize(10);
  if (hasCustomerDetails) {
    doc.text(`Customer Name: ${order.full_name || "N/A"}`, leftX, y);
    doc.text(`Invoice No: ${order.tracking_no}`, rightX, y, { align: "right" });
  } else {
    doc.text(`Invoice No: ${order.tracking_no}`, leftX, y);
  }
  y += 5;
  if (hasCustomerDetails) {
    doc.text(`Customer Phone No: ${order.phone || "N/A"}`, leftX, y);
    doc.text(`Invoice Date: ${order.order_date}`, rightX, y, {
      align: "right",
    });
  } else {
    doc.text(`Invoice Date: ${order.order_date}`, leftX, y);
  }
  y += 5;
  if (hasCustomerDetails) {
    doc.text(`Customer Email Id: ${order.email || "N/A"}`, leftX, y);
    y += 8;
  } else {
    y += 3;
  }

  const col = { id: 14, name: 26, price: 132, qty: 158, total: 181 };
  doc.setLineWidth(0.2);
  doc.line(leftX, y - 3, rightX, y - 3);
  doc.text("ID", col.id, y);
  doc.text("Product Name", col.name, y);
  doc.text("Price", col.price, y);
  doc.text("Quantity", col.qty, y);
  doc.text("Total Price", col.total, y);
  y += 4;
  doc.line(leftX, y, rightX, y);
  y += 5;

  items.forEach((item, index) => {
    doc.text(String(index + 1), col.id, y);
    doc.text(String(item.product_name || "N/A"), col.name, y);
    doc.text(formatCurrency(item.price), col.price, y);
    doc.text(String(item.quantity), col.qty, y);
    doc.text(formatCurrency(item.subtotal), col.total, y);
    y += 6;
    if (y > 275) {
      doc.addPage();
      y = 15;
    }
  });

  doc.line(leftX, y - 2, rightX, y - 2);
  y += 6;
  doc.setFontSize(11);
  doc.text(`Payment Mode: ${order.payment_method || "N/A"}`, leftX, y);
  doc.setFont(undefined, "bold");
  doc.text(`Grand Total: ${formatCurrency(order.total_amount)}`, rightX, y, {
    align: "right",
  });
  doc.save(`${order.tracking_no}.pdf`);
}

document.addEventListener("DOMContentLoaded", () => {
  setAdminView("table");
  setAdminSection("products");
  filterOrders();
});
