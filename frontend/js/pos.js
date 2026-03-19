/**
 * DFA Overview
 * ============
 * This POS uses two Deterministic Finite Automata to validate input codes before
 * they are acted upon.  A DFA is a 5-tuple  M = (Q, Σ, δ, q₀, F)  where:
 *   Q  = finite set of states
 *   Σ  = input alphabet
 *   δ  = transition function  Q × Σ → Q
 *   q₀ = initial state
 *   F  = set of accepting / final states
 *
 * The automata run character-by-character and reject immediately on any unexpected
 * character — no backtracking is needed, making them O(n) and O(1) space.
 */

// State variables
let activeCategory = "all";
let cart = [];
let selectedPaymentMethod = null;
let selectedCustomer = null;
let savedOrder = null;
let isCompletingOrder = false;
let collectCustomerDetails = false;
let appliedPromo = null;

// UI helpers
function updateDateTime() {
  const now = new Date();
  const options = {
    weekday: "short",
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  };
  const el = document.getElementById("currentDateTime");
  if (el) el.textContent = now.toLocaleString("en-US", options);
}

function toggleTransactionHistory() {
  const overlay = document.getElementById("transactionHistoryOverlay");
  if (!overlay) return;
  overlay.style.display =
    overlay.style.display === "none" || overlay.style.display === ""
      ? "flex"
      : "none";
}

function toggleMobileMenu() {
  const nav = document.getElementById("mobileNav");
  if (nav) nav.classList.toggle("active");
}

function normalizeCategory(value) {
  return String(value || "")
    .trim()
    .toLowerCase();
}

function updateStatusBadge(elementId, statusClass, message) {
  const el = document.getElementById(elementId);
  if (!el) return;
  el.className = `dfa-status ${statusClass}`;
  el.textContent = message;
}

function setProductCodeFeedback(statusClass, message) {
  const el = document.getElementById("productCodeStatus");
  if (!el) return;
  el.className = `product-code-feedback ${statusClass}`;
  el.textContent = message;
}

function runProductCodeDfa(rawValue) {
  const input = String(rawValue || "")
    .trim()
    .toUpperCase();
  if (!input) return { accepted: false, empty: true, normalized: "" };

  let state = "prefix-1";

  for (const char of input) {
    switch (state) {
      case "prefix-1":
      case "prefix-2":
      case "prefix-3":
        if (!/[A-Z]/.test(char))
          return { accepted: false, normalized: input, state };
        // Advance to next prefix state, or to digit-1 after the third letter
        state =
          state === "prefix-1"
            ? "prefix-2"
            : state === "prefix-2"
              ? "prefix-3"
              : "digit-1";
        break;

      case "digit-1":
      case "digit-2":
      case "digit-3":
        if (!/[0-9]/.test(char))
          return { accepted: false, normalized: input, state };
        // Advance to next digit state, or to accept after the third digit
        state =
          state === "digit-1"
            ? "digit-2"
            : state === "digit-2"
              ? "digit-3"
              : "accept";
        break;

      default:
        // Trap state — extra characters after 'accept', or unknown state
        return { accepted: false, normalized: input, state };
    }
  }

  return {
    accepted: state === "accept" && input.length === 6,
    normalized: input,
    state,
  };
}

function runPromoCodeDfa(rawValue) {
  const input = String(rawValue || "")
    .trim()
    .toUpperCase();
  if (!input) return { accepted: false, empty: true, normalized: "" };

  let state = "letter-1";

  for (const char of input) {
    if (state.startsWith("letter")) {
      if (!/[A-Z]/.test(char))
        return { accepted: false, normalized: input, state };
      // Advance through letter states, after letter-4 move to digit-1
      state =
        state === "letter-1"
          ? "letter-2"
          : state === "letter-2"
            ? "letter-3"
            : state === "letter-3"
              ? "letter-4"
              : "digit-1";
      continue;
    }

    if (state === "digit-1" || state === "digit-2") {
      if (!/[0-9]/.test(char))
        return { accepted: false, normalized: input, state };
      state = state === "digit-1" ? "digit-2" : "accept";
      continue;
    }

    // Trap: already accepted but more characters are present, or unknown state
    return { accepted: false, normalized: input, state };
  }

  return {
    accepted: state === "accept" && input.length === 6,
    normalized: input,
    state,
  };
}

// Promo logic
function buildPromoFromCode(rawValue) {
  const dfaResult = runPromoCodeDfa(rawValue);

  if (!dfaResult.accepted) {
    return {
      valid: false,
      empty: !!dfaResult.empty,
      message: "Promo code must follow the AAAA## format.",
    };
  }

  const discountPercent = parseInt(dfaResult.normalized.slice(-2), 10);
  if (discountPercent < 5 || discountPercent > 30) {
    return {
      valid: false,
      code: dfaResult.normalized,
      message: "Promo discount must be between 05 and 30.",
    };
  }

  return { valid: true, code: dfaResult.normalized, discountPercent };
}

