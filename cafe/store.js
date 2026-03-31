/* =========================================================
   Cozy Corner Café — Public Store JavaScript
   ========================================================= */

(function () {
  'use strict';

  /* ── State ─────────────────────────────────────────── */
  let allProducts = [];
  let cart = {};            // { productId: { product, qty } }
  let activeMenuCat = 'all';
  let activeOrderCat = 'all';
  let chatHistory = [];

  /* ── Utility ────────────────────────────────────────── */
  const $ = id => document.getElementById(id);
  const fmt = p => '₱' + parseFloat(p).toFixed(2);

  function categoryIcon(cat) {
    const icons = {
      'Coffee': '☕', 'Cold Drinks': '🧊', 'Hot Drinks': '🍵',
      'Pastries': '🥐', 'Food': '🥪', 'Beverages': '🥤'
    };
    return icons[cat] || '☕';
  }

  /* ── Fetch Products ─────────────────────────────────── */
  async function fetchProducts() {
    try {
      const res = await fetch('products_api.php');
      if (!res.ok) throw new Error('Network error');
      allProducts = await res.json();
      renderMenuGrid();
      renderOrderList();
    } catch (err) {
      const errHTML = '<p style="text-align:center;color:#991b1b;padding:2rem;">Could not load menu. Please refresh or try again shortly.</p>';
      const mg = $('menuGrid'); if (mg) mg.innerHTML = errHTML;
      const op = $('orderProductList'); if (op) op.innerHTML = errHTML;
    }
  }

  /* ── Menu Grid (read-only display) ─────────────────── */
  function filteredMenu() {
    if (activeMenuCat === 'all') return allProducts;
    return allProducts.filter(p => p.category === activeMenuCat);
  }

  function renderMenuGrid() {
    const grid = $('menuGrid');
    if (!grid) return;
    const items = filteredMenu();
    if (!items.length) {
      grid.innerHTML = '<p style="text-align:center;color:#7D6350;padding:3rem;">No items in this category.</p>';
      return;
    }
    grid.innerHTML = items.map(p => `
      <div class="menu-card" data-id="${p.id}" onclick="addFromMenu(${p.id})">
        ${p.image_url
          ? `<img class="menu-card-img" src="${escHtml(p.image_url)}" alt="${escHtml(p.name)}" loading="lazy">`
          : `<div class="menu-card-img-placeholder">${categoryIcon(p.category)}</div>`
        }
        <div class="menu-card-body">
          <div class="menu-card-cat">${escHtml(p.category)}</div>
          <div class="menu-card-name">${escHtml(p.name)}</div>
          <div class="menu-card-desc">${escHtml(p.description)}</div>
          <div class="menu-card-footer">
            <span class="menu-card-price">${fmt(p.price)}</span>
            <button class="add-to-cart-btn" onclick="event.stopPropagation();addFromMenu(${p.id})" title="Add to cart">+</button>
          </div>
        </div>
      </div>
    `).join('');
  }

  window.addFromMenu = function(id) {
    const p = allProducts.find(x => x.id == id);
    if (!p) return;
    addToCart(p);
    // Smooth scroll to order section
    const orderSec = document.getElementById('order');
    if (orderSec) orderSec.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  /* ── Menu Category Filter ───────────────────────────── */
  document.addEventListener('click', function (e) {
    if (e.target.classList.contains('filter-btn')) {
      document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
      e.target.classList.add('active');
      activeMenuCat = e.target.dataset.cat || 'all';
      renderMenuGrid();
    }
    if (e.target.classList.contains('order-filter')) {
      document.querySelectorAll('.order-filter').forEach(b => b.classList.remove('active'));
      e.target.classList.add('active');
      activeOrderCat = e.target.dataset.ocat || 'all';
      renderOrderList();
    }
  });

  /* ── Order List ─────────────────────────────────────── */
  function filteredOrder() {
    if (activeOrderCat === 'all') return allProducts;
    return allProducts.filter(p => p.category === activeOrderCat);
  }

  function renderOrderList() {
    const list = $('orderProductList');
    if (!list) return;
    const items = filteredOrder();
    if (!items.length) {
      list.innerHTML = '<p style="text-align:center;color:#7D6350;padding:1.5rem;">No items in this category.</p>';
      return;
    }
    list.innerHTML = items.map(p => {
      const qty = cart[p.id]?.qty || 0;
      return `
        <div class="order-item-row" id="orow-${p.id}">
          ${p.image_url
            ? `<img class="order-item-img" src="${escHtml(p.image_url)}" alt="${escHtml(p.name)}" loading="lazy">`
            : `<div class="order-item-img" style="display:flex;align-items:center;justify-content:center;font-size:1.5rem;">${categoryIcon(p.category)}</div>`
          }
          <div class="order-item-info">
            <div class="order-item-name">${escHtml(p.name)}</div>
            <div class="order-item-price">${fmt(p.price)}</div>
          </div>
          <div class="qty-control">
            <button class="qty-btn" onclick="changeQty(${p.id}, -1)">−</button>
            <span class="qty-display" id="qty-${p.id}">${qty}</span>
            <button class="qty-btn" onclick="changeQty(${p.id}, 1)">+</button>
          </div>
        </div>`;
    }).join('');
  }

  window.changeQty = function(id, delta) {
    const p = allProducts.find(x => x.id == id);
    if (!p) return;
    if (delta > 0) {
      addToCart(p);
    } else {
      removeFromCart(id);
    }
  };

  /* ── Cart ───────────────────────────────────────────── */
  function addToCart(p) {
    if (!cart[p.id]) cart[p.id] = { product: p, qty: 0 };
    cart[p.id].qty += 1;
    updateCartUI(p.id);
  }

  function removeFromCart(id) {
    if (!cart[id]) return;
    cart[id].qty -= 1;
    if (cart[id].qty <= 0) delete cart[id];
    updateCartUI(id);
  }

  function updateCartUI(changedId) {
    // Update qty display in order list
    const qtyEl = $('qty-' + changedId);
    if (qtyEl) qtyEl.textContent = cart[changedId]?.qty || 0;

    // Update cart summary
    const summary = $('cartSummary');
    const cartItemsEl = $('cartItems');
    const cartTotalEl = $('cartTotal');
    if (!summary) return;

    const cartEntries = Object.values(cart);
    if (!cartEntries.length) {
      summary.style.display = 'none';
      return;
    }
    summary.style.display = 'block';
    let total = 0;
    cartItemsEl.innerHTML = cartEntries.map(({ product, qty }) => {
      const line = product.price * qty;
      total += line;
      return `<div class="cart-item-line"><span>${qty}× ${escHtml(product.name)}</span><span>${fmt(line)}</span></div>`;
    }).join('');
    cartTotalEl.textContent = fmt(total);
  }

  /* ── Order Submission ───────────────────────────────── */
  const orderForm = $('customerOrderForm');
  if (orderForm) {
    orderForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const name = $('customerName').value.trim();
      const email = $('customerEmail').value.trim();
      const phone = $('customerPhone').value.trim();
      const notes = $('customerNotes').value.trim();
      const feedback = $('orderFeedback');
      const submitBtn = $('orderSubmitBtn');

      if (!name || !phone) {
        showFeedback(feedback, 'Please enter your name and phone number.', 'error');
        return;
      }
      const cartEntries = Object.values(cart);
      if (!cartEntries.length) {
        showFeedback(feedback, 'Please add at least one item to your order.', 'error');
        return;
      }

      submitBtn.disabled = true;
      submitBtn.innerHTML = '<span>Placing Order…</span>';

      const items = cartEntries.map(({ product, qty }) => ({
        product_id: product.id,
        product_name: product.name,
        quantity: qty,
        unit_price: product.price,
        line_total: (product.price * qty).toFixed(2)
      }));

      const total = items.reduce((s, i) => s + parseFloat(i.line_total), 0);

      const payload = { name, email, phone, notes, items, total: total.toFixed(2) };

      try {
        const res = await fetch('submit_order.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload)
        });
        const data = await res.json();
        if (data.success) {
          cart = {};
          orderForm.reset();
          renderOrderList();
          updateCartUI(-1);
          $('cartSummary').style.display = 'none';
          showOrderModal(`Your order has been received! We'll prepare it right away. Order reference: #${data.order_id || '—'}`);
        } else {
          showFeedback(feedback, data.message || 'Failed to place order. Please try again.', 'error');
        }
      } catch (err) {
        showFeedback(feedback, 'Connection error. Please check your internet and try again.', 'error');
      } finally {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<span>Place My Order</span>';
      }
    });
  }

  function showFeedback(el, msg, type) {
    if (!el) return;
    el.innerHTML = msg;
    el.className = type === 'error' ? 'feedback-error' : 'feedback-success';
  }

  function showOrderModal(msg) {
    const modal = $('orderModal');
    const msgEl = $('modalMessage');
    if (!modal) return;
    if (msgEl) msgEl.textContent = msg;
    modal.style.display = 'flex';
  }

  const modalClose = $('modalClose');
  if (modalClose) {
    modalClose.addEventListener('click', () => {
      $('orderModal').style.display = 'none';
    });
  }

  /* ── Contact Form ───────────────────────────────────── */
  const contactForm = $('contactForm');
  if (contactForm) {
    contactForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const fb = $('contactFeedback');
      fb.textContent = 'Thank you for your message! We\'ll get back to you within 24 hours.';
      fb.className = 'form-feedback success';
      contactForm.reset();
    });
  }

  /* ── Newsletter Form ────────────────────────────────── */
  const newsletterForm = $('newsletterForm');
  if (newsletterForm) {
    newsletterForm.addEventListener('submit', function (e) {
      e.preventDefault();
      const fb = $('newsletterFeedback');
      fb.textContent = '☕ You\'re subscribed! Welcome to the Cozy Corner family.';
      newsletterForm.reset();
    });
  }

  /* ── Sticky Header ──────────────────────────────────── */
  const header = $('siteHeader');
  if (header) {
    window.addEventListener('scroll', function () {
      header.classList.toggle('scrolled', window.scrollY > 60);
    }, { passive: true });
  }

  /* ── Mobile Nav Toggle ──────────────────────────────── */
  const hamburger = $('hamburger');
  const nav = $('mainNav');
  if (hamburger && nav) {
    hamburger.addEventListener('click', () => {
      nav.classList.toggle('open');
      hamburger.classList.toggle('open');
    });
    // Close on nav link click
    nav.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        nav.classList.remove('open');
        hamburger.classList.remove('open');
      });
    });
  }

  /* ── Chatbot Toggle / Messaging ─────────────────────── */
  const chatToggle = $('chatbot-toggle');
  const chatWindow = $('chatbot-window');
  const chatClose = $('chatbot-close');
  const chatMessages = $('chatbot-messages');
  const chatForm = $('chatbot-form');
  const chatInput = $('chatbot-input');
  const chatQuickActions = $('chatbot-quick-actions');

  function pushChatHistory(role, text) {
    chatHistory.push({ role, text });
    if (chatHistory.length > 12) chatHistory = chatHistory.slice(-12);
  }

  function appendChatMessage(role, text) {
    if (!chatMessages) return;
    const item = document.createElement('div');
    item.className = 'chat-message ' + role;
    item.textContent = text;
    chatMessages.appendChild(item);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function localFallbackAnswer(message) {
    const msg = String(message || '').toLowerCase();
    if (/hour|open|close|time|schedule/.test(msg)) {
      return 'We are open Monday-Friday 7:00 AM to 8:00 PM, and Saturday-Sunday 8:00 AM to 9:00 PM.';
    }
    if (/where|location|address|find/.test(msg)) {
      return 'You can find us at Cuasi, Loon, Bohol.';
    }
    if (/phone|call|contact/.test(msg)) {
      return 'You can call us at 09361679546 or email wlaniba330@gmail.com.';
    }
    if (/order/.test(msg) && /(status|tracking|track|where|ready|preparing)/.test(msg)) {
      return 'For order status, please send your order reference number (example: #123) and the phone number you used when ordering.';
    }
    if (/email/.test(msg)) {
      return 'For email, contact us at wlaniba330@gmail.com.';
    }
    if (/order|pickup|buy/.test(msg)) {
      return 'To place an order: open the Order for Pickup section, choose items, enter your details (Full Name and Phone are required; Email is optional), then tap Place My Order.';
    }
    if (/menu|coffee|pastr|food|drink/.test(msg)) {
      return 'Our menu includes coffee, cold drinks, hot drinks, pastries, and food. I can help if you ask for specific item recommendations.';
    }
    return 'I can help with menu items, pricing, location, opening hours, and pickup orders. What would you like to know?';
  }

  async function sendChatMessage(message) {
    const clean = String(message || '').trim();
    if (!clean) return;
    appendChatMessage('user', clean);
    pushChatHistory('user', clean);

    if (chatInput) chatInput.value = '';
    if (chatInput) chatInput.disabled = true;

    try {
      const res = await fetch('chatbot_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: clean, history: chatHistory })
      });

      if (!res.ok) throw new Error('Chat service unavailable');
      const data = await res.json();
      const answer = data.answer || localFallbackAnswer(clean);
      appendChatMessage('bot', answer);
      pushChatHistory('bot', answer);
    } catch (err) {
      const fallback = localFallbackAnswer(clean);
      appendChatMessage('bot', fallback);
      pushChatHistory('bot', fallback);
    } finally {
      if (chatInput) {
        chatInput.disabled = false;
        chatInput.focus();
      }
    }
  }

  if (chatForm) {
    chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      sendChatMessage(chatInput ? chatInput.value : '');
    });
  }

  if (chatQuickActions) {
    chatQuickActions.addEventListener('click', function (e) {
      if (!e.target.classList.contains('chatbot-chip')) return;
      sendChatMessage(e.target.textContent || '');
    });
  }

  if (chatToggle && chatWindow) {
    chatToggle.addEventListener('click', () => {
      const isOpen = chatWindow.style.display !== 'none';
      chatWindow.style.display = isOpen ? 'none' : 'block';
      if (!isOpen && chatInput) chatInput.focus();
    });
    if (chatClose) {
      chatClose.addEventListener('click', () => {
        chatWindow.style.display = 'none';
      });
    }
  }

  if (chatMessages) {
    const greeting = 'Hi! I am your Cozy Corner assistant. Ask me about our menu, hours, location, or how pickup ordering works.';
    appendChatMessage('bot', greeting);
    pushChatHistory('bot', greeting);
  }

  /* ── Active Nav Highlighting on scroll ──────────────── */
  const sections = document.querySelectorAll('section[id]');
  const navLinks = document.querySelectorAll('.nav-link');
  function updateActiveNav() {
    let current = '';
    sections.forEach(s => {
      if (window.scrollY >= s.offsetTop - 120) current = s.id;
    });
    navLinks.forEach(l => {
      l.classList.toggle('active', l.getAttribute('href') === '#' + current);
    });
  }
  window.addEventListener('scroll', updateActiveNav, { passive: true });

  /* ── Escape helper ──────────────────────────────────── */
  function escHtml(str) {
    if (!str) return '';
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  /* ── Init ───────────────────────────────────────────── */
  fetchProducts();
})();
