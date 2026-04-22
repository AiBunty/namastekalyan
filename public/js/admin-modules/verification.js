/**
 * NK Admin SPA Module: Coupon Verification
 * Source: admin-verification.html
 */
(function (NK) {
  'use strict';
  NK.MODULES = NK.MODULES || {};
  NK.MODULES['verification'] = {
    _container: null,
    _authClient: null,
    _lastVerifyData: null,

    init: function (container, authClient) {
      this._container = container;
      this._authClient = authClient;
      this._lastVerifyData = null;

      var HOTEL_WHATSAPP_NO = (window.NK_DATA_API && window.NK_DATA_API.hotelWhatsappNo)
        ? String(window.NK_DATA_API.hotelWhatsappNo).replace(/\D/g, '')
        : '919371519999';

      var styleEl = document.createElement('style');
      styleEl.textContent = [
        '.vf-shell { min-height:60vh; }',
        '.vf-card { max-width:540px; margin:0 auto; }',
        '.vf-summary-line,.vf-detail-line { display:grid; grid-template-columns:130px 1fr; gap:8px; font-size:0.9rem; margin-bottom:6px; }',
        '.vf-summary-line { font-size:0.93rem; }',
        '.vf-label { color: var(--muted); }',
        '@media (max-width:768px) {',
        '  .vf-card { max-width:100%; }',
        '  .vf-summary-line,.vf-detail-line { grid-template-columns:1fr; gap:2px; }',
        '  .vf-shell .row { align-items:stretch; }',
        '  .vf-shell .row > * { flex: 1 1 100%; min-width:0; }',
        '  .vf-shell input, .vf-shell select { font-size:16px; }',
        '}',
        '@media (max-width:520px) {',
        '  .vf-card { padding:14px; }',
        '}'
      ].join('\n');
      container.appendChild(styleEl);

      container.innerHTML = '<div class="admin-module-centered vf-shell">'
        + '<div class="card vf-card">'
        + '<h1 style="margin:0 0 8px;">Staff Coupon Verification</h1>'
        + '<p style="margin:0 0 14px;">Check customer mobile number and redeem offer at billing counter.</p>'
        + '<input id="vfPhone" type="tel" inputmode="numeric" maxlength="10" placeholder="Enter 10-digit mobile" style="width:100%;margin-bottom:12px;">'
        + '<div class="row">'
        + '<button id="vfVerifyBtn" type="button">Check Offer</button>'
        + '<button id="vfRedeemBtn" class="secondary" type="button" disabled>Redeem Coupon</button>'
        + '<button id="vfRegenBtn" class="secondary" type="button">Generate Missing Coupon</button>'
        + '</div>'
        + '<select id="vfGiftOverride" style="width:100%;margin-top:12px;" aria-label="Gift override for coupon generation">'
        + '<option value="">Gift Override (use only if prize is Try Again)</option>'
        + '<option value="Dessert on the House">Dessert on the House</option>'
        + '<option value="Mocktail on the House">Mocktail on the House</option>'
        + '<option value="Aerated Drink on the House">Aerated Drink on the House</option>'
        + '<option value="Starter on the House">Starter on the House</option>'
        + '<option value="10% OFF">10% OFF</option>'
        + '<option value="15% OFF">15% OFF</option>'
        + '<option value="20% OFF">20% OFF</option>'
        + '<option value="25% OFF">25% OFF</option>'
        + '</select>'
        + '<div id="vfResult" style="margin-top:14px;padding:12px;min-height:44px;font-weight:700;border-radius:12px;">Awaiting verification...</div>'

        + '<div id="vfGiftSummary" style="display:none;margin-top:12px;padding:12px;background:rgba(108,74,50,0.06);border-radius:12px;">'
        + '<div style="color:#6c4a32;font-size:0.82rem;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;font-weight:800;">Winner Redemption Summary</div>'
        + '<div class="vf-summary-line"><span class="vf-label">Gift Item</span><span id="vfGiftPrize">-</span></div>'
        + '<div class="vf-summary-line"><span class="vf-label">Coupon Code</span><span id="vfGiftCoupon">-</span></div>'
        + '</div>'

        + '<div id="vfDetails" style="display:none;margin-top:14px;padding:12px;background:rgba(108,74,50,0.06);border-radius:12px;">'
        + ['Name','Mobile','Original Spin','Active Reward','Status','Coupon Code','DOB','Anniversary','Source','Timestamp'].map(function (label, i) {
            var id = ['vfDName','vfDPhone','vfDOriginalPrize','vfDActiveReward','vfDStatus','vfDCode','vfDDob','vfDAnn','vfDSource','vfDTime'][i];
            return '<div class="vf-detail-line">'
              + '<span class="vf-label">' + label + '</span><span id="' + id + '">-</span></div>';
          }).join('')
        + '</div>'

        + '<div id="vfCouponTools" style="display:none;margin-top:12px;padding:10px;background:rgba(108,74,50,0.06);border-radius:12px;">'
        + '<div id="vfCouponCodeView" style="font-size:0.95rem;font-weight:800;letter-spacing:0.8px;color:#6c4a32;margin-bottom:8px;word-break:break-all;">-</div>'
        + '<div class="row">'
        + '<button id="vfCopyBtn" type="button">Copy Coupon Code</button>'
        + '<button id="vfSendBtn" class="secondary" type="button">Send to Hotel WhatsApp</button>'
        + '</div></div>'
        + '</div></div>';

      var phoneInput = container.querySelector('#vfPhone');
      var verifyBtn = container.querySelector('#vfVerifyBtn');
      var redeemBtn = container.querySelector('#vfRedeemBtn');
      var regenBtn = container.querySelector('#vfRegenBtn');
      var giftOverride = container.querySelector('#vfGiftOverride');
      var resultEl = container.querySelector('#vfResult');
      var giftSummary = container.querySelector('#vfGiftSummary');
      var giftPrize = container.querySelector('#vfGiftPrize');
      var giftCoupon = container.querySelector('#vfGiftCoupon');
      var details = container.querySelector('#vfDetails');
      var couponTools = container.querySelector('#vfCouponTools');
      var couponCodeView = container.querySelector('#vfCouponCodeView');
      var copyBtn = container.querySelector('#vfCopyBtn');
      var sendBtn = container.querySelector('#vfSendBtn');
      var module = this;

      function setResult(text, ok) {
        resultEl.textContent = text;
        resultEl.style.background = ok ? 'rgba(77,129,100,0.12)' : 'rgba(164,83,72,0.1)';
        resultEl.style.color = ok ? '#246a38' : '#7a342b';
      }

      function setDetail(id, value) {
        var el = container.querySelector('#' + id);
        if (el) el.textContent = value || '-';
      }

      function updateRegenButtonState(data) {
        if (!regenBtn) return;
        if (data && data.canIssueSurprise) {
          regenBtn.textContent = 'Issue Surprise Coupon';
        } else if (data && data.activeRewardSource === 'surprise') {
          regenBtn.textContent = 'Regenerate Surprise Coupon';
        } else {
          regenBtn.textContent = 'Generate Missing Coupon';
        }
      }

      function renderDetails(data) {
        setDetail('vfDName', data.name);
        setDetail('vfDPhone', data.phone);
        setDetail('vfDOriginalPrize', data.originalPrize || data.prize);
        setDetail('vfDActiveReward', data.activeRewardLabel || '-');
        setDetail('vfDStatus', data.status);
        setDetail('vfDCode', data.couponCode || '-');
        setDetail('vfDDob', data.dob);
        setDetail('vfDAnn', data.anniversary);
        setDetail('vfDSource', data.source);
        setDetail('vfDTime', data.timestamp);
        details.style.display = 'block';

        var couponCode = String(data.couponCode || '').trim();
        var hasActiveReward = !!String(data.activeRewardLabel || '').trim();
        if (hasActiveReward) {
          giftPrize.textContent = data.activeRewardLabel || '-';
          giftCoupon.textContent = couponCode || 'Not generated yet';
          giftSummary.style.display = 'block';
        } else {
          giftSummary.style.display = 'none';
        }
        if (hasActiveReward && couponCode) {
          couponCodeView.textContent = couponCode;
          couponTools.style.display = 'block';
        } else {
          couponCodeView.textContent = '-';
          couponTools.style.display = 'none';
        }
        updateRegenButtonState(data);
      }

      function clearDetails() {
        ['vfDName','vfDPhone','vfDOriginalPrize','vfDActiveReward','vfDStatus','vfDCode','vfDDob','vfDAnn','vfDSource','vfDTime'].forEach(function (id) {
          setDetail(id, '-');
        });
        details.style.display = 'none';
        couponCodeView.textContent = '-';
        couponTools.style.display = 'none';
        giftSummary.style.display = 'none';
        updateRegenButtonState(null);
      }

      function apiGetVerification(action, extraParams) {
        var phone = String(phoneInput.value || '').trim();
        if (!/^\d{10}$/.test(phone)) {
          setResult('Enter a valid 10-digit mobile number.', false);
          return Promise.reject(new Error('Invalid phone'));
        }
        var params = Object.assign({ phone: phone }, extraParams || {});
        return authClient.apiGet(action, params);
      }

      verifyBtn.addEventListener('click', function () {
        verifyBtn.disabled = true;
        redeemBtn.disabled = true;
        module._lastVerifyData = null;
        clearDetails();
        apiGetVerification('verify')
          .then(function (data) {
            if (!data) return;
            renderDetails(data);
            if (data.canRedeem) {
              module._lastVerifyData = data;
              redeemBtn.disabled = false;
              setResult('Redeemable reward: ' + (data.activeRewardLabel || '-'), true);
              return;
            }

            if (data.canIssueSurprise) {
              module._lastVerifyData = data;
              setResult('Try Again customer found. Select a surprise reward to issue a coupon.', false);
              return;
            }

            if (data.activeRewardLabel) {
              setResult('Already Redeemed: ' + data.activeRewardLabel, false);
              return;
            }

            setResult('No redeemable reward found for this mobile number.', false);
          })
          .catch(function (err) {
            if (err.message !== 'Invalid phone') {
              setResult('Verification failed: ' + err.message, false);
            }
          })
          .finally(function () { verifyBtn.disabled = false; });
      });

      redeemBtn.addEventListener('click', function () {
        if (!module._lastVerifyData) {
          setResult('Please check offer first before redeem.', false);
          return;
        }
        redeemBtn.disabled = true;
        apiGetVerification('redeem')
          .then(function (data) {
            if (!data) return;
            setResult(data.message || ('Redeemed successfully: ' + (module._lastVerifyData.prize || '-')), true);
            setDetail('vfDStatus', 'Redeemed');
            if (module._lastVerifyData && module._lastVerifyData.couponCode) {
              setDetail('vfDCode', module._lastVerifyData.couponCode);
              couponCodeView.textContent = module._lastVerifyData.couponCode;
              couponTools.style.display = 'block';
            }
            redeemBtn.disabled = true;
          })
          .catch(function (err) {
            setResult('Redeem failed: ' + err.message, false);
            redeemBtn.disabled = false;
          });
      });

      phoneInput.addEventListener('input', function () {
        module._lastVerifyData = null;
        redeemBtn.disabled = true;
      });

      regenBtn.addEventListener('click', function () {
        regenBtn.disabled = true;
        var selectedGift = String(giftOverride.value || '').trim();
        apiGetVerification('regen_coupon', selectedGift ? { giftItem: selectedGift } : undefined)
          .then(function (data) {
            if (!data) return;
            if (data.couponCode) {
              if (data.prize) {
                setDetail('vfDActiveReward', data.prize);
                giftPrize.textContent = data.prize;
              }
              setDetail('vfDCode', data.couponCode);
              giftCoupon.textContent = data.couponCode;
              giftSummary.style.display = 'block';
              couponCodeView.textContent = data.couponCode;
              couponTools.style.display = 'block';
            }
            if (module._lastVerifyData) {
              module._lastVerifyData.couponCode = String(data.couponCode || module._lastVerifyData.couponCode || '');
              module._lastVerifyData.activeRewardLabel = String(data.prize || module._lastVerifyData.activeRewardLabel || '');
              module._lastVerifyData.activeRewardSource = String(data.rewardSource || module._lastVerifyData.activeRewardSource || '');
              module._lastVerifyData.canRedeem = true;
              module._lastVerifyData.canIssueSurprise = false;
            }
            redeemBtn.disabled = false;
            setResult(data.message || (data.couponCode ? 'Coupon generated: ' + data.couponCode : 'Coupon regenerated.'), true);
          })
          .catch(function (err) {
            setResult('Coupon generation failed: ' + err.message, false);
          })
          .finally(function () { regenBtn.disabled = false; });
      });

      copyBtn.addEventListener('click', function () {
        var code = String(couponCodeView.textContent || '').trim();
        if (!code || code === '-') { setResult('No coupon code available to copy.', false); return; }
        if (navigator.clipboard && navigator.clipboard.writeText) {
          navigator.clipboard.writeText(code)
            .then(function () { setResult('Coupon code copied.', true); })
            .catch(function () { fallbackCopy(code); });
        } else {
          fallbackCopy(code);
        }
        function fallbackCopy(text) {
          var temp = document.createElement('textarea');
          temp.value = text;
          document.body.appendChild(temp);
          temp.select();
          document.execCommand('copy');
          document.body.removeChild(temp);
          setResult('Coupon code copied.', true);
        }
      });

      sendBtn.addEventListener('click', function () {
        var code = String(couponCodeView.textContent || '').trim();
        if (!code || code === '-') { setResult('No coupon code available to send.', false); return; }
        if (!HOTEL_WHATSAPP_NO) { setResult('Hotel WhatsApp number is not configured.', false); return; }
        window.open('https://wa.me/' + HOTEL_WHATSAPP_NO + '?text=' + encodeURIComponent(code), '_blank', 'noopener');
        setResult('Opening WhatsApp with coupon code.', true);
      });
    },

    destroy: function () {
      this._lastVerifyData = null;
      this._container = null;
      this._authClient = null;
    }
  };
})(window.NK || (window.NK = {}));