// Cart calculations
function getCartSubtotal() {
  return cart.reduce(
    (sum, item) => sum + Number(item.price) * Number(item.quantity),
    0,
  );
}

function getPromoDiscount(subtotal = getCartSubtotal()) {
  if (!appliedPromo) return 0;
  return Number(((subtotal * appliedPromo.discountPercent) / 100).toFixed(2));
}

function getCartTotal() {
  const subtotal = getCartSubtotal();
  return Number((subtotal - getPromoDiscount(subtotal)).toFixed(2));
}

// Product Code field interaction
function validateProductCodeField() {
  const input = document.getElementById("productCodeInput");
  const rawValue = String(input?.value || "")
    .toUpperCase()
    .replace(/\s+/g, "");
  if (input) input.value = rawValue;

  const result = runProductCodeDfa(rawValue);

  if (result.empty) {
    setProductCodeFeedback("idle", "Enter code in AAA999 format.");
    return result;
  }

  // Partial input: still forming a valid prefix — stay idle, don't show error yet
  const isPotentialPartial =
    rawValue.length < 6 && /^(?:[A-Z]{0,3}|[A-Z]{3}[0-9]{0,3})$/.test(rawValue);
  if (!result.accepted && isPotentialPartial) {
    setProductCodeFeedback(
      "idle",
      "Format: 3 letters + 3 digits (example: SNA004).",
    );
    return result;
  }

  if (!result.accepted) {
    setProductCodeFeedback(
      "error",
      "Invalid product code. Use 3 letters followed by 3 digits.",
    );
    return result;
  }

  const productCard = document.querySelector(
    `.product-card[data-code="${result.normalized}"]`,
  );
  if (!productCard) {
    setProductCodeFeedback(
      "error",
      `Code ${result.normalized} is valid in format but not found in products.`,
    );
    return result;
  }

  setProductCodeFeedback("success", `Code ${result.normalized} is valid.`);
  return result;
}

function handleProductCodeInput() {
  const validation = validateProductCodeField();
  // Auto-add to cart as soon as a structurally-valid, existing code is entered
  if (validation.accepted) addProductByCode();
}

// Promo field interaction
function validatePromoCodeField() {
  const input = document.getElementById("promoCodeInput");
  if (input) {
    input.value = String(input.value || "")
      .toUpperCase()
      .replace(/\s+/g, "");
  }
  const promo = buildPromoFromCode(input?.value || "");
  return promo;
}

function applyPromoCode() {
  const input = document.getElementById("promoCodeInput");
  const badge = document.getElementById("activePromoBadge");
  const promo = buildPromoFromCode(input?.value || "");

  if (promo.empty) {
    appliedPromo = null;
    if (badge) {
      badge.style.display = "none";
      badge.textContent = "";
    }
    updateCart();
    return;
  }

  if (!promo.valid) {
    appliedPromo = null;
    if (badge) {
      badge.style.display = "none";
      badge.textContent = "";
    }
    showInvalidPromoModal(promo.message || "The promo code is invalid.");
    updateCart();
    return;
  }

  appliedPromo = promo;
  if (input) input.value = promo.code;
  if (badge) {
    badge.style.display = "inline-flex";
    badge.textContent = `Applied ${promo.code} for ${promo.discountPercent}% off`;
  }
  updateCart();
}

function showInvalidPromoModal(message) {
  const modal = document.getElementById("invalidPromoModal");
  const messageEl = document.getElementById("invalidPromoMessage");
  if (messageEl) {
    messageEl.textContent =
      String(message || "").trim() || "The promo code is invalid.";
  }
  if (modal) {
    modal.style.display = "flex";
  }
}

function closeInvalidPromoModal() {
  const modal = document.getElementById("invalidPromoModal");
  if (modal) {
    modal.style.display = "none";
  }
}

// Add product by code
function addProductByCode() {
  const productCodeInput = document.getElementById("productCodeInput");
  const quantityInput = document.getElementById("productCodeQuantity");
  const validation = validateProductCodeField();

  if (!validation.accepted) return;

  const quantity = Math.max(1, parseInt(quantityInput?.value || "1", 10) || 1);
  const card = document.querySelector(
    `.product-card[data-code="${validation.normalized}"][data-clickable="1"]`,
  );
  if (!card) {
    setProductCodeFeedback(
      "error",
      `Product ${validation.normalized} is valid but unavailable or out of stock.`,
    );
    return;
  }

  const id = parseInt(card.dataset.id, 10);
  const name = card.dataset.name;
  const price = parseFloat(card.dataset.price);
  const category = card.dataset.category;
  const stock = parseInt(card.dataset.stock, 10);
  const code = card.dataset.code;

  addToCart(id, name, price, category, stock, code, quantity);

  if (productCodeInput) productCodeInput.value = "";
  if (quantityInput) quantityInput.value = "1";
  setProductCodeFeedback(
    "success",
    `Added ${quantity} item(s): ${validation.normalized}`,
  );
}

