(() => {
  'use strict';
  const endpoint = document.body.dataset.cuRequestNotifications;
  if (!endpoint) return;
  const badges = [...document.querySelectorAll('[data-cu-request-count]')];
  const chatBadges = [...document.querySelectorAll('[data-cu-chat-count]')];
  const totalBadges = [...document.querySelectorAll('[data-cu-notification-count]')];
  const allBadges = [...badges, ...chatBadges, ...totalBadges];
  const message = document.querySelector('[data-cu-request-message]');
  const chatMessage = document.querySelector('[data-cu-chat-message]');
  const bell = document.querySelector('.cu-notification-trigger');
  const announcer = document.createElement('span');
  announcer.className = 'cu-request-announcer';
  announcer.setAttribute('role', 'status');
  announcer.setAttribute('aria-live', 'polite');
  document.body.append(announcer);
  let timer, busy = false, stopped = false, previous = null;
  let chatRevision = 0;
  let lastCounts = {department:0,storage:0,returns:0,total:Number(document.body.dataset.cuInitialRequests || 0),chat:Number(document.body.dataset.cuInitialChat || 0)};

  function renderBadges(elements, count, description) {
    elements.forEach(badge => {
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.hidden = count === 0;
      const parent = badge.parentElement;
      const label = parent.dataset.requestBaseLabel ||= parent.getAttribute('aria-label') || parent.childNodes[0]?.textContent.trim() || 'Pending Actions';
      parent.setAttribute('aria-label', `${label}${count ? `, ${count} ${description}` : ''}`);
    });
  }
  function update(counts) {
    lastCounts = counts;
    const total = counts.total;
    const combined = total + counts.chat;
    renderBadges(badges, total, 'requests awaiting action');
    renderBadges(chatBadges, counts.chat, 'unread chat messages');
    renderBadges(totalBadges, combined, 'pending requests and unread messages');
    if (message) message.textContent = total ? `${total} ${total === 1 ? 'request' : 'requests'} awaiting action` : 'No requests awaiting action';
    if (chatMessage) chatMessage.textContent = counts.chat ? `${counts.chat} unread chat ${counts.chat === 1 ? 'message' : 'messages'}` : 'No unread chat messages';
    const link = message?.closest('a');
    if (link) link.title = `${counts.department} department requests, ${counts.storage} storage requests, ${counts.returns} returns`;
    const signature = `${total}:${counts.chat}`;
    if (previous !== null && previous !== signature) announcer.textContent = [message?.textContent,chatMessage?.textContent].filter(Boolean).join('. ');
    previous = signature;
  }
  function stop() {
    stopped = true;
    allBadges.forEach(badge => {
      badge.hidden = true;
      if (badge.parentElement.dataset.requestBaseLabel) badge.parentElement.setAttribute('aria-label', badge.parentElement.dataset.requestBaseLabel);
    });
    if (message) message.closest('a').hidden = true;
    if (chatMessage) chatMessage.closest('a').hidden = true;
    if (bell) bell.setAttribute('aria-label', 'Notifications');
  }
  async function refresh() {
    clearTimeout(timer);
    if (busy || stopped || document.hidden) return;
    busy = true;
    const fetchChatRevision = chatRevision;
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 10000);
    try {
      const response = await fetch(endpoint, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
      if (response.redirected || response.status === 401 || response.status === 403) { stop(); return; }
      if (!response.ok) return;
      const data = await response.json();
      if (!data.ok || !['department','storage','returns','total','chat'].every(key => Number.isSafeInteger(data.counts?.[key]) && data.counts[key] >= 0)) return;
      if (data.access?.requests === false) {
        data.counts.department = data.counts.storage = data.counts.returns = data.counts.total = 0;
        if (message) message.closest('a').hidden = true;
      }
      if (data.access?.chat === false) {
        data.counts.chat = 0;
        if (chatMessage) chatMessage.closest('a').hidden = true;
      } else if (fetchChatRevision !== chatRevision) data.counts.chat = lastCounts.chat;
      update(data.counts);
    } catch (_) { /* Keep the last successful count during temporary connection failures. */ }
    finally {
      clearTimeout(timeout);
      busy = false;
      if (!stopped && !document.hidden) timer = setTimeout(refresh, 30000);
    }
  }
  document.addEventListener('visibilitychange', () => { clearTimeout(timer); if (!document.hidden) refresh(); });
  window.addEventListener('focus', refresh);
  window.addEventListener('pageshow', refresh);
  bell?.addEventListener('click', refresh);
  document.addEventListener('cssr:chat-unread', event => {
    if (stopped || !Number.isSafeInteger(event.detail?.total) || event.detail.total < 0) return;
    chatRevision++;
    update({...lastCounts,chat:event.detail.total});
  });
  update(lastCounts);
  refresh();
})();
