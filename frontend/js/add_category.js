/**
 * add_category.js - Manage Categories page scripts
 */

/** Toggle the mobile slide-out navigation. */
function toggleMobileMenu() {
  const nav = document.getElementById("mobileNav");
  if (nav) nav.classList.toggle("active");
}

/** Confirm then redirect to delete a category. */
function deleteCategory(id, name) {
  if (confirm(`Are you sure you want to delete the category "${name}"?`)) {
    window.location.href = `add_category.php?delete_id=${id}`;
  }
}


function generateCodePrefix(name) {
  return String(name || "")
    .replace(/[^A-Za-z]/g, "")
    .toUpperCase()
    .substring(0, 3);
}

/**
 * Called on every keystroke in the Category Name field.
 * Auto-fills the prefix input when the user has not yet typed a custom prefix.
 */
function onCategoryNameInput() {
  const nameInput = document.getElementById("category_name");
  const prefixInput = document.getElementById("code_prefix");
  if (!nameInput || !prefixInput) return;

  // Only auto-fill when the prefix was not manually customised
  if (!prefixInput.dataset.manuallyEdited) {
    prefixInput.value = generateCodePrefix(nameInput.value);
  }

  validateCodePrefix();
}

/**
 * Called on every keystroke in the Code Prefix field.
 * Enforces uppercase letters only (DFA alphabet constraint) and caps at 3 chars.
 * Marks the field as manually edited so auto-fill stops overriding user input.
 */
function onCodePrefixInput() {
  const prefixInput = document.getElementById("code_prefix");
  if (!prefixInput) return;

  // Strip non-letter characters and uppercase — DFA pre-processing step
  const sanitised = prefixInput.value
    .replace(/[^A-Za-z]/g, "")
    .toUpperCase()
    .substring(0, 3);

  prefixInput.value = sanitised;
  prefixInput.dataset.manuallyEdited = sanitised.length > 0 ? "1" : "";

  validateCodePrefix();
}

/**
 * Validates the prefix field and updates the inline hint text.
 * Mirrors the DFA acceptance condition: state === s3 (length === 3).
 *
 * @returns {boolean}  true if the prefix is a valid 3-letter code.
 */
function validateCodePrefix() {
  const prefixInput = document.getElementById("code_prefix");
  const hint = document.getElementById("codePrefixHint");
  if (!prefixInput || !hint) return false;

  const value = prefixInput.value;

  // DFA acceptance: all 3 states traversed → length === 3 uppercase letters
  if (value.length === 0) {
    hint.textContent =
      "Will be auto-generated from category name if left blank.";
    hint.className = "field-hint";
    return false;
  }

  if (value.length < 3) {
    // Partial — still traversing states s1 or s2
    hint.textContent = `${3 - value.length} more letter(s) needed.`;
    hint.className = "field-hint hint-warning";
    return false;
  }

  // Length === 3 → DFA reached accepting state s3
  hint.textContent = `Prefix "${value}" will produce codes like ${value}001, ${value}002 …`;
  hint.className = "field-hint hint-success";
  return true;
}

document.addEventListener("DOMContentLoaded", function () {
  // Wire up the category name field to auto-generate the prefix
  const nameInput = document.getElementById("category_name");
  const prefixInput = document.getElementById("code_prefix");

  if (nameInput) {
    nameInput.addEventListener("input", onCategoryNameInput);
  }

  if (prefixInput) {
    prefixInput.addEventListener("input", onCodePrefixInput);
    // Run initial validation in case the form was reloaded with a value
    validateCodePrefix();
  }
});