// POS card click setup
function initializePOSCardClicks() {
  const cards = document.querySelectorAll('.product-card[data-clickable="1"]');
  cards.forEach((card) => {
    card.addEventListener("click", () => {
      addToCart(
        parseInt(card.dataset.id, 10),
        card.dataset.name,
        parseFloat(card.dataset.price),
        card.dataset.category,
        parseInt(card.dataset.stock, 10),
        card.dataset.code,
      );
    });
  });
}

// Payment
function selectPaymentMethod(method) {
  selectedPaymentMethod = method;
  document.querySelectorAll(".payment-option").forEach((btn) => {
    btn.classList.toggle("selected", btn.dataset.method === method);
  });

  const cashDetails = document.getElementById("cashPaymentDetails");
  if (cashDetails)
    cashDetails.style.display = method === "Cash" ? "block" : "none";

  updateCashChange();
  updateCheckoutButtons();
}

function updateCheckoutButtons() {
  const isDisabled = cart.length === 0 || !selectedPaymentMethod;
  const officialReceiptBtn = document.getElementById("officialReceiptBtn");
  const quickProcessBtn = document.getElementById("quickProcessBtn");
  if (officialReceiptBtn) officialReceiptBtn.disabled = isDisabled;
  if (quickProcessBtn) quickProcessBtn.disabled = isDisabled;
}

function updateCashChange() {
  const cashAmountInput = document.getElementById("cashAmount");
  const cashChange = document.getElementById("cashChange");
  if (!cashAmountInput || !cashChange) return;

  const total = getCartTotal();
  const cashReceived = parseFloat(cashAmountInput.value || "0");
  const change = cashReceived - total;

  if (change >= 0) {
    cashChange.textContent = "₱" + change.toFixed(2);
    cashChange.classList.remove("insufficient");
  } else {
    cashChange.textContent = "₱0.00";
    cashChange.classList.add("insufficient");
  }
}

// Customer picker
function renderCustomerPickerList(searchTerm = "") {
  const listEl = document.getElementById("customerPickerList");
  const proceedBtn = document.getElementById("proceedReceiptBtn");
  if (!listEl) return;

  const filter = String(searchTerm || "")
    .trim()
    .toLowerCase();
  const filtered = customerRegistry.filter((customer) => {
    const fullName = String(customer.full_name || "").toLowerCase();
    const phone = String(customer.phone || "").toLowerCase();
    const email = String(customer.email || "").toLowerCase();
    return (
      !filter ||
      fullName.includes(filter) ||
      phone.includes(filter) ||
      email.includes(filter)
    );
  });

  if (filtered.length === 0) {
    listEl.innerHTML =
      '<div class="customer-picker-empty">No customers found. Use Add New Customer.</div>';
    if (proceedBtn) proceedBtn.disabled = !selectedCustomer?.id;
    return;
  }

  listEl.innerHTML = filtered
    .map((customer) => {
      const isActive =
        selectedCustomer && Number(selectedCustomer.id) === Number(customer.id);
      return `<button type="button" class="customer-picker-item ${isActive ? "active" : ""}" onclick="selectCustomerFromPicker(${Number(customer.id)})">
        <span class="name">${escapeHtml(customer.full_name || "N/A")}</span>
        <span class="meta">${escapeHtml(customer.phone || "N/A")} · ${escapeHtml(customer.email || "N/A")}</span>
      </button>`;
    })
    .join("");

  if (proceedBtn) proceedBtn.disabled = !selectedCustomer?.id;
}

function openCustomerPickerModal() {
  const modal = document.getElementById("selectCustomerModal");
  const searchInput = document.getElementById("customerPickerSearch");
  if (searchInput) searchInput.value = "";
  renderCustomerPickerList("");
  if (modal) modal.style.display = "flex";
  if (searchInput) searchInput.focus();
}

function closeCustomerPickerModal() {
  const modal = document.getElementById("selectCustomerModal");
  if (modal) modal.style.display = "none";
}

function filterCustomerPickerList() {
  const searchInput = document.getElementById("customerPickerSearch");
  renderCustomerPickerList(searchInput?.value || "");
}

function selectCustomerFromPicker(customerId) {
  const customer = customerRegistry.find(
    (item) => Number(item.id) === Number(customerId),
  );
  if (!customer) return;
  selectedCustomer = customer;
  renderCustomerPickerList(
    document.getElementById("customerPickerSearch")?.value || "",
  );
}

