# 🌍 Country Picker Component

A modern, searchable country picker with flag emojis and international dialing codes. Perfect for mobile reservations and contact forms.

## Features

✨ **Modern Design**
- Clean, responsive UI with smooth animations
- Flag emojis for visual recognition
- Accessible keyboard navigation

🔍 **Searchable**
- Real-time search by country name
- Search by country code (e.g., "IN", "US", "GB")
- Search by dial code (e.g., "+91", "+1", "+44")

📱 **Mobile-First**
- Optimized for mobile screens
- Touch-friendly controls
- Smooth scrolling with custom scrollbars

♿ **Accessible**
- ARIA labels and descriptions
- Keyboard navigation support
- Focus management

🎨 **Customizable**
- Easy color theming
- Modifiable dimensions
- Animation speed control

## Files

- **`country-picker.js`** — Main JavaScript logic (290+ countries)
- **`country-picker.css`** — Styling and animations
- **`reservation-form-example.html`** — Full example with reservation form

## Quick Start

### 1. Basic HTML Setup

```html
<!DOCTYPE html>
<html>
<head>
    <link rel="stylesheet" href="country-picker.css">
</head>
<body>
    <!-- Container for the country picker -->
    <div id="mbCountryPickerContainer"></div>
    
    <!-- Hidden select for form submission -->
    <select id="mbCountryCode" name="countryCode">
        <option value="91">India</option>
    </select>

    <script src="country-picker.js"></script>
    <script>
        // Initialize the picker (auto-init runs on DOM ready)
        initCountryPicker('mbCountryPickerContainer', 'mbCountryCode');
    </script>
</body>
</html>
```

### 2. In Your Existing Form

```html
<form id="reservationForm">
    <!-- Other fields... -->
    
    <label for="mbCountryCode">Country</label>
    <div id="mbCountryPickerContainer"></div>
    <select id="mbCountryCode" name="countryCode" style="display: none;">
        <option value="91" selected>India</option>
    </select>

    <!-- Phone field for the number itself -->
    <input type="tel" name="phone" placeholder="9876543210" required>

    <button type="submit">Submit</button>
</form>

<script src="country-picker.js"></script>
```

### 3. Update Phone Display

Sync the dial code with a phone display element:

```javascript
const countryCode = document.getElementById('mbCountryCode');
const phoneDisplay = document.getElementById('phoneDial');

countryCode.addEventListener('change', (e) => {
    phoneDisplay.textContent = '+' + e.target.value;
});
```

## Usage with Form Submission

```javascript
document.getElementById('reservationForm').addEventListener('submit', (e) => {
    e.preventDefault();
    
    const formData = {
        countryCode: document.getElementById('mbCountryCode').value,
        phone: document.getElementById('guestPhone').value,
        // ... other fields
    };

    // Send to server
    fetch('/api/reservations', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    });
});
```

## Customization

### Change Default Country

Edit `country-picker.js` and find this line:

```javascript
// Set initial (India)
const indiaItem = list.querySelector('[data-code="IN"]');
```

Change `"IN"` to your desired country code:

```javascript
// Set initial (United States)
const usaItem = list.querySelector('[data-code="US"]');
if (usaItem) {
    usaItem.classList.add('selected');
    select.value = '1';
}
```

### Theme Colors

Edit `country-picker.css`. Key color variables:

```css
/* Main button color */
border-color: #ff6b35;  /* Change to your brand color */
background-color: #f9f9f9;

/* Selected item highlight */
background-color: #fff3e0;  /* Light highlight */
border-left: 3px solid #ff6b35;  /* Orange accent */
```

### Adjust Dropdown Width

```css
.country-picker-wrapper {
    width: 100%;  /* Change to min-width: 250px; or max-width: 400px; */
}
```

### Change Animation Speed

```css
.country-picker-panel {
    animation: slideDown 0.2s ease;  /* Change 0.2s to 0.4s or 0.1s */
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-8px);  /* Adjust offset */
    }
}
```

## Integration with Namaste Kalyan

### Step 1: Files Already Added

The following files are ready in your project:
- `country-picker.js` — 290+ countries with dialing codes
- `country-picker.css` — Responsive styling
- `reservation-form-example.html` — Complete working example

### Step 2: Link in Your HTML

In your main [index.html](index.html), add to the `<head>`:

```html
<link rel="stylesheet" href="country-picker.css">
```

And before closing `</body>`:

```html
<script src="country-picker.js"></script>
```

### Step 3: Add to Your Mobile Form

In your reservation or contact form:

```html
<label for="countryCode">Country *</label>
<div id="mbCountryPickerContainer"></div>
<select id="mbCountryCode" name="countryCode" style="display: none;">
    <option value="91" selected>India</option>
</select>
```

### Step 4: Use the Selected Value

