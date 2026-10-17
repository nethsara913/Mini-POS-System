/**
 * Mini-POS Terminal Client-Side Controller
 * Handles interactive product filtering, live cart operations, discounts, and cash calculations.
 */

(function () {
    'use strict';

    // State
    const cart = new Map(); // id -> { id, sku, name, price, stock, quantity }
    let discountType = 'fixed'; // 'fixed' or 'percent'
    let discountValue = 0;

    // Elements
    const productGrid = document.getElementById('productGrid');
    const searchInput = document.getElementById('posSearchInput');
    const categoryPills = document.querySelectorAll('.category-filter-btn');
    const cartTableBody = document.getElementById('cartTableBody');
    const emptyCartMsg = document.getElementById('emptyCartMsg');
    const cartSummaryEl = document.getElementById('cartSummary');
    const subtotalEl = document.getElementById('cartSubtotal');
    const discountAmountEl = document.getElementById('cartDiscountAmount');
    const netTotalEl = document.getElementById('cartNetTotal');
    const discountTypeSelect = document.getElementById('discountType');
    const discountValueInput = document.getElementById('discountValue');
    const paidAmountInput = document.getElementById('paidAmount');
    const changeAmountEl = document.getElementById('changeAmount');
    const paymentMethodSelect = document.getElementById('paymentMethod');
    const checkoutBtn = document.getElementById('checkoutBtn');
    const checkoutForm = document.getElementById('checkoutForm');
    const cartDataInput = document.getElementById('cartDataJson');

    if (!productGrid) return; // Not on POS page

    // -------------------------------------------------------------
    // Product Filtering & Search
    // -------------------------------------------------------------
    function filterProducts() {
        const query = (searchInput.value || '').trim().toLowerCase();
        const activeCategoryBtn = document.querySelector('.category-filter-btn.active');
        const selectedCategoryId = activeCategoryBtn ? activeCategoryBtn.getAttribute('data-category-id') : 'all';

        const productCards = productGrid.querySelectorAll('.pos-product-item');
        let visibleCount = 0;

        productCards.forEach(card => {
            const name = (card.getAttribute('data-name') || '').toLowerCase();
            const sku = (card.getAttribute('data-sku') || '').toLowerCase();
            const catId = card.getAttribute('data-category-id') || '';

            const matchesQuery = !query || name.includes(query) || sku.includes(query);
            const matchesCat = selectedCategoryId === 'all' || catId === selectedCategoryId;

            if (matchesQuery && matchesCat) {
                card.style.display = '';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        const noMatchEl = document.getElementById('noProductsMatch');
        if (noMatchEl) {
            noMatchEl.style.display = visibleCount === 0 ? 'block' : 'none';
        }
    }

    if (searchInput) {
        searchInput.addEventListener('input', filterProducts);
        // Handle barcode scanner enter key press
        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const query = searchInput.value.trim().toLowerCase();
                const matchedItem = Array.from(productGrid.querySelectorAll('.pos-product-item')).find(card => {
                    return (card.getAttribute('data-sku') || '').toLowerCase() === query;
                });
                if (matchedItem) {
                    const id = parseInt(matchedItem.getAttribute('data-id'), 10);
                    addToCart(id);
                    searchInput.value = '';
                    filterProducts();
                }
            }
        });
    }

    categoryPills.forEach(btn => {
        btn.addEventListener('click', () => {
            categoryPills.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterProducts();
        });
    });

    // -------------------------------------------------------------
    // Cart Logic
    // -------------------------------------------------------------
    window.addToCart = function (productId) {
        const productCard = productGrid.querySelector(`.pos-product-item[data-id="${productId}"]`);
        if (!productCard) return;

        const stock = parseInt(productCard.getAttribute('data-stock'), 10) || 0;
        if (stock <= 0) {
            alert('This item is currently out of stock.');
            return;
        }

        const sku = productCard.getAttribute('data-sku');
        const name = productCard.getAttribute('data-name');
        const price = parseFloat(productCard.getAttribute('data-price')) || 0;

        if (cart.has(productId)) {
            const currentItem = cart.get(productId);
            if (currentItem.quantity + 1 > stock) {
                alert(`Cannot add more. Available stock for "${name}" is ${stock}.`);
                return;
            }
            currentItem.quantity += 1;
        } else {
            cart.set(productId, {
                id: productId,
                sku: sku,
                name: name,
                price: price,
                stock: stock,
                quantity: 1
            });
        }

        renderCart();
    };

    window.updateCartQty = function (productId, delta) {
        if (!cart.has(productId)) return;
        const item = cart.get(productId);
        const newQty = item.quantity + delta;

        if (newQty <= 0) {
            removeFromCart(productId);
            return;
        }

        if (newQty > item.stock) {
            alert(`Maximum available stock for "${item.name}" is ${item.stock}.`);
            return;
        }

        item.quantity = newQty;
        renderCart();
    };

    window.setCartQtyDirect = function (productId, inputEl) {
        if (!cart.has(productId)) return;
        const item = cart.get(productId);
        let val = parseInt(inputEl.value, 10);

        if (isNaN(val) || val <= 0) {
            val = 1;
        }

        if (val > item.stock) {
            alert(`Stock limit reached! Available: ${item.stock}`);
            val = item.stock;
        }

        item.quantity = val;
        renderCart();
    };

    window.removeFromCart = function (productId) {
        cart.delete(productId);
        renderCart();
    };

    window.clearCart = function () {
        if (cart.size === 0) return;
        if (confirm('Are you sure you want to clear all items in the cart?')) {
            cart.clear();
            renderCart();
        }
    };

    // -------------------------------------------------------------
    // Calculations & Rendering
    // -------------------------------------------------------------
    function calculateTotals() {
        let subtotal = 0;
        cart.forEach(item => {
            subtotal += item.price * item.quantity;
        });

        let discAmount = 0;
        if (discountType === 'percent') {
            discAmount = (subtotal * discountValue) / 100;
        } else {
            discAmount = discountValue;
        }

        if (discAmount > subtotal) {
            discAmount = subtotal;
        }
        if (discAmount < 0) {
            discAmount = 0;
        }

        const netTotal = Math.max(0, subtotal - discAmount);

        return {
            subtotal: subtotal,
            discountAmount: discAmount,
            netTotal: netTotal
        };
    }

    function renderCart() {
        if (cart.size === 0) {
            cartTableBody.innerHTML = '';
            emptyCartMsg.style.display = 'block';
            cartSummaryEl.style.display = 'none';
            if (checkoutBtn) checkoutBtn.disabled = true;
            updateChangeCalculation(0);
            return;
        }

        emptyCartMsg.style.display = 'none';
        cartSummaryEl.style.display = 'block';
        if (checkoutBtn) checkoutBtn.disabled = false;

        let rowsHtml = '';
        cart.forEach(item => {
            const lineTotal = item.price * item.quantity;
            rowsHtml += `
                <tr>
                    <td>
                        <div class="fw-semibold text-truncate" style="max-width: 140px;" title="${item.name}">${escapeHtml(item.name)}</div>
                        <small class="text-muted font-monospace">${escapeHtml(item.sku)}</small>
                    </td>
                    <td class="text-end">LKR ${item.price.toFixed(2)}</td>
                    <td class="text-center">
                        <div class="input-group input-group-sm justify-content-center" style="width: 105px; margin: 0 auto;">
                            <button type="button" class="btn btn-outline-secondary px-2" onclick="updateCartQty(${item.id}, -1)">-</button>
                            <input type="number" class="form-control text-center p-0" min="1" max="${item.stock}" value="${item.quantity}" onchange="setCartQtyDirect(${item.id}, this)">
                            <button type="button" class="btn btn-outline-secondary px-2" onclick="updateCartQty(${item.id}, 1)">+</button>
                        </div>
                    </td>
                    <td class="text-end fw-bold">LKR ${lineTotal.toFixed(2)}</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-link text-danger p-0 border-0" onclick="removeFromCart(${item.id})" title="Remove item">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
            `;
        });

        cartTableBody.innerHTML = rowsHtml;

        const totals = calculateTotals();
        subtotalEl.textContent = `LKR ${totals.subtotal.toFixed(2)}`;
        discountAmountEl.textContent = `-LKR ${totals.discountAmount.toFixed(2)}`;
        netTotalEl.textContent = `LKR ${totals.netTotal.toFixed(2)}`;

        // Auto sync cash paid default if paying exact or card
        const method = paymentMethodSelect ? paymentMethodSelect.value : 'cash';
        if (method !== 'cash' && paidAmountInput) {
            paidAmountInput.value = totals.netTotal.toFixed(2);
        }

        updateChangeCalculation(totals.netTotal);
    }

    function updateChangeCalculation(netTotal) {
        if (!paidAmountInput || !changeAmountEl) return;
        const currentNet = netTotal !== undefined ? netTotal : calculateTotals().netTotal;
        const paid = parseFloat(paidAmountInput.value) || 0;
        const change = Math.max(0, paid - currentNet);
        changeAmountEl.textContent = `LKR ${change.toFixed(2)}`;

        if (paid < currentNet && currentNet > 0 && paymentMethodSelect.value === 'cash') {
            changeAmountEl.classList.add('text-danger');
            changeAmountEl.classList.remove('text-success');
        } else {
            changeAmountEl.classList.remove('text-danger');
            changeAmountEl.classList.add('text-success');
        }
    }

    // -------------------------------------------------------------
    // Discount listeners
    // -------------------------------------------------------------
    if (discountTypeSelect) {
        discountTypeSelect.addEventListener('change', () => {
            discountType = discountTypeSelect.value;
            renderCart();
        });
    }

    if (discountValueInput) {
        discountValueInput.addEventListener('input', () => {
            discountValue = parseFloat(discountValueInput.value) || 0;
            renderCart();
        });
    }

    if (paidAmountInput) {
        paidAmountInput.addEventListener('input', () => {
            updateChangeCalculation();
        });
    }

    if (paymentMethodSelect) {
        paymentMethodSelect.addEventListener('change', () => {
            const totals = calculateTotals();
            if (paymentMethodSelect.value !== 'cash' && paidAmountInput) {
                paidAmountInput.value = totals.netTotal.toFixed(2);
                paidAmountInput.readOnly = true;
            } else if (paidAmountInput) {
                paidAmountInput.readOnly = false;
            }
            updateChangeCalculation();
        });
    }

    // Quick cash tender helper buttons
    window.setTenderAmount = function (amount) {
        if (!paidAmountInput) return;
        if (amount === 'exact') {
            paidAmountInput.value = calculateTotals().netTotal.toFixed(2);
        } else {
            paidAmountInput.value = parseFloat(amount).toFixed(2);
        }
        updateChangeCalculation();
    };

    // -------------------------------------------------------------
    // Form Checkout Submission
    // -------------------------------------------------------------
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', (e) => {
            if (cart.size === 0) {
                e.preventDefault();
                alert('Your cart is empty. Please add items to checkout.');
                return;
            }

            const totals = calculateTotals();
            const paid = parseFloat(paidAmountInput.value) || 0;

            if (paid < totals.netTotal && paymentMethodSelect.value === 'cash') {
                e.preventDefault();
                alert(`Insufficient payment tendered! Total due is LKR ${totals.netTotal.toFixed(2)}, but only LKR ${paid.toFixed(2)} was entered.`);
                paidAmountInput.focus();
                return;
            }

            // Serialize cart items into JSON
            const itemsArray = [];
            cart.forEach(item => {
                itemsArray.push({
                    product_id: item.id,
                    quantity: item.quantity
                });
            });

            cartDataInput.value = JSON.stringify(itemsArray);
            checkoutBtn.disabled = true;
            checkoutBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Processing Sale...';
        });
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Initialize
    renderCart();

})();