function openCreateCustomerFromPicker() {
  closeCustomerPickerModal();
  const searchText = (
    document.getElementById("customerPickerSearch")?.value || ""
  ).trim();
  document.getElementById("newCustomerName").value = "";
  document.getElementById("newCustomerEmail").value = "";
  document.getElementById("newCustomerPhone").value =
    /^\+?[0-9\-\s]{6,20}$/.test(searchText) ? searchText : "";
  document.getElementById("createCustomerModal").style.display = "flex";
}

async function proceedOfficialReceiptCheckout() {
  if (!selectedCustomer?.id) {
    alert("Please select a customer for official receipt.");
    return;
  }
  closeCustomerPickerModal();
  const isSaved = await saveOrder(false);
  if (isSaved) printOrderSummary();
}

function hasCustomerDetails(
  orderData = savedOrder,
  customerData = selectedCustomer,
) {
  if (orderData && typeof orderData.has_customer_details !== "undefined")
    return !!orderData.has_customer_details;
  return !!(
    customerData &&
    (customerData.full_name || customerData.phone || customerData.email)
  );
}

// Cart management
function addToCart(id, name, price, category, stock, code, quantity = 1) {
  const maxStock = Number.isFinite(stock) ? stock : parseInt(stock, 10);
  const safeQuantity = Math.max(1, parseInt(quantity, 10) || 1);
  const existingItem = cart.find((item) => item.id === id);

  if (existingItem) {
    if (existingItem.quantity + safeQuantity > existingItem.stock) {
      alert("Cannot add more. Stock limit reached for this product.");
      return;
    }
    existingItem.quantity += safeQuantity;
  } else {
    const icons = {
      Tools: '<i class="fas fa-hammer"></i>',
      Paint: '<i class="fas fa-paint-roller"></i>',
      Electrical: '<i class="fas fa-bolt"></i>',
      Plumbing: '<i class="fas fa-wrench"></i>',
      Fasteners: '<i class="fas fa-screwdriver"></i>',
    };
    cart.push({
      id,
      name,
      price,
      quantity: safeQuantity,
      stock: maxStock,
      code: String(code || "").toUpperCase(),
      icon: icons[category] || '<i class="fas fa-box"></i>',
    });
  }

  updateCart();
}

function updateQuantity(id, change) {
  const item = cart.find((item) => item.id === id);
  if (!item) return;
  if (change > 0 && item.quantity >= item.stock) {
    alert("Cannot add more. Stock limit reached for this product.");
    return;
  }
  item.quantity += change;
  if (item.quantity <= 0) removeFromCart(id);
  else updateCart();
}

function removeFromCart(id) {
  cart = cart.filter((item) => item.id !== id);
  updateCart();
}

function closeCreateCustomerModal() {
  const modal = document.getElementById("createCustomerModal");
  if (modal) modal.style.display = "none";
  ["newCustomerName", "newCustomerPhone", "newCustomerEmail"].forEach((id) => {
    const el = document.getElementById(id);
    if (el) el.value = "";
  });
}

function closeOrderSummary() {
  const modal = document.getElementById("orderSummaryModal");
  if (modal) modal.style.display = "none";
}

function updateCart() {
  const cartItemsContainer = document.getElementById("cartItems");
  const cartSummary = document.getElementById("cartSummary");
  const paymentSelector = document.getElementById("paymentMethodSelector");
  const cashDetails = document.getElementById("cashPaymentDetails");
  const cashAmountInput = document.getElementById("cashAmount");
  const cashChange = document.getElementById("cashChange");
  const promoRow = document.getElementById("promoRow");
  const discountRow = document.getElementById("discountRow");
  const promoLabel = document.getElementById("promoLabel");
  const discountLabel = document.getElementById("discount");

  if (cart.length === 0) {
    cartItemsContainer.innerHTML = `
      <div class="cart-empty">
        <i class="fas fa-shopping-cart"></i>
        <p>Your cart is empty</p>
      </div>`;
    cartSummary.style.display = "none";
    selectedPaymentMethod = null;
    collectCustomerDetails = false;
    if (paymentSelector) paymentSelector.style.display = "none";
    document
      .querySelectorAll(".payment-option")
      .forEach((btn) => btn.classList.remove("selected"));
    if (cashDetails) cashDetails.style.display = "none";
    if (cashAmountInput) cashAmountInput.value = "";
    if (cashChange) {
      cashChange.textContent = "₱0.00";
      cashChange.classList.remove("insufficient");
    }
    if (promoRow) promoRow.style.display = "none";
    if (discountRow) discountRow.style.display = "none";
    if (promoLabel) promoLabel.textContent = "None";
    if (discountLabel) discountLabel.textContent = "-₱0.00";
    selectedCustomer = null;
    savedOrder = null;
    appliedPromo = null;
    const promoInput = document.getElementById("promoCodeInput");
    const promoBadge = document.getElementById("activePromoBadge");
    if (promoInput) promoInput.value = "";
    if (promoBadge) {
      promoBadge.style.display = "none";
      promoBadge.textContent = "";
    }
    updateCheckoutButtons();
  } else {
    const subtotal = getCartSubtotal();
    const discountAmount = getPromoDiscount(subtotal);
    const total = Number((subtotal - discountAmount).toFixed(2));

    let html = "";
    cart.forEach((item) => {
      html += `
        <div class="cart-item">
          <div class="cart-item-icon">${item.icon}</div>
          <div class="cart-item-details">
            <div class="cart-item-name">${item.name}</div>
            <div class="cart-item-code">${item.code}</div>
            <div class="cart-item-price">₱${item.price.toFixed(2)}</div>
          </div>
          <div class="cart-item-controls">
            <button class="qty-btn" onclick="updateQuantity(${item.id}, -1)">−</button>
            <span class="qty-display">${item.quantity}</span>
            <button class="qty-btn" onclick="updateQuantity(${item.id}, 1)">+</button>
            <button class="remove-btn" onclick="removeFromCart(${item.id})">×</button>
          </div>
        </div>`;
    });

    cartItemsContainer.innerHTML = html;
    document.getElementById("subtotal").textContent = "₱" + subtotal.toFixed(2);
    document.getElementById("total").textContent = "₱" + total.toFixed(2);
    cartSummary.style.display = "block";
    if (promoRow) promoRow.style.display = appliedPromo ? "flex" : "none";
    if (discountRow) discountRow.style.display = appliedPromo ? "flex" : "none";
    if (promoLabel)
      promoLabel.textContent = appliedPromo
        ? `${appliedPromo.code} (${appliedPromo.discountPercent}% off)`
        : "None";
    if (discountLabel)
      discountLabel.textContent = "-₱" + discountAmount.toFixed(2);
    if (paymentSelector) paymentSelector.style.display = "block";
    updateCheckoutButtons();
    updateCashChange();
  }
}

