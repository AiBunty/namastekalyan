(function () {
  function toggleSideNav(show) {
    var drawer = document.getElementById('sideNavContainer');
    var overlay = document.getElementById('sideNavOverlay');
    if (!drawer || !overlay) return;

    if (show) {
      drawer.classList.add('open');
      overlay.style.display = 'block';
      document.body.style.overflow = 'hidden';
    } else {
      drawer.classList.remove('open');
      overlay.style.display = 'none';
      document.body.style.overflow = '';
    }
  }

  function toggleFloatingActions(show) {
    var group = document.getElementById('floatingActionsGroup');
    var reveal = document.getElementById('floatingActionsReveal');
    if (!group || !reveal) return;
    group.classList.toggle('is-hidden', !show);
    reveal.classList.toggle('visible', !show);
  }

  window.toggleSideNav = toggleSideNav;
  window.toggleFloatingActions = toggleFloatingActions;

  function buildHeader() {
    return [
      '<div class="brand-strip">',
      '  <a class="brand-strip-logo" href="index.html" aria-label="Namaste Kalyan">',
      '    <img src="assets/Logo/Namaste Kalyan by AWG -04.png" alt="Namaste Kalyan logo">',
      '  </a>',
      '</div>',
      '<header class="concept-header">',
      '  <a class="concept-brand" href="index.html" aria-label="Namaste Kalyan home">',
      '    <img src="assets/Logo/Namaste Kalyan by AWG -02.png" alt="Namaste Kalyan by AWG logo">',
      '  </a>',
      '  <button class="hamburger-menu" onclick="toggleSideNav(true)" aria-label="Open navigation menu">',
      '    <span class="hamburger-line"></span>',
      '    <span class="hamburger-line"></span>',
      '    <span class="hamburger-line"></span>',
      '  </button>',
      '</header>',
      '<div id="sideNavOverlay" onclick="toggleSideNav(false)"></div>',
      '<div class="floating-actions-group" id="floatingActionsGroup">',
      '  <button class="floating-action-btn menu-action" onclick="toggleSideNav(true)" aria-label="Open navigation menu">MENU</button>',
      '  <a class="floating-action-btn icon-action whatsapp-action" href="https://wa.me/919371519999" target="_blank" rel="noopener noreferrer" aria-label="Chat on WhatsApp"><img src="assets/icons/social/whatsapp.svg" alt="WhatsApp" style="width:26px;height:26px;"></a>',
      '  <a class="floating-action-btn icon-action call-action" href="tel:+919371519999" aria-label="Call Namaste Kalyan">📞</a>',
      '  <button class="floating-action-btn hide-action" onclick="toggleFloatingActions(false)" aria-label="Hide floating actions">×</button>',
      '</div>',
      '<button id="floatingActionsReveal" onclick="toggleFloatingActions(true)" aria-label="Show floating actions">☰</button>',
      '<div id="sideNavContainer">',
      '  <a href="index.html" class="cat-link">🏠 Home</a>',
      '  <a href="menu.html" class="cat-link">🍽️ Food Menu</a>',
      '  <a href="cocktail.html" class="cat-link">🍸 Cocktail Menu</a>',
      '  <hr style="border: none; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 10px 0;">',
      '  <a href="index.html#menu" class="cat-link" onclick="toggleSideNav(false)">📋 Menus & Offers</a>',
      '  <a href="index.html#events" class="cat-link" onclick="toggleSideNav(false)">🎉 Events</a>',
      '  <a href="index.html#gallery" class="cat-link" onclick="toggleSideNav(false)">🖼️ Gallery</a>',
      '  <a href="index.html#contact" class="cat-link" onclick="toggleSideNav(false)">📞 Contact</a>',
      '  <hr style="border: none; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 10px 0;">',
      '  <a href="https://www.zomato.com/mumbai/namaste-kalyan-by-asian-wok-and-grill-kalyan-thane" target="_blank" rel="noopener noreferrer" class="cat-link">📅 Reserve Table</a>',
      '  <a href="https://www.swiggy.com/restaurants/namaste-kalyan-by-asian-wok-and-grill-kalyan-mumbai-1000913/dineout" target="_blank" rel="noopener noreferrer" class="cat-link">🛵 Order on Swiggy</a>',
      '  <a href="https://www.zomato.com/mumbai/namaste-kalyan-by-asian-wok-and-grill-kalyan-thane" target="_blank" rel="noopener noreferrer" class="cat-link">🍔 Order on Zomato</a>',
      '  <hr style="border: none; border-top: 1px solid rgba(255, 255, 255, 0.1); margin: 10px 0;">',
      '  <a href="https://www.instagram.com/namaste_kalyan_by_awg/" target="_blank" rel="noopener noreferrer" class="cat-link">📸 Instagram</a>',
      '  <a href="https://www.facebook.com/namastekalyanbyawg" target="_blank" rel="noopener noreferrer" class="cat-link">👍 Facebook</a>',
      '  <a href="https://wa.me/919371519999" target="_blank" rel="noopener noreferrer" class="cat-link">💬 WhatsApp</a>',
      '</div>'
    ].join('');
  }

  function buildFooter() {
    return [
      '<footer class="concept-footer">',
      '  <div class="footer-intro reveal">',
      '    <p class="eyebrow">Our Properties</p>',
      '    <h2>Three destinations, one hospitality group.</h2>',
      '  </div>',
      '  <div class="property-footer-grid">',
      '    <article class="property-footer-card reveal">',
      '      <div class="property-footer-head">',
      '        <img class="property-footer-logo" src="https://storage.files-vault.com/landing_pages/92217/21f05c4f652aa7dc9ced8c6426285775-21f05c4f652aa7dc9ced8c6426285775-AWG.webp" alt="Asian Wok and Grill logo">',
      '        <div>',
      '          <h3>Asian Wok &amp; Grill</h3>',
      '          <p>Gourmet kitchen for bold Asian plates, grills, and dine-out evenings in Nashik.</p>',
      '        </div>',
      '      </div>',
      '      <p class="property-footer-address">Shop no 1, Rushiraj Riviera, ahead of Jihan Circle, Gangapur Rd, Anandvalli, Maharashtra 422013.</p>',
      '      <div class="property-socials property-socials-icons">',
      '        <a class="property-social-link" href="https://www.facebook.com/asianwokandgrill" target="_blank" rel="noopener noreferrer" aria-label="Asian Wok and Grill on Facebook" title="Facebook"><img class="social-icon" src="assets/icons/social/facebook.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="https://www.instagram.com/asianwokandgrillnashik/" target="_blank" rel="noopener noreferrer" aria-label="Asian Wok and Grill on Instagram" title="Instagram"><img class="social-icon" src="assets/icons/social/instagram.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="https://wa.me/917269899999" target="_blank" rel="noopener noreferrer" aria-label="Asian Wok and Grill on WhatsApp" title="WhatsApp"><img class="social-icon" src="assets/icons/social/whatsapp.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="https://asianwokandgrill.in/" target="_blank" rel="noopener noreferrer" aria-label="Asian Wok and Grill website" title="Website"><img class="social-icon" src="assets/icons/social/website.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '      </div>',
      '      <div class="property-order-links">',
      '        <a href="https://www.swiggy.com/restaurants/asian-wok-and-grill-anandvalli-nashik-686655/dineout" target="_blank" rel="noopener noreferrer" aria-label="Order on Swiggy" title="Swiggy"><img src="assets/Online order Logo/vecteezy_swiggy-app-icon-with-transparent-background_56850705.png" alt="Swiggy" class="order-platform-logo"></a>',
      '        <a href="https://www.zomato.com/nashik/asian-wok-and-grill-anand-wali-goan" target="_blank" rel="noopener noreferrer" aria-label="Order on Zomato" title="Zomato"><img src="assets/Online order Logo/vecteezy_zomato-logo-png-zomato-icon-transparent-png_20975665.png" alt="Zomato" class="order-platform-logo"></a>',
      '      </div>',
      '    </article>',
      '    <article class="property-footer-card property-footer-card-featured reveal">',
      '      <div class="property-footer-head">',
      '        <img class="property-footer-logo" src="https://storage.files-vault.com/landing_pages/92217/e3dc4d8eaebe1fc0762560060a4e7409-e3dc4d8eaebe1fc0762560060a4e7409-nc.webp" alt="Namaste Chef logo">',
      '        <div>',
      '          <h3>Namaste Chef</h3>',
      '          <p>Signature multi-cuisine dining with chef specials, mocktails, and event-ready seating.</p>',
      '        </div>',
      '      </div>',
      '      <p class="property-footer-address">Nashik - Pune Rd, near Inox Signal, above Croma Store, Ganesh Baba Nagar, Nashik, Maharashtra 422011.</p>',
      '      <div class="property-socials property-socials-icons">',
      '        <a class="property-social-link" href="https://www.facebook.com/namastechefnashik/" target="_blank" rel="noopener noreferrer" aria-label="Namaste Chef on Facebook" title="Facebook"><img class="social-icon" src="assets/icons/social/facebook.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="https://www.instagram.com/namaste_chef_nashik/" target="_blank" rel="noopener noreferrer" aria-label="Namaste Chef on Instagram" title="Instagram"><img class="social-icon" src="assets/icons/social/instagram.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="https://wa.me/917378599999" target="_blank" rel="noopener noreferrer" aria-label="Namaste Chef on WhatsApp" title="WhatsApp"><img class="social-icon" src="assets/icons/social/whatsapp.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="https://namastechefawg.in/" target="_blank" rel="noopener noreferrer" aria-label="Namaste Chef website" title="Website"><img class="social-icon" src="assets/icons/social/website.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '      </div>',
      '      <div class="property-order-links">',
      '        <a href="https://www.swiggy.com/restaurants/namaste-chef-by-asian-wok-and-grill-nashik-road-nashik-road-nashik-786310/dineout" target="_blank" rel="noopener noreferrer" aria-label="Order on Swiggy" title="Swiggy"><img src="assets/Online order Logo/vecteezy_swiggy-app-icon-with-transparent-background_56850705.png" alt="Swiggy" class="order-platform-logo"></a>',
      '        <a href="https://www.zomato.com/nashik/namaste-chef-by-asian-wok-grill-upnagar" target="_blank" rel="noopener noreferrer" aria-label="Order on Zomato" title="Zomato"><img src="assets/Online order Logo/vecteezy_zomato-logo-png-zomato-icon-transparent-png_20975665.png" alt="Zomato" class="order-platform-logo"></a>',
      '        <a href="https://www.eazydiner.com/nashik/namaste-chef-by-asian-wok-grill-rane-nagar-nashik-697540" target="_blank" rel="noopener noreferrer">EazyDiner</a>',
      '      </div>',
      '    </article>',
      '    <article class="property-footer-card reveal">',
      '      <div class="property-footer-head">',
      '        <img class="property-footer-logo" src="assets/Logo/Namaste Kalyan by AWG -02.png" alt="Namaste Kalyan by AWG logo">',
      '        <div>',
      '          <h3>Namaste Kalyan</h3>',
      '          <p>Celebration-first dining in Kalyan with premium ambience, cocktails, and group-ready tables.</p>',
      '        </div>',
      '      </div>',
      '      <p class="property-footer-address">Rockmount Commercial Hub, 4th Floor, Khadakpada Circle, Kalyan West, Thane, Maharashtra 421301</p>',
      '      <div class="property-socials property-socials-icons">',
      '        <a class="property-social-link" href="https://www.facebook.com/namastekalyanbyawg" target="_blank" rel="noopener noreferrer" aria-label="Namaste Kalyan on Facebook" title="Facebook"><img class="social-icon" src="assets/icons/social/facebook.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="https://www.instagram.com/namaste_kalyan_by_awg/" target="_blank" rel="noopener noreferrer" aria-label="Namaste Kalyan on Instagram" title="Instagram"><img class="social-icon" src="assets/icons/social/instagram.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="https://wa.me/919371519999" target="_blank" rel="noopener noreferrer" aria-label="Namaste Kalyan on WhatsApp" title="WhatsApp"><img class="social-icon" src="assets/icons/social/whatsapp.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '        <a class="property-social-link" href="mailto:namastekalyan09@gmail.com" aria-label="Email Namaste Kalyan" title="Mail"><img class="social-icon" src="assets/icons/social/mail.svg?v=20260321-3" alt="" loading="lazy" decoding="async"></a>',
      '      </div>',
      '      <div class="property-order-links">',
      '        <a href="https://www.swiggy.com/restaurants/namaste-kalyan-by-asian-wok-and-grill-kalyan-mumbai-1000913/dineout" target="_blank" rel="noopener noreferrer" aria-label="Order on Swiggy" title="Swiggy"><img src="assets/Online order Logo/vecteezy_swiggy-app-icon-with-transparent-background_56850705.png" alt="Swiggy" class="order-platform-logo"></a>',
      '        <a href="https://www.zomato.com/mumbai/namaste-kalyan-by-asian-wok-and-grill-kalyan-thane" target="_blank" rel="noopener noreferrer" aria-label="Order on Zomato" title="Zomato"><img src="assets/Online order Logo/vecteezy_zomato-logo-png-zomato-icon-transparent-png_20975665.png" alt="Zomato" class="order-platform-logo"></a>',
      '      </div>',
      '    </article>',
      '  </div>',
      '</footer>'
    ].join('');
  }

  function mountSharedChrome() {
    var headerTarget = document.getElementById('nkSharedNavbar');
    if (headerTarget) {
      headerTarget.innerHTML = buildHeader();
    }

    var footerTarget = document.getElementById('nkSharedFooter');
    if (footerTarget) {
      footerTarget.innerHTML = buildFooter();
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountSharedChrome);
  } else {
    mountSharedChrome();
  }
})();
