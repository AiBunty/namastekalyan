/**
 * NK Admin SPA — Module Base Utilities
 * Loaded once in admin-portal.html before any module JS.
 * Provides: NK.MODULE_BASE (shared helpers), NK.MODULES (module registry)
 */
(function (window) {
  'use strict';
  var NK = window.NK || (window.NK = {});
  NK.MODULES = NK.MODULES || {};

  NK.MODULE_BASE = {
    /**
     * Dynamically load a CDN script once (idempotent).
     * Returns a Promise that resolves when loaded, rejects on error.
     */
    loadCdnScript: function (url) {
      return new Promise(function (resolve, reject) {
        var existing = document.querySelector('script[src="' + url + '"]');
        if (existing) {
          resolve();
          return;
        }
        var s = document.createElement('script');
        s.src = url;
        s.onload = function () { resolve(); };
        s.onerror = function () { reject(new Error('Failed to load: ' + url)); };
        document.head.appendChild(s);
      });
    },

    /**
     * Get IST date parts {year, month, day} from a Date or timestamp.
     * Uses Intl.DateTimeFormat for correct IST conversion.
     */
    getIstDateParts: function (value) {
      var formatter = new Intl.DateTimeFormat('en-CA', {
        timeZone: 'Asia/Kolkata',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
      });
      var parts = formatter.formatToParts(value instanceof Date ? value : new Date(value || Date.now()));
      return {
        year: (parts.find(function (p) { return p.type === 'year'; }) || {}).value || '0000',
        month: (parts.find(function (p) { return p.type === 'month'; }) || {}).value || '01',
        day: (parts.find(function (p) { return p.type === 'day'; }) || {}).value || '01'
      };
    },

    /** Returns today's date as YYYY-MM-DD string in IST. */
    todayIso: function () {
      var p = this.getIstDateParts(new Date());
      return p.year + '-' + p.month + '-' + p.day;
    },

    /** Escape HTML to prevent XSS in dynamically-built innerHTML. */
    escHtml: function (value) {
      return String(value == null ? '' : value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    },

    /** Format a date value for display in IST locale. */
    formatDate: function (value) {
      if (!value) return '-';
      var d = new Date(value);
      return isNaN(d.getTime())
        ? String(value)
        : d.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Kolkata' });
    },

    /** Format a number as INR currency string. */
    formatCurrency: function (value, currency) {
      return (currency || 'INR') + ' ' + Number(value || 0).toFixed(2);
    }
  };
})(window);