// Product search & filter
function searchPOSProducts() {
  applyPOSFilters();
}

function applyPOSFilters() {
  const input = document.getElementById("posSearchInput");
  const filter = input ? input.value.toLowerCase() : "";
  const cards = document.querySelectorAll(".product-card");
  const noProductsMessage = document.getElementById("noProductsMessage");
  let visibleCount = 0;

  cards.forEach((card) => {
    const itemName = card
      .querySelector(".product-name")
      .textContent.toLowerCase();
    const itemCategory = card
      .querySelector(".product-category")
      .textContent.toLowerCase();
    const categoryMatch =
      activeCategory === "all" ||
      normalizeCategory(card.dataset.category) === activeCategory;
    const searchMatch =
      itemName.includes(filter) || itemCategory.includes(filter);

    card.style.display = categoryMatch && searchMatch ? "" : "none";
    if (categoryMatch && searchMatch) visibleCount++;
  });

  if (noProductsMessage) {
    if (visibleCount === 0) {
      noProductsMessage.textContent =
        activeCategory === "all"
          ? "No Products Available."
          : "No Products Available in this category.";
      noProductsMessage.style.display = "block";
    } else {
      noProductsMessage.style.display = "none";
    }
  }
}

function filterCategory(category, element) {
  document
    .querySelectorAll(".category-btn")
    .forEach((btn) => btn.classList.remove("active"));
  if (element) element.classList.add("active");
  activeCategory = category === "all" ? "all" : normalizeCategory(category);
  searchPOSProducts();
}

// Checkout
function validateCheckout() {
  if (cart.length === 0) return false;
  if (!selectedPaymentMethod) {
    alert("Please select a payment method.");
    return false;
  }
  if (selectedPaymentMethod === "Cash") {
    const cashAmountInput = document.getElementById("cashAmount");
    const total = getCartTotal();
    const cashReceived = parseFloat(cashAmountInput?.value || "0");
    if (!cashReceived || cashReceived < total) {
      alert("Cash received must be greater than or equal to the total amount.");
      return false;
    }
  }
  return true;
}

async function checkoutWithReceipt() {
  if (!validateCheckout()) return;
  collectCustomerDetails = true;
  selectedCustomer = null;
  openCustomerPickerModal();
}

async function checkoutWithoutReceipt() {
  if (!validateCheckout()) return;
  collectCustomerDetails = false;
  selectedCustomer = null;
  await saveOrder(false);
}

async function createCustomerAndContinue() {
  const fullName = (
    document.getElementById("newCustomerName").value || ""
  ).trim();
  const phone = (
    document.getElementById("newCustomerPhone").value || ""
  ).trim();
  const email = (
    document.getElementById("newCustomerEmail").value || ""
  ).trim();

  if (!fullName || !phone) {
    alert("Full name and phone are required.");
    return;
  }

  const formData = new FormData();
  formData.append("full_name", fullName);
  formData.append("phone", phone);
  formData.append("email", email);

  try {
    const response = await fetch("../backend/create_customer.php", {
      method: "POST",
      body: formData,
    });
    const data = await response.json();
    if (!data.success) {
      alert(data.error || "Failed to create customer.");
      return;
    }
    selectedCustomer = data.customer;
    customerRegistry.push(data.customer);
    closeCreateCustomerModal();
    if (collectCustomerDetails) {
      const isSaved = await saveOrder(false);
      if (isSaved) printOrderSummary();
    }
  } catch (error) {
    console.error(error);
    alert("Error creating customer.");
  }
}

