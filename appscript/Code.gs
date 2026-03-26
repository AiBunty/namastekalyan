const SPREADSHEET_ID = '15-fEg0mC9oUcYi6WKW9hyrrtPyiLAvQk2Jhbk4uWYw8';

function doGet(e) {
  const params = (e && e.parameter) || {};
  const requestedTab = String(params.tab || '').trim();
  const shape = String(params.shape || 'grid').trim().toLowerCase();

  if (!requestedTab) {
    return jsonResponse({
      ok: false,
      error: 'TAB_REQUIRED',
      message: 'Missing required query parameter: tab'
    });
  }

  const spreadsheet = SpreadsheetApp.openById(SPREADSHEET_ID);
  const sheet = findSheetByNormalizedName(spreadsheet, requestedTab);

  if (!sheet) {
    return jsonResponse({
      ok: false,
      error: 'TAB_NOT_FOUND',
      requestedTab: requestedTab,
      availableTabs: spreadsheet.getSheets().map((item) => item.getName())
    });
  }

  const values = sheet.getDataRange().getDisplayValues();
  if (!values.length) {
    return jsonResponse({
      ok: false,
      error: 'EMPTY_SHEET',
      sourceTab: sheet.getName()
    });
  }

  const headers = values[0].map((header, index) => {
    const clean = String(header || '').trim();
    return clean || `__col_${index + 1}`;
  });

  const rows = values
    .slice(1)
    .filter((row) => row.some((cell) => String(cell || '').trim() !== ''));

  if (shape === 'records') {
    const records = rows.map((row) => {
      const record = {};
      headers.forEach((header, index) => {
        record[header] = row[index] || '';
      });
      return record;
    });

    return jsonResponse({
      ok: true,
      sourceTab: sheet.getName(),
      headers: headers,
      items: records,
      rowCount: records.length,
      fetchedAt: new Date().toISOString()
    });
  }

  return jsonResponse({
    ok: true,
    sourceTab: sheet.getName(),
    headers: headers,
    rows: rows,
    rowCount: rows.length,
    fetchedAt: new Date().toISOString()
  });
}

function jsonResponse(payload) {
  return ContentService
    .createTextOutput(JSON.stringify(payload))
    .setMimeType(ContentService.MimeType.JSON);
}

function findSheetByNormalizedName(spreadsheet, requestedTab) {
  const normalizedRequested = normalizeSheetName(requestedTab);
  const sheets = spreadsheet.getSheets();

  // Exact match first (fast path)
  const exact = spreadsheet.getSheetByName(requestedTab);
  if (exact) return exact;

  // Fallback: trim + collapse spaces + lowercase
  for (let i = 0; i < sheets.length; i++) {
    const candidate = sheets[i];
    if (normalizeSheetName(candidate.getName()) === normalizedRequested) {
      return candidate;
    }
  }

  return null;
}

function normalizeSheetName(name) {
  return String(name || '')
    .replace(/\u00a0/g, ' ')
    .trim()
    .replace(/\s+/g, ' ')
    .toLowerCase();
}