The picker automatically updates a hidden `<select>` element with the dial code. Get it in your form handler:

```javascript
const dialCode = document.getElementById('mbCountryCode').value;
const phoneNumber = document.getElementById('guestPhone').value;
const fullPhone = '+' + dialCode + phoneNumber;
```

## Countries Included (290+)

Afghanistan, Albania, Algeria, Andorra, Angola, Argentina, Armenia, Australia, Austria, Azerbaijan, Bahrain, Bangladesh, Belarus, Belgium, Belize, Benin, Bhutan, Bolivia, Bosnia, Botswana, Brazil, Brunei, Bulgaria, Burkina Faso, Burundi, Cambodia, Cameroon, Canada, Cape Verde, Central African Republic, Chad, Chile, China, Colombia, Comoros, Congo, Costa Rica, Croatia, Cuba, Cyprus, Czech Republic, Denmark, Djibouti, Dominican Republic, Ecuador, Egypt, El Salvador, Equatorial Guinea, Eritrea, Estonia, Ethiopia, Fiji, Finland, France, Gabon, Gambia, Georgia, Germany, Ghana, Greece, Guatemala, Guinea, Guinea-Bissau, Guyana, Haiti, Honduras, Hungary, Iceland, **India (+91)**, Indonesia, Iran, Iraq, Ireland, Israel, Italy, Jamaica, Japan, Jordan, Kazakhstan, Kenya, Kuwait, Kyrgyzstan, Laos, Latvia, Lebanon, Lesotho, Liberia, Libya, Liechtenstein, Lithuania, Luxembourg, Madagascar, Malawi, Malaysia, Maldives, Mali, Malta, Mauritania, Mauritius, Mexico, Monaco, Mongolia, Montenegro, Morocco, Mozambique, Myanmar, Namibia, Nepal, Netherlands, New Zealand, Nicaragua, Niger, Nigeria, North Korea, Norway, Oman, Pakistan, Palestine, Panama, Papua New Guinea, Paraguay, Peru, Philippines, Poland, Portugal, Qatar, Romania, Russia, Rwanda, Saudi Arabia, Senegal, Serbia, Seychelles, Sierra Leone, Singapore, Slovakia, Slovenia, South Africa, South Korea, South Sudan, Spain, Sri Lanka, Sudan, Suriname, Sweden, Switzerland, Syria, Taiwan, Tajikistan, Tanzania, Thailand, Togo, Tunisia, Turkey, Turkmenistan, Uganda, Ukraine, United Arab Emirates, United Kingdom, **United States (+1)**, Uruguay, Uzbekistan, Venezuela, Vietnam, Yemen, Zambia, Zimbabwe...

## Mobile Optimization

The picker is fully responsive and optimized for mobile:

- **Tap-friendly** — Large touch targets
- **Portrait-optimized** — Works great on narrow screens
- **Smooth scrolling** — Custom scrollbar styling
- **Full-screen search** — Focus on search when opened

Example on mobile (portrait):

```
┌─────────────────────┐
│ 🇮🇳 India (+91) ▼ │
└─────────────────────┘
     ▼ Click to open ▼
┌─────────────────────┐
│ Search country...   │
├─────────────────────┤
│ 🇦🇫 Afghanistan   │
│ 🇦🇱 Albania +355  │
│ 🇮🇳 India  +91    │
│ 🇵🇰 Pakistan +92  │
│ 🇺🇸 USA     +1   │
└─────────────────────┘
```

## Accessibility

- ✅ ARIA labels on button
- ✅ Keyboard navigation (arrow keys, enter, escape)
- ✅ Semantic HTML structure
- ✅ Focus indicators
- ✅ Screen reader friendly

## Browser Support

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+
- Mobile browsers (iOS Safari, Chrome Android)

## Troubleshooting

### Picker Not Showing?

1. Check that `country-picker.js` is loaded **after** HTML
2. Ensure container ID matches: `mbCountryPickerContainer`
3. Verify CSS file is linked

### Values Not Updating?

1. Confirm hidden select ID is `mbCountryCode`
2. Check browser console for errors
3. Verify form is within the same document (not iframe)

### Styling Issues?

1. Check CSS file is loaded (no 404 error)
2. Ensure parent container has `position: relative` for dropdown positioning
3. Verify z-index values if dropdown is hidden behind other elements

## Performance

- **Lightweight** — ~8KB JS, ~2KB CSS
- **Fast search** — Real-time filtering on 290+ countries
- **No dependencies** — Vanilla JavaScript, no jQuery or frameworks
- **Cached** — Browser caches CSS/JS after first visit

## License & Attribution

Country data and flags use Unicode emoji. Standards-compliant and supported across all modern browsers.

---

**Ready to use!** Open [reservation-form-example.html](reservation-form-example.html) in your browser to see a working demo.
