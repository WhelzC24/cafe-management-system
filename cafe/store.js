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
  const navOverlay = $('mobileNavOverlay');

  function openMobileNav() {
    nav.classList.add('open');
    hamburger.classList.add('open');
    if (navOverlay) navOverlay.classList.add('open');
    document.body.style.overflow = 'hidden'; // prevent scroll behind drawer
  }

  function closeMobileNav() {
    nav.classList.remove('open');
    hamburger.classList.remove('open');
    if (navOverlay) navOverlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  if (hamburger && nav) {
    hamburger.addEventListener('click', () => {
      nav.classList.contains('open') ? closeMobileNav() : openMobileNav();
    });
    // Close on nav link click
    nav.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', closeMobileNav);
    });
    // Close when tapping the backdrop overlay
    if (navOverlay) {
      navOverlay.addEventListener('click', closeMobileNav);
    }
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

  function appendChatMessage(role, text, showTime = true) {
    if (!chatMessages) return;
    const emptyState = chatMessages.querySelector('.chat-messages-empty');
    if (emptyState) emptyState.remove();
    const item = document.createElement('div');
    item.className = 'chat-message ' + role;
    // Support **bold** markdown
    const formatted = String(text).replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    const textSpan = document.createElement('span');
    textSpan.innerHTML = formatted;
    item.appendChild(textSpan);
    if (showTime) {
      const timeSpan = document.createElement('span');
      timeSpan.className = 'chat-message-time';
      const now = new Date();
      timeSpan.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
      item.appendChild(timeSpan);
    }
    chatMessages.appendChild(item);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function showTypingIndicator() {
    if (!chatMessages) return;
    // Remove existing typing indicator
    const existing = chatMessages.querySelector('.chat-typing');
    if (existing) existing.remove();
    
    const typing = document.createElement('div');
    typing.className = 'chat-typing';
    typing.id = 'chat-typing-indicator';
    typing.innerHTML = '<span class="chat-typing-dot"></span><span class="chat-typing-dot"></span><span class="chat-typing-dot"></span>';
    chatMessages.appendChild(typing);
    chatMessages.scrollTop = chatMessages.scrollHeight;
  }

  function hideTypingIndicator() {
    const typing = $('chat-typing-indicator');
    if (typing) typing.remove();
  }

  function localFallbackAnswer(message) {
    const msg = String(message || '').toLowerCase();
    const hour = new Date().getHours();
    const greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    if (/hello|hi |hey|howdy/.test(msg)) return `${greet}! Welcome to Cozy Corner Café! ☕\n\nI can help with:\n• 📋 Menu & prices\n• 🕐 Opening hours\n• 📍 Location\n• 📦 Order tracking\n\nWhat can I get for you?`;
    if (/hour|open|close|schedule|when/.test(msg)) return '🕐 Opening Hours:\n\n• Mon – Fri: 7:00 AM – 8:00 PM\n• Saturday: 8:00 AM – 9:00 PM\n• Sunday: 8:00 AM – 9:00 PM';
    if (/where|location|address|find/.test(msg)) return '📍 We are located at:\n\nCuasi, Loon, Bohol\n\nCall us for directions: 📞 09361679546';
    if (/phone|call|contact|email/.test(msg)) return '📞 Contact us:\n\n• Phone: 09361679546\n• Email: wlaniba330@gmail.com';
    if (/track|status|ready|preparing/.test(msg)) return 'To track your order, send:\n• Your order number (e.g. #42)\n• Your phone number\n\nExample: "Track order #42 09361679546"';
    if (/order|pickup|buy|cart/.test(msg)) return '🛒 To order pickup:\n\n1. Scroll to "Order for Pickup"\n2. Add items to cart\n3. Enter name & phone\n4. Tap **Place My Order**';
    if (/pay|cash|gcash|maya/.test(msg)) return '💳 We accept: Cash, GCash, and PayMaya!';
    if (/wifi|wi-fi|internet/.test(msg)) return '📶 Yes, we have free Wi-Fi! Ask our staff for the password when you arrive.';
    if (/allergy|vegan|vegetarian|gluten/.test(msg)) return '🌿 For allergy/dietary info, please call us at 📞 09361679546 so we can confirm before you order.';
    if (/menu|coffee|food|drink|pastr/.test(msg)) return '📋 We have Coffee, Cold Drinks, Hot Drinks, Pastries, and Food!\n\nAsk \"Show me the menu\" to see items and prices, or ask about a specific item!';
    if (/recommend|best|popular/.test(msg)) return '⭐ Popular picks include our Signature Cappuccino, Iced Matcha Latte, and Cinnamon Roll!\n\nTell me what you prefer (coffee, cold, pastry) and I can suggest more!';
    if (/price|cost|how much/.test(msg)) return '💰 Ask me about a specific item (e.g. "How much is the Cappuccino?") and I\'ll tell you the price!';
    if (/thank/.test(msg)) return "You're welcome! 😊 Anything else I can help with?";
    if (/bye|goodbye/.test(msg)) return 'Goodbye! Hope to see you at Cozy Corner Café soon. ☕';
    return 'I can help with:\n\n• 📋 Menu & prices\n• ☕ Coffee recs\n• 🕐 Hours\n• 📍 Location\n• 📦 Order tracking\n• 💳 Payments\n\nWhat would you like to know?';
  }

  async function sendChatMessage(message) {
    const clean = String(message || '').trim();
    if (!clean) return;
    appendChatMessage('user', clean);
    pushChatHistory('user', clean);

    if (chatInput) chatInput.value = '';
    if (chatInput) chatInput.disabled = true;
    const sendBtn = document.querySelector('.chatbot-send');
    if (sendBtn) sendBtn.disabled = true;

    // Show typing indicator
    showTypingIndicator();

    // Simulate realistic typing delay (800-1500ms)
    const typingDelay = 800 + Math.random() * 700;

    try {
      // Add delay for better UX
      await new Promise(resolve => setTimeout(resolve, typingDelay));
      
      const res = await fetch('chatbot_api.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: clean, history: chatHistory })
      });

      if (!res.ok) throw new Error('Chat service unavailable');
      const data = await res.json();
      const answer = data.answer || localFallbackAnswer(clean);
      
      hideTypingIndicator();
      appendChatMessage('bot', answer);
      pushChatHistory('bot', answer);
    } catch (err) {
      hideTypingIndicator();
      const fallback = localFallbackAnswer(clean);
      appendChatMessage('bot', fallback);
      pushChatHistory('bot', fallback);
    } finally {
      if (chatInput) {
        chatInput.disabled = false;
        chatInput.focus();
      }
      if (sendBtn) sendBtn.disabled = false;
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
      const isOpen = chatWindow.classList.contains('open');
      if (isOpen) {
        chatWindow.classList.remove('open');
      } else {
        chatWindow.classList.add('open');
        if (chatInput) chatInput.focus();
      }
    });
    if (chatClose) {
      chatClose.addEventListener('click', () => {
        chatWindow.classList.remove('open');
      });
    }
  }

  if (chatMessages) {
    const hour = new Date().getHours();
    const greet = hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    const greeting = `${greet}! Welcome to Cozy Corner Café ☕\n\nI can help you with:\n• 📋 Menu & prices\n• 🕐 Opening hours & location\n• ☕ Coffee recommendations\n• 📦 Order tracking\n• 💳 Payment options\n\nWhat can I get for you today?`;
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
