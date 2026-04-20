(function () {
  'use strict';

  var BUSY_KEYWORDS = ['save', 'update', 'create', 'approve', 'request', 'issue', 'reset', 'password'];

  function toLower(value) {
    return String(value || '').trim().toLowerCase();
  }

  function shouldAnimateButton(button) {
    if (!button) return false;

    var idText = toLower(button.id);
    var actionText = toLower(button.getAttribute('data-action'));
    var text = toLower(button.textContent);
    var all = idText + ' ' + actionText + ' ' + text;

    for (var i = 0; i < BUSY_KEYWORDS.length; i += 1) {
      if (all.indexOf(BUSY_KEYWORDS[i]) !== -1) return true;
    }

    return false;
  }

  function stopBusy(button) {
    if (!button) return;
    button.classList.remove('btn-saving');
    button.classList.add('btn-saved');
    window.setTimeout(function () {
      button.classList.remove('btn-saved');
    }, 420);

    var timer = button.__adminUxTimer;
    if (timer) {
      window.clearInterval(timer);
      button.__adminUxTimer = null;
    }
  }

  function startBusy(button) {
    if (!button || button.classList.contains('btn-saving')) return;

    button.classList.add('btn-saving');
    var startedAt = Date.now();

    button.__adminUxTimer = window.setInterval(function () {
      var stillInDom = document.body && document.body.contains(button);
      var settled = !button.disabled && Date.now() - startedAt > 350;

      if (!stillInDom || settled || Date.now() - startedAt > 12000) {
        stopBusy(button);
      }
    }, 150);
  }

  function wireButtons() {
    document.addEventListener('click', function (event) {
      var target = event.target;
      if (!(target instanceof HTMLElement)) return;

      var button = target.closest('button');
      if (!button) return;
      if (button.disabled) return;
      if (!shouldAnimateButton(button)) return;

      startBusy(button);
    }, true);
  }

  function createLoader() {
    var existing = document.getElementById('adminPageLoader');
    if (existing) return existing;

    var loader = document.createElement('div');
    loader.id = 'adminPageLoader';
    loader.className = 'admin-page-loader';
    loader.innerHTML = '<div class="admin-page-loader__spinner" aria-hidden="true"></div><div class="admin-page-loader__text">Loading Admin Workspace</div>';
    document.body.appendChild(loader);
    return loader;
  }

  function hideLoader() {
    var loader = document.getElementById('adminPageLoader');
    if (!loader) return;

    loader.classList.add('is-hidden');
    window.setTimeout(function () {
      if (loader && loader.parentNode) {
        loader.parentNode.removeChild(loader);
      }
    }, 340);
  }

  function showToast(message) {
    message = String(message || 'Saved successfully').trim();

    var existing = document.querySelector('.admin-toast:not(.is-leaving)');
    if (existing) {
      existing.classList.add('is-leaving');
      window.setTimeout(function () {
        if (existing && existing.parentNode) {
          existing.parentNode.removeChild(existing);
        }
      }, 240);
    }

    var toast = document.createElement('div');
    toast.className = 'admin-toast';
    toast.textContent = message;
    document.body.appendChild(toast);

    window.setTimeout(function () {
      if (toast && toast.parentNode) {
        toast.classList.add('is-leaving');
        window.setTimeout(function () {
          if (toast && toast.parentNode) {
            toast.parentNode.removeChild(toast);
          }
        }, 240);
      }
    }, 2800);
  }

  function init() {
    if (!document.body) return;

    createLoader();
    wireButtons();

    if (document.readyState === 'complete') {
      hideLoader();
      return;
    }

    window.addEventListener('load', function () {
      hideLoader();
    });

    window.setTimeout(function () {
      hideLoader();
    }, 2800);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  window.adminToast = showToast;
})();
