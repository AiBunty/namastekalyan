window.NK_DATA_API = window.NK_DATA_API || {
  // Paste your deployed Google Apps Script web app URL here.
  // Example: https://script.google.com/macros/s/AKfycbwFb9UB7etHUaT4LsIYEnlyh5TfL1UH2P8_QkDYBPVMcTIsSPFVVPBe90698qBj8OPW/exec
  appsScriptUrl: 'https://script.google.com/macros/s/AKfycbycDXiXAgZf5l-V4v8cbu8DQEPh8QFYuyYx9XogEpEtiVx6IWXe3_xHmhA-vvQYuZ2E/exec',
  hotelWhatsappNo: '919371519999',
  adminPasscode: '8442'
};

// Backward-compatible global endpoint consumed by legacy and blocker scripts.
window.APPS_SCRIPT_URL = window.NK_DATA_API && window.NK_DATA_API.appsScriptUrl
  ? String(window.NK_DATA_API.appsScriptUrl).trim()
  : '';