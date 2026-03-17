/**
 * add_product.js — Add / Edit Product page scripts
 */

/** Toggle the mobile slide-out navigation. */
function toggleMobileMenu() {
  const nav = document.getElementById("mobileNav");
  if (nav) nav.classList.toggle("active");
}

function getCategoryPrefix(category) {
  if (category.code_prefix && category.code_prefix.trim() !== "") {
    return category.code_prefix.toUpperCase().substring(0, 3).padEnd(3, "X");
  }
  // Auto-derive: same logic as PHP's buildProductCode()
  const letters = (category.category_name || "")
    .replace(/[^A-Z]/gi, "")
    .toUpperCase();
  const base = letters || "ITM";
  return base.substring(0, 3).padEnd(3, "X");
}

/**
 * Update the product code preview field whenever the category selection changes.
 * - New product:  shows  PREFIX###  (digits pending DB assignment)
 * - Edit product: shows  PREFIXNNN  (exact code, ID is known)
 */
function previewProductCode() {
  const catSelect = document.getElementById("category_id");
  const previewInput = document.getElementById("productCodePreview");
  const hintEl = document.getElementById("codePreviewHint");
  if (!catSelect || !previewInput) return;

  const categoryId = parseInt(catSelect.value, 10);

  if (!categoryId) {
    previewInput.value = "";
    if (hintEl)
      hintEl.textContent = "Select a category to preview the product code.";
    return;
  }

  const data = window.PRODUCT_DATA || {};
  const category = (data.categories || []).find((c) => c.id === categoryId);
  if (!category) return;

  const prefix = getCategoryPrefix(category);

  if (data.isEditMode && data.productId) {
    // Exact code known at edit time
    const suffix = String(data.productId).padStart(3, "0");
    previewInput.value = prefix + suffix;
    if (hintEl)
      hintEl.textContent =
        "This product's code (prefix from category + product ID).";
  } else {
    // New product — suffix assigned by DB on INSERT
    previewInput.value = prefix + "###";
    if (hintEl)
      hintEl.textContent =
        "Preview only — the 3 digits (###) will be replaced by the product's ID after saving.";
  }
}

document.addEventListener("DOMContentLoaded", function () {
  const catSelect = document.getElementById("category_id");
  if (catSelect) {
    catSelect.addEventListener("change", previewProductCode);
    // Run immediately on load (handles edit-mode pre-selected category)
    previewProductCode();
  }
});