// Order summary helpers
function buildSummaryHtml(orderRefText, orderDateText) {
  const subtotal = getCartSubtotal();
  const discountAmount = getPromoDiscount(subtotal);
  const total = Number((subtotal - discountAmount).toFixed(2));
  const includeCustomerDetails = hasCustomerDetails();

  const itemsRows = cart
    .map(
      (item) =>
        `<tr>
          <td>${item.name}</td>
          <td>${item.code}</td>
          <td>${item.quantity}</td>
          <td>₱${item.price.toFixed(2)}</td>
          <td>₱${(item.price * item.quantity).toFixed(2)}</td>
        </tr>`,
    )
    .join("");

  let paymentExtra = "";
  if (selectedPaymentMethod === "Cash") {
    const cashReceived = parseFloat(
      document.getElementById("cashAmount")?.value || "0",
    );
    const change = Math.max(cashReceived - total, 0);
    paymentExtra = `<div>Cash Received: ₱${cashReceived.toFixed(2)}</div><div>Change: ₱${change.toFixed(2)}</div>`;
  }

  const promoSummary = appliedPromo
    ? `<div>Promo Code: ${appliedPromo.code}</div><div>Discount: -₱${discountAmount.toFixed(2)} (${appliedPromo.discountPercent}% off)</div>`
    : "";

  return {
    customerHtml: includeCustomerDetails
      ? `<h4>Customer Details</h4>
         <div>Name: ${selectedCustomer?.full_name || "N/A"}</div>
         <div>Phone: ${selectedCustomer?.phone || "N/A"}</div>
         <div>Email: ${selectedCustomer?.email || "N/A"}</div>`
      : "",
    invoiceHtml: `
      <h4>Invoice Details</h4>
      <div>Invoice Number: ${orderRefText}</div>
      <div>Invoice Date: ${orderDateText}</div>
      <div>Payment Mode: ${selectedPaymentMethod}</div>
      <div>Subtotal: ₱${subtotal.toFixed(2)}</div>
      ${promoSummary}
      ${paymentExtra}`,
    productsHtml: `
      <h4>Products</h4>
      <table class="summary-products-table">
        <thead><tr><th>Item</th><th>Code</th><th>Qty</th><th>Price</th><th>Subtotal</th></tr></thead>
        <tbody>${itemsRows}</tbody>
      </table>
      <div class="summary-total-row">Total: ₱${total.toFixed(2)}</div>`,
  };
}

function showOrderSummary() {
  if (collectCustomerDetails && !selectedCustomer) {
    alert("Customer is required before order summary.");
    return;
  }

  const invoiceNo = savedOrder?.tracking_no || "Pending (save to generate)";
  const invoiceDate = savedOrder?.order_date || new Date().toLocaleString();
  const blocks = buildSummaryHtml(invoiceNo, invoiceDate);

  const summaryCustomerBlock = document.getElementById("summaryCustomerBlock");
  if (summaryCustomerBlock) {
    summaryCustomerBlock.innerHTML = blocks.customerHtml;
    summaryCustomerBlock.style.display = blocks.customerHtml ? "block" : "none";
  }
  document.getElementById("summaryInvoiceBlock").innerHTML = blocks.invoiceHtml;
  document.getElementById("summaryProductsBlock").innerHTML =
    blocks.productsHtml;

  const printBtn = document.getElementById("printOrderBtn");
  const downloadBtn = document.getElementById("downloadOrderBtn");
  if (printBtn) printBtn.disabled = !savedOrder;
  if (downloadBtn) downloadBtn.disabled = !savedOrder;

  document.getElementById("orderSummaryModal").style.display = "flex";
}

async function saveOrder(showSuccessAlert = true) {
  if (collectCustomerDetails && !selectedCustomer) {
    alert("Customer is required.");
    return false;
  }

  if (savedOrder) {
    showOrderSummary();
    if (showSuccessAlert)
      alert(
        `Order already saved with invoice number ${savedOrder.tracking_no}.`,
      );
    return true;
  }

  const total = getCartTotal();
  const formData = new FormData();
  formData.append("cart", JSON.stringify(cart));
  formData.append("total", total.toFixed(2));
  formData.append("payment_method", selectedPaymentMethod);
  formData.append(
    "collect_customer_details",
    collectCustomerDetails ? "1" : "0",
  );
  formData.append("promo_code", appliedPromo?.code || "");
  if (selectedCustomer?.id) formData.append("customer_id", selectedCustomer.id);

  if (selectedPaymentMethod === "Cash") {
    const cashReceived = parseFloat(
      document.getElementById("cashAmount")?.value || "0",
    );
    const change = Math.max(cashReceived - total, 0);
    formData.append("cash_received", cashReceived.toFixed(2));
    formData.append("cash_change", change.toFixed(2));
  }

  try {
    const response = await fetch("../backend/place_order.php", {
      method: "POST",
      body: formData,
    });
    const data = await response.json();
    if (!data.success) {
      alert(data.error || "Failed to save order.");
      return false;
    }
    savedOrder = data;
    showOrderSummary();
    if (showSuccessAlert)
      alert("Order saved successfully. You can now print or download PDF.");
    return true;
  } catch (error) {
    console.error(error);
    alert("Error saving order.");
    return false;
  }
}

async function completeOrder() {
  if (isCompletingOrder) return;
  isCompletingOrder = true;
  const doneBtn = document.getElementById("doneOrderBtn");
  if (doneBtn) doneBtn.disabled = true;

  try {
    let processed = !!savedOrder;
    if (!processed) processed = await saveOrder(false);
    if (!processed) return;
    alert("Order processed successfully. Inventory has been updated.");
    window.location.reload();
  } finally {
    isCompletingOrder = false;
    if (doneBtn) doneBtn.disabled = false;
  }
}

// Print / PDF
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
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#039;");
}

function getCompanyDetails() {
  return {
    name: "CornerMart Convenience Store",
    address1: "94 Kamuning Rd, Diliman, Quezon City, 1103 Metro Manila",
    address2: "CornerMart Retail Solutions Inc.",
  };
}

function buildInvoiceHtml(order, customer, items) {
  const company = getCompanyDetails();
  const includeCustomerDetails = hasCustomerDetails(order, customer);
  const subtotal = Number(
    order.subtotal_amount ??
      order.subtotal ??
      items.reduce(
        (sum, item) => sum + Number(item.price) * Number(item.quantity),
        0,
      ),
  );
  const discountAmount = Number(order.discount_amount || 0);
  const grandTotal = Number(order.total_amount ?? subtotal - discountAmount);

  const rows = items
    .map((item, index) => {
      const rowTotal = Number(item.price) * Number(item.quantity);
      return `<tr>
        <td>${index + 1}</td>
        <td>${escapeHtml(item.name)}</td>
        <td>${escapeHtml(item.code || "N/A")}</td>
        <td>${formatCurrency(item.price)}</td>
        <td>${item.quantity}</td>
        <td><strong>${formatCurrency(rowTotal)}</strong></td>
      </tr>`;
    })
    .join("");

  return `<html>
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
      <div class="top-grid" style="grid-template-columns: ${includeCustomerDetails ? "1fr 1fr" : "1fr"};">
        ${
          includeCustomerDetails
            ? `<div>
              <h3>Customer Details</h3>
              <p>Customer Name: ${escapeHtml(customer.full_name)}</p>
              <p>Customer Phone No: ${escapeHtml(customer.phone)}</p>
              <p>Customer Email Id: ${escapeHtml(customer.email || "N/A")}</p>
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
            <th>ID</th><th>Product Name</th><th>Product Code</th>
            <th>Price</th><th>Quantity</th><th>Total Price</th>
          </tr>
        </thead>
        <tbody>${rows}</tbody>
      </table>
      <div class="bottom">
        <div>
          <div>Payment Mode: ${escapeHtml(order.payment_method)}</div>
          <div>Subtotal: ${formatCurrency(subtotal)}</div>
          ${order.promo_code ? `<div>Promo: ${escapeHtml(order.promo_code)} (${Number(order.promo_discount_percent || 0)}% off)</div>` : ""}
          ${discountAmount > 0 ? `<div>Discount: -${formatCurrency(discountAmount)}</div>` : ""}
        </div>
        <div class="grand">Grand Total: ${formatCurrency(grandTotal)}</div>
      </div>
    </body>
  </html>`;
}

function printOrderSummary() {
  if (!savedOrder) {
    alert("Please save the order first.");
    return;
  }
  const printWindow = window.open("", "_blank");
  if (!printWindow) return;

  const orderData = {
    tracking_no: savedOrder?.tracking_no || "N/A",
    order_date: savedOrder?.order_date || new Date().toLocaleString(),
    payment_method: selectedPaymentMethod || "N/A",
    has_customer_details:
      savedOrder?.has_customer_details ??
      hasCustomerDetails(savedOrder, selectedCustomer),
    promo_code: savedOrder?.promo_code || appliedPromo?.code || "",
    promo_discount_percent:
      savedOrder?.promo_discount_percent || appliedPromo?.discountPercent || 0,
    discount_amount: savedOrder?.discount_amount || getPromoDiscount(),
    subtotal: savedOrder?.subtotal_amount || getCartSubtotal(),
    total_amount: savedOrder?.total_amount || getCartTotal(),
  };

  printWindow.document.write(
    buildInvoiceHtml(orderData, selectedCustomer || {}, cart),
  );
  printWindow.document.close();
  printWindow.focus();
  printWindow.print();
}

function downloadOrderPdf() {
  if (!savedOrder) {
    alert("Please save the order first.");
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
  const includeCustomerDetails = hasCustomerDetails(
    savedOrder,
    selectedCustomer,
  );
  const subtotal = Number(savedOrder?.subtotal_amount || getCartSubtotal());
  const discountAmount = Number(
    savedOrder?.discount_amount || getPromoDiscount(subtotal),
  );
  const total = Number(savedOrder?.total_amount || subtotal - discountAmount);
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
  if (includeCustomerDetails) {
    doc.text("Customer Details", leftX, y);
    doc.text("Invoice Details", rightX, y, { align: "right" });
  } else {
    doc.text("Invoice Details", leftX, y);
  }
  y += 6;

  doc.setFontSize(10);
  if (includeCustomerDetails) {
    doc.text(
      `Customer Name: ${selectedCustomer?.full_name || "N/A"}`,
      leftX,
      y,
    );
    doc.text(`Invoice No: ${savedOrder?.tracking_no || "N/A"}`, rightX, y, {
      align: "right",
    });
  } else {
    doc.text(`Invoice No: ${savedOrder?.tracking_no || "N/A"}`, leftX, y);
  }
  y += 5;
  if (includeCustomerDetails) {
    doc.text(
      `Customer Phone No: ${selectedCustomer?.phone || "N/A"}`,
      leftX,
      y,
    );
    doc.text(
      `Invoice Date: ${savedOrder?.order_date || new Date().toLocaleString()}`,
      rightX,
      y,
      { align: "right" },
    );
  } else {
    doc.text(
      `Invoice Date: ${savedOrder?.order_date || new Date().toLocaleString()}`,
      leftX,
      y,
    );
  }
  y += 5;
  if (includeCustomerDetails) {
    doc.text(
      `Customer Email Id: ${selectedCustomer?.email || "N/A"}`,
      leftX,
      y,
    );
    y += 8;
  } else {
    y += 3;
  }

  // Product columns: ID | Name | Code | Price | Qty | Total
  const col = { id: 14, name: 24, code: 96, price: 126, qty: 156, total: 181 };
  doc.setLineWidth(0.2);
  doc.line(leftX, y - 3, rightX, y - 3);
  doc.text("ID", col.id, y);
  doc.text("Product Name", col.name, y);
  doc.text("Code", col.code, y);
  doc.text("Price", col.price, y);
  doc.text("Quantity", col.qty, y);
  doc.text("Total Price", col.total, y);
  y += 4;
  doc.line(leftX, y, rightX, y);
  y += 5;

  cart.forEach((item, index) => {
    const rowTotal = Number(item.price) * Number(item.quantity);
    doc.text(String(index + 1), col.id, y);
    doc.text(String(item.name || "N/A"), col.name, y);
    doc.text(String(item.code || "N/A"), col.code, y);
    doc.text(formatCurrency(item.price), col.price, y);
    doc.text(String(item.quantity), col.qty, y);
    doc.text(formatCurrency(rowTotal), col.total, y);
    y += 6;
    if (y > 272) {
      doc.addPage();
      y = 16;
    }
  });

  doc.line(leftX, y - 2, rightX, y - 2);
  y += 6;
  doc.setFontSize(11);
  doc.text(`Payment Mode: ${selectedPaymentMethod || "N/A"}`, leftX, y);
  y += 6;
  doc.text(`Subtotal: ${formatCurrency(subtotal)}`, leftX, y);
  if (savedOrder?.promo_code || appliedPromo?.code) {
    y += 6;
    doc.text(
      `Promo: ${savedOrder?.promo_code || appliedPromo?.code} (${savedOrder?.promo_discount_percent || appliedPromo?.discountPercent || 0}% off)`,
      leftX,
      y,
    );
  }
  if (discountAmount > 0) {
    y += 6;
    doc.text(`Discount: -${formatCurrency(discountAmount)}`, leftX, y);
  }
  doc.setFont(undefined, "bold");
  doc.text(`Grand Total: ${formatCurrency(total)}`, rightX, y, {
    align: "right",
  });
  doc.save(`${savedOrder.tracking_no}.pdf`);
}

// Initialisation (runs after DOM is ready, script loaded at end of <body>)
document.addEventListener("DOMContentLoaded", function () {
  updateDateTime();
  setInterval(updateDateTime, 1000);
  initializePOSCardClicks();
  validateProductCodeField();
  validatePromoCodeField();
  searchPOSProducts();
});
