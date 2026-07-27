/**
 * Consultation Booking Pro - Form Logic
 * Production-Ready Implementation - FULLY FIXED
 */

document.addEventListener('DOMContentLoaded', () => {
    const root = document.getElementById('cbp-booking-app');
    if (!root) return;

    const config = {
        apiUrl: root.dataset.apiUrl,
        nonce: root.dataset.nonce,
        ajaxUrl: root.dataset.ajaxUrl || '/wp-admin/admin-ajax.php',
        timezone: root.dataset.timezone || '',
        timezoneLabel: root.dataset.timezoneLabel || '',
        form: document.getElementById('cbp-main-form'),
        submitBtn: document.getElementById('cbp-submit-btn'),
        errorBox: document.getElementById('cbp-error-alert'),
        successBox: document.getElementById('cbp-success-view')
    };

    const state = {
        selectedDate: '',
        selectedTime: '',
        isSubmitting: false,
        countries: [],
        availability: {}
    };

    /** Local calendar date YYYY-MM-DD — never use toISOString() (UTC shift bug). */
    const toLocalISODate = (d) => {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    };

    const formatDisplayDate = (isoDate) => {
        if (!isoDate) return '';
        const parts = isoDate.split('-');
        if (parts.length !== 3) return isoDate;
        const d = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, parseInt(parts[2], 10));
        if (Number.isNaN(d.getTime())) return isoDate;
        return d.toLocaleDateString(undefined, { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    };

    const updateSuccessNotifications = (result) => {
        const hideAll = ['res-email-student', 'res-email-counselor', 'res-calendar', 'res-email-failed', 'res-calendar-failed'];
        hideAll.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.style.display = 'none';
                el.textContent = '';
            }
        });

        const studentEl = document.getElementById('res-email-student');
        const counselorEl = document.getElementById('res-email-counselor');
        const calendarEl = document.getElementById('res-calendar');
        const emailFailedEl = document.getElementById('res-email-failed');
        const calendarFailedEl = document.getElementById('res-calendar-failed');

        if (result.student_email_sent && studentEl) {
            studentEl.textContent = '✓ A confirmation email has been sent to your inbox.';
            studentEl.style.display = 'block';
        }
        if (result.counselor_email_sent && counselorEl) {
            counselorEl.textContent = '✓ Your counselor has been notified of this booking.';
            counselorEl.style.display = 'block';
        }
        if (!result.student_email_sent || !result.counselor_email_sent) {
            if (emailFailedEl) {
                const errors = [];
                if (!result.student_email_sent) {
                    errors.push('Student email: ' + (result.student_email_error || 'wp_mail() returned false'));
                }
                if (!result.counselor_email_sent) {
                    errors.push('Counselor email: ' + (result.counselor_email_error || 'wp_mail() returned false'));
                }
                emailFailedEl.innerHTML = '⚠ Email delivery failed.<br>' + errors.map(e => '• ' + e).join('<br>');
                emailFailedEl.style.display = 'block';
            }
        }
        if (result.calendar_synced && calendarEl) {
            let msg = '✓ A Google Calendar invite has been sent to your email.';
            if (result.google_meet_link) {
                calendarEl.innerHTML = msg + ' <a href="' + result.google_meet_link + '" target="_blank" rel="noopener">Join Google Meet</a>';
            } else if (result.google_event_link) {
                calendarEl.innerHTML = msg + ' <a href="' + result.google_event_link + '" target="_blank" rel="noopener">View event</a>';
            } else {
                calendarEl.textContent = msg;
            }
            calendarEl.style.display = 'block';
        } else if (calendarFailedEl) {
            calendarFailedEl.textContent = '⚠ Calendar: ' + (result.calendar_error || 'Google Calendar event could not be created.');
            calendarFailedEl.style.display = 'block';
        }
    };

    // --- INITIALIZATION ---
    const init = async () => {
        setupOverlay();
        setupScheduling();
        setupFormValidation();
        await loadCountries();
        setupCustomSelects();
        setupWhatsAppSelector();
        
        // Handle pre-selected date if any
        const today = new Date();
        today.setDate(today.getDate() + 1);
        const initialDate = today.toISOString().split('T')[0];
        selectDate(initialDate);
    };

    // ==========================================
    // COUNTRY DATA - EMBEDDED FALLBACK
    // ==========================================
    const getEmbeddedCountries = () => {
        return [
            {"flag":"🇦🇫","name":"Afghanistan","code":"+93"},
            {"flag":"🇦🇱","name":"Albania","code":"+355"},
            {"flag":"🇩🇿","name":"Algeria","code":"+213"},
            {"flag":"🇦🇩","name":"Andorra","code":"+376"},
            {"flag":"🇦🇴","name":"Angola","code":"+244"},
            {"flag":"🇦🇷","name":"Argentina","code":"+54"},
            {"flag":"🇦🇲","name":"Armenia","code":"+374"},
            {"flag":"🇦🇺","name":"Australia","code":"+61"},
            {"flag":"🇦🇹","name":"Austria","code":"+43"},
            {"flag":"🇦🇿","name":"Azerbaijan","code":"+994"},
            {"flag":"🇧🇭","name":"Bahrain","code":"+973"},
            {"flag":"🇧🇩","name":"Bangladesh","code":"+880"},
            {"flag":"🇧🇾","name":"Belarus","code":"+375"},
            {"flag":"🇧🇪","name":"Belgium","code":"+32"},
            {"flag":"🇧🇷","name":"Brazil","code":"+55"},
            {"flag":"🇧🇳","name":"Brunei","code":"+673"},
            {"flag":"🇧🇬","name":"Bulgaria","code":"+359"},
            {"flag":"🇰🇭","name":"Cambodia","code":"+855"},
            {"flag":"🇨🇲","name":"Cameroon","code":"+237"},
            {"flag":"🇨🇦","name":"Canada","code":"+1"},
            {"flag":"🇨🇳","name":"China","code":"+86"},
            {"flag":"🇨🇴","name":"Colombia","code":"+57"},
            {"flag":"🇨🇷","name":"Costa Rica","code":"+506"},
            {"flag":"🇭🇷","name":"Croatia","code":"+385"},
            {"flag":"🇨🇾","name":"Cyprus","code":"+357"},
            {"flag":"🇨🇿","name":"Czechia","code":"+420"},
            {"flag":"🇩🇰","name":"Denmark","code":"+45"},
            {"flag":"🇪🇨","name":"Ecuador","code":"+593"},
            {"flag":"🇪🇬","name":"Egypt","code":"+20"},
            {"flag":"🇪🇪","name":"Estonia","code":"+372"},
            {"flag":"🇫🇮","name":"Finland","code":"+358"},
            {"flag":"🇫🇷","name":"France","code":"+33"},
            {"flag":"🇬🇪","name":"Georgia","code":"+995"},
            {"flag":"🇩🇪","name":"Germany","code":"+49"},
            {"flag":"🇬🇭","name":"Ghana","code":"+233"},
            {"flag":"🇬🇷","name":"Greece","code":"+30"},
            {"flag":"🇭🇺","name":"Hungary","code":"+36"},
            {"flag":"🇮🇸","name":"Iceland","code":"+354"},
            {"flag":"🇮🇳","name":"India","code":"+91"},
            {"flag":"🇮🇩","name":"Indonesia","code":"+62"},
            {"flag":"🇮🇷","name":"Iran","code":"+98"},
            {"flag":"🇮🇶","name":"Iraq","code":"+964"},
            {"flag":"🇮🇪","name":"Ireland","code":"+353"},
            {"flag":"🇮🇱","name":"Israel","code":"+972"},
            {"flag":"🇮🇹","name":"Italy","code":"+39"},
            {"flag":"🇯🇵","name":"Japan","code":"+81"},
            {"flag":"🇯🇴","name":"Jordan","code":"+962"},
            {"flag":"🇰🇿","name":"Kazakhstan","code":"+7"},
            {"flag":"🇰🇪","name":"Kenya","code":"+254"},
            {"flag":"🇰🇷","name":"South Korea","code":"+82"},
            {"flag":"🇰🇼","name":"Kuwait","code":"+965"},
            {"flag":"🇱🇻","name":"Latvia","code":"+371"},
            {"flag":"🇱🇧","name":"Lebanon","code":"+961"},
            {"flag":"🇱🇾","name":"Libya","code":"+218"},
            {"flag":"🇱🇹","name":"Lithuania","code":"+370"},
            {"flag":"🇱🇺","name":"Luxembourg","code":"+352"},
            {"flag":"🇲🇾","name":"Malaysia","code":"+60"},
            {"flag":"🇲🇻","name":"Maldives","code":"+960"},
            {"flag":"🇲🇹","name":"Malta","code":"+356"},
            {"flag":"🇲🇺","name":"Mauritius","code":"+230"},
            {"flag":"🇲🇽","name":"Mexico","code":"+52"},
            {"flag":"🇲🇩","name":"Moldova","code":"+373"},
            {"flag":"🇲🇨","name":"Monaco","code":"+377"},
            {"flag":"🇲🇳","name":"Mongolia","code":"+976"},
            {"flag":"🇲🇪","name":"Montenegro","code":"+382"},
            {"flag":"🇲🇦","name":"Morocco","code":"+212"},
            {"flag":"🇲🇲","name":"Myanmar","code":"+95"},
            {"flag":"🇳🇵","name":"Nepal","code":"+977"},
            {"flag":"🇳🇱","name":"Netherlands","code":"+31"},
            {"flag":"🇳🇿","name":"New Zealand","code":"+64"},
            {"flag":"🇳🇬","name":"Nigeria","code":"+234"},
            {"flag":"🇳🇴","name":"Norway","code":"+47"},
            {"flag":"🇴🇲","name":"Oman","code":"+968"},
            {"flag":"🇵🇰","name":"Pakistan","code":"+92"},
            {"flag":"🇵🇸","name":"Palestine","code":"+970"},
            {"flag":"🇵🇦","name":"Panama","code":"+507"},
            {"flag":"🇵🇾","name":"Paraguay","code":"+595"},
            {"flag":"🇵🇪","name":"Peru","code":"+51"},
            {"flag":"🇵🇭","name":"Philippines","code":"+63"},
            {"flag":"🇵🇱","name":"Poland","code":"+48"},
            {"flag":"🇵🇹","name":"Portugal","code":"+351"},
            {"flag":"🇶🇦","name":"Qatar","code":"+974"},
            {"flag":"🇷🇴","name":"Romania","code":"+40"},
            {"flag":"🇷🇺","name":"Russia","code":"+7"},
            {"flag":"🇸🇦","name":"Saudi Arabia","code":"+966"},
            {"flag":"🇷🇸","name":"Serbia","code":"+381"},
            {"flag":"🇸🇬","name":"Singapore","code":"+65"},
            {"flag":"🇸🇰","name":"Slovakia","code":"+421"},
            {"flag":"🇸🇮","name":"Slovenia","code":"+386"},
            {"flag":"🇿🇦","name":"South Africa","code":"+27"},
            {"flag":"🇪🇸","name":"Spain","code":"+34"},
            {"flag":"🇱🇰","name":"Sri Lanka","code":"+94"},
            {"flag":"🇸🇪","name":"Sweden","code":"+46"},
            {"flag":"🇨🇭","name":"Switzerland","code":"+41"},
            {"flag":"🇹🇼","name":"Taiwan","code":"+886"},
            {"flag":"🇹🇭","name":"Thailand","code":"+66"},
            {"flag":"🇹🇳","name":"Tunisia","code":"+216"},
            {"flag":"🇹🇷","name":"Turkey","code":"+90"},
            {"flag":"🇺🇬","name":"Uganda","code":"+256"},
            {"flag":"🇺🇦","name":"Ukraine","code":"+380"},
            {"flag":"🇦🇪","name":"United Arab Emirates","code":"+971"},
            {"flag":"🇬🇧","name":"United Kingdom","code":"+44"},
            {"flag":"🇺🇸","name":"United States","code":"+1"},
            {"flag":"🇺🇾","name":"Uruguay","code":"+598"},
            {"flag":"🇺🇿","name":"Uzbekistan","code":"+998"},
            {"flag":"🇻🇪","name":"Venezuela","code":"+58"},
            {"flag":"🇻🇳","name":"Vietnam","code":"+84"},
            {"flag":"🇾🇪","name":"Yemen","code":"+967"},
            {"flag":"🇿🇲","name":"Zambia","code":"+260"},
            {"flag":"🇿🇼","name":"Zimbabwe","code":"+263"}
        ];
    };

    const loadCountries = async () => {
        try {
            const res = await fetch(`${config.apiUrl}/countries`, {
                headers: { 'X-WP-Nonce': config.nonce }
            });
            if (res.ok) {
                const data = await res.json();
                if (Array.isArray(data) && data.length > 0) {
                    state.countries = data;
                    console.log('Countries loaded from API:', state.countries.length);
                } else {
                    throw new Error('Empty response');
                }
            } else {
                throw new Error('API failed');
            }
        } catch (e) {
            console.warn('Failed to load countries from API, using embedded list');
            state.countries = getEmbeddedCountries();
        }
        renderCountryTypeLists();
        renderWhatsAppCountries();
    };

    // --- OVERLAY & DROPDOWN CORE ---
    const setupOverlay = () => {
        let overlay = document.querySelector('.cbp-dropdown-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'cbp-dropdown-overlay';
            document.body.appendChild(overlay);
        }
        overlay.onclick = closeAllDropdowns;
    };

    const closeAllDropdowns = () => {
        document.querySelectorAll('.cbp-select-panel, .cbp-country-panel').forEach(p => p.classList.remove('show'));
        document.querySelectorAll('.cbp-select-trigger, .cbp-country-trigger').forEach(t => t.classList.remove('active'));
        const overlay = document.querySelector('.cbp-dropdown-overlay');
        if (overlay) overlay.style.display = 'none';
    };

    const togglePanel = (trigger, panel) => {
        const isOpen = panel.classList.contains('show');
        closeAllDropdowns();
        
        if (!isOpen) {
            panel.classList.add('show');
            trigger.classList.add('active');
            
            const rect = trigger.getBoundingClientRect();
            const panelHeight = 350;
            const spaceBelow = window.innerHeight - rect.bottom;
            
            panel.style.position = 'fixed';
            panel.style.left = rect.left + 'px';
            
            if (trigger.closest('.cbp-whatsapp-group')) {
                const group = trigger.closest('.cbp-whatsapp-group');
                panel.style.width = Math.max(320, group.offsetWidth) + 'px';
            } else {
                panel.style.width = Math.max(rect.width, 280) + 'px';
            }
            
            panel.style.zIndex = '999999';

            if (spaceBelow < panelHeight && rect.top > panelHeight) {
                panel.style.top = 'auto';
                panel.style.bottom = (window.innerHeight - rect.top + 5) + 'px';
                panel.classList.add('open-up');
            } else {
                panel.style.bottom = 'auto';
                panel.style.top = (rect.bottom + 5) + 'px';
                panel.classList.remove('open-up');
            }

            const overlay = document.querySelector('.cbp-dropdown-overlay');
            if (overlay) overlay.style.display = 'block';
            
            const search = panel.querySelector('input');
            if (search) {
                search.value = '';
                const clearBtn = panel.querySelector('.cbp-search-clear');
                if (clearBtn) clearBtn.classList.remove('show');
                panel.querySelectorAll('.cbp-option, .cbp-country-item').forEach(o => o.style.display = '');
                setTimeout(() => search.focus(), 100);
            }
        }
    };

    // --- CUSTOM SELECTS (Country, Study Level, Field, Time) ---
    const setupCustomSelects = () => {
        document.querySelectorAll('.cbp-custom-select').forEach(wrapper => {
            const trigger = wrapper.querySelector('.cbp-select-trigger');
            const panel = wrapper.querySelector('.cbp-select-panel');
            const hidden = wrapper.querySelector('input[type="hidden"]');

            if (!trigger || !panel) return;

            // Remove old click handler by cloning
            const newTrigger = trigger.cloneNode(true);
            trigger.parentNode.replaceChild(newTrigger, trigger);
            
            newTrigger.onclick = (e) => {
                e.stopPropagation();
                e.preventDefault();
                const currentPanel = wrapper.querySelector('.cbp-select-panel');
                togglePanel(newTrigger, currentPanel);
            };

            // Setup click handlers on ALL options
            const setupOptionClicks = () => {
                panel.querySelectorAll('.cbp-option').forEach(opt => {
                    // Remove old handler
                    const newOpt = opt.cloneNode(true);
                    opt.parentNode.replaceChild(newOpt, opt);
                    
                    newOpt.onclick = (e) => {
                        e.stopPropagation();
                        e.preventDefault();
                        selectOptionDirectly(wrapper, newOpt);
                    };
                });
            };

            setupOptionClicks();

            // Setup search if exists
            const search = panel.querySelector('.cbp-panel-search input');
            if (search) {
                const clearBtn = panel.querySelector('.cbp-search-clear');
                
                search.onclick = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                };
                
                search.oninput = (e) => {
                    e.stopPropagation();
                    const val = e.target.value.toLowerCase();
                    if (clearBtn) clearBtn.classList.toggle('show', val.length > 0);
                    panel.querySelectorAll('.cbp-option').forEach(opt => {
                        const txt = opt.textContent.toLowerCase();
                        opt.style.display = txt.includes(val) ? 'flex' : 'none';
                    });
                };

                if (clearBtn) {
                    clearBtn.onclick = (e) => {
                        e.stopPropagation();
                        e.preventDefault();
                        search.value = '';
                        panel.querySelectorAll('.cbp-option').forEach(o => o.style.display = 'flex');
                        clearBtn.classList.remove('show');
                        search.focus();
                    };
                }

                search.onkeydown = (e) => {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const first = Array.from(panel.querySelectorAll('.cbp-option'))
                            .find(o => o.style.display !== 'none');
                        if (first) selectOptionDirectly(wrapper, first);
                    } else if (e.key === 'Escape') {
                        closeAllDropdowns();
                    }
                };
            }

            // Panel click should not close
            panel.onclick = (e) => {
                e.stopPropagation();
            };
        });
    };

    // Direct option selection - FIXED
    const selectOptionDirectly = (wrapper, opt) => {
        const triggerVal = wrapper.querySelector('.val');
        const hidden = wrapper.querySelector('input[type="hidden"]');
        const val = opt.dataset.value;
        const text = opt.textContent.trim();

        if (triggerVal) triggerVal.textContent = text;
        if (hidden) {
            hidden.value = val;
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        // Update visual selection
        const panel = wrapper.querySelector('.cbp-select-panel');
        if (panel) {
            panel.querySelectorAll('.cbp-option').forEach(o => {
                o.classList.remove('selected');
                const check = o.querySelector('.cbp-check-icon');
                if (check) check.style.display = 'none';
            });
        }

        opt.classList.add('selected');
        const activeCheck = opt.querySelector('.cbp-check-icon');
        if (activeCheck) activeCheck.style.display = 'block';
        
        // Synchronize with time chips if it's the time selector
        if (wrapper.dataset.id === 'booking_time') {
            state.selectedTime = val;
            document.querySelectorAll('.cbp-time-chip').forEach(c => {
                c.classList.toggle('active', c.dataset.time === val);
            });
        }

        setTimeout(closeAllDropdowns, 100);
        
        // Validate the field
        if (hidden) validateField(hidden);
    };

    // Keep old function for backward compatibility
    const selectOption = (wrapper, opt) => {
        selectOptionDirectly(wrapper, opt);
    };

    const renderCountryTypeLists = () => {
        document.querySelectorAll('.cbp-option-list[data-type="country"]').forEach(list => {
            const wrapper = list.closest('.cbp-custom-select');
            if (!wrapper) return;
            
            list.innerHTML = '';
            if (state.countries.length === 0) {
                list.innerHTML = '<div class="cbp-option" style="color:#94a3b8;">Loading countries...</div>';
                return;
            }
            
            state.countries.forEach(c => {
                const opt = document.createElement('div');
                opt.className = 'cbp-option';
                opt.dataset.value = c.name;
                opt.innerHTML = `
                    <span style="display:flex; align-items:center; gap:10px; width:100%;">
                        <span style="font-size:18px;">${c.flag}</span> 
                        <span style="flex:1;">${c.name}</span>
                    </span>
                `;
                opt.onclick = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    selectOptionDirectly(wrapper, opt);
                };
                list.appendChild(opt);
            });
        });
    };

    // --- WHATSAPP SELECTOR ---
    let whatsAppInitialized = false;
    
    const setupWhatsAppSelector = () => {
        if (whatsAppInitialized) return;
        
        const group = document.querySelector('.cbp-whatsapp-group');
        if (!group) return;

        const trigger = group.querySelector('.cbp-country-trigger');
        const panel = group.querySelector('.cbp-country-panel');
        
        if (!trigger || !panel) return;

        // Remove old handler
        const newTrigger = trigger.cloneNode(true);
        trigger.parentNode.replaceChild(newTrigger, trigger);

        newTrigger.onclick = (e) => {
            e.stopPropagation();
            e.preventDefault();
            togglePanel(newTrigger, panel);
        };

        const search = panel.querySelector('.cbp-panel-search input');
        if (search) {
            const clearBtn = panel.querySelector('.cbp-search-clear');
            
            search.onclick = (e) => {
                e.stopPropagation();
                e.preventDefault();
            };
            
            search.oninput = (e) => {
                e.stopPropagation();
                const val = e.target.value.toLowerCase().replace('+', '');
                if (clearBtn) clearBtn.classList.toggle('show', val.length > 0);
                
                if (state.countries.length === 0) return;
                
                const filtered = state.countries.filter(c => {
                    const dial = (c.code || '').toLowerCase().replace('+', '');
                    return c.name.toLowerCase().includes(val) || dial.includes(val);
                });
                renderWhatsAppCountryItems(filtered);
            };

            if (clearBtn) {
                clearBtn.onclick = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    search.value = '';
                    if (state.countries.length > 0) renderWhatsAppCountryItems(state.countries);
                    clearBtn.classList.remove('show');
                    search.focus();
                };
            }

            search.onkeydown = (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    const firstItem = panel.querySelector('.cbp-country-item');
                    if (firstItem) firstItem.click();
                } else if (e.key === 'Escape') {
                    closeAllDropdowns();
                }
            };
        }

        panel.onclick = (e) => {
            e.stopPropagation();
        };

        whatsAppInitialized = true;
        
        // Render countries if already loaded
        if (state.countries.length > 0) {
            renderWhatsAppCountryItems(state.countries);
        }
    };

    const renderWhatsAppCountries = () => {
        if (state.countries.length > 0) {
            renderWhatsAppCountryItems(state.countries);
        }
    };

    const renderWhatsAppCountryItems = (items) => {
        const panel = document.querySelector('.cbp-country-panel');
        if (!panel) return;
        
        const list = panel.querySelector('.cbp-country-list');
        if (!list) return;
        
        list.innerHTML = '';
        
        if (items.length === 0) {
            list.innerHTML = '<div class="cbp-country-item" style="color:#94a3b8; cursor:default;">No countries found</div>';
            return;
        }
        
        items.forEach(c => {
            const row = document.createElement('div');
            row.className = 'cbp-country-item';
            const dial = c.code || '';
            row.innerHTML = `
                <span class="flag" style="font-size:20px;">${c.flag}</span> 
                <span class="name" style="flex-grow:1; font-size:14px; font-weight:500; color:#334155;">${c.name}</span> 
                <span class="code" style="color:#64748b; font-size:13px; font-weight:600;">${dial}</span>
            `;
            row.onclick = (e) => {
                e.stopPropagation();
                e.preventDefault();
                
                const trigger = document.querySelector('.cbp-country-trigger');
                if (trigger) {
                    const flagEl = trigger.querySelector('.flag');
                    const dialEl = trigger.querySelector('.dial');
                    if (flagEl) flagEl.textContent = c.flag;
                    if (dialEl) dialEl.textContent = dial;
                }
                
                const dialCodeInput = document.getElementById('cbp-dial-code');
                if (dialCodeInput) {
                    dialCodeInput.value = dial;
                    dialCodeInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
                
                const phoneInput = document.querySelector('.cbp-phone-field');
                if (phoneInput) {
                    phoneInput.placeholder = getPlaceholderForCountry(c.name);
                }
                
                panel.querySelectorAll('.cbp-country-item').forEach(i => i.classList.remove('selected'));
                row.classList.add('selected');

                setTimeout(closeAllDropdowns, 100);
                
                const hiddenDial = document.getElementById('cbp-dial-code');
                if (hiddenDial) validateField(hiddenDial);
            };
            list.appendChild(row);
        });
    };

    const getPlaceholderForCountry = (name) => {
        const placeholders = {
            'Pakistan': '300 0000000',
            'United States': '212 555 1234',
            'United Kingdom': '7700 900000',
            'India': '98765 43210',
            'Canada': '416 555 1234',
            'Australia': '400 000 000',
            'Germany': '151 12345678',
            'France': '612 34 56 78',
            'Italy': '312 345 6789',
            'Spain': '612 34 56 78',
            'Brazil': '11 91234 5678',
            'Japan': '090 1234 5678',
            'South Korea': '010 1234 5678',
            'China': '138 0013 8000',
            'Russia': '912 345 67 89',
            'South Africa': '82 123 4567',
            'Nigeria': '803 123 4567',
            'Kenya': '712 345 678',
            'Egypt': '100 123 4567',
            'Saudi Arabia': '50 123 4567',
            'UAE': '50 123 4567',
            'Turkey': '530 123 45 67',
            'Malaysia': '12 345 6789',
            'Indonesia': '812 3456 7890',
            'Philippines': '912 345 6789',
            'Vietnam': '91 234 56 78',
            'Thailand': '81 234 5678',
            'Mexico': '55 1234 5678',
            'Argentina': '11 1234 5678',
            'Colombia': '300 123 4567'
        };
        return placeholders[name] || '123 4567 890';
    };

    // --- SCHEDULING ---
    const setupScheduling = () => {
        const dateCards = document.getElementById('cbp-date-cards');
        const datePicker = document.getElementById('cbp-date-picker');

        const generateDateCards = () => {
            if (!dateCards) return;
            dateCards.innerHTML = '';
            const now = new Date();
            for (let i = 1; i <= 7; i++) {
                const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i);
                const dateIso = toLocalISODate(d);
                
                const card = document.createElement('div');
                card.className = 'cbp-date-card';
                card.dataset.date = dateIso;
                if (i === 7) card.classList.add('card-7');
                
                card.innerHTML = `
                    <span class="day">${d.toLocaleDateString('en-US', {weekday:'short'}).toUpperCase()}</span>
                    <span class="num">${d.getDate()}</span>
                `;
                card.onclick = () => selectDate(dateIso);
                dateCards.appendChild(card);
            }
        };

        generateDateCards();
        
        if (datePicker) {
            datePicker.onchange = (e) => {
                const selectedDate = e.target.value;
                selectDate(selectedDate);
            };
            
            // Make the calendar icon clickable
            const calendarIcon = datePicker.parentElement.querySelector('.cbp-calendar-icon-overlay');
            if (calendarIcon) {
                calendarIcon.onclick = () => {
                    if (datePicker.showPicker) {
                        datePicker.showPicker();
                    } else {
                        datePicker.click();
                    }
                };
            }
        }
    };

    const selectDate = async (date) => {
        state.selectedDate = date;
        state.selectedTime = '';
        
        const picker = document.getElementById('cbp-date-picker');
        if (picker) picker.value = date;
        
        document.querySelectorAll('.cbp-date-card').forEach(c => {
            c.classList.toggle('active', c.dataset.date === date);
        });

        // Clear time selection
        const timeWrapper = document.querySelector('.cbp-custom-select[data-id="booking_time"]');
        if (timeWrapper) {
            const triggerVal = timeWrapper.querySelector('.val');
            const hidden = timeWrapper.querySelector('input[type="hidden"]');
            if (triggerVal) triggerVal.textContent = 'Select a time';
            if (hidden) hidden.value = '';
        }
        document.querySelectorAll('.cbp-time-chip').forEach(c => c.classList.remove('active'));

        const timeGrid = document.getElementById('cbp-time-grid');
        const timeList = document.getElementById('cbp-time-option-list');
        
        if (timeGrid) {
            timeGrid.innerHTML = '<div style="grid-column:span 4; text-align:center; padding:20px;"><span class="cbp-spinner" style="border-top-color:#ec1e28; display:inline-block;"></span> <span style="color:#64748b; font-size:13px;">Loading times...</span></div>';
        }
        if (timeList) {
            timeList.innerHTML = '<div class="cbp-option" style="color:#94a3b8; cursor:default; text-align:center;">Loading...</div>';
        }

        try {
            const formData = new FormData();
            formData.append('action', 'cbp_get_available_slots');
            formData.append('date', date);
            formData.append('nonce', config.nonce);

            const res = await fetch(config.ajaxUrl, {
                method: 'POST',
                body: formData
            });
            
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch (parseErr) {
                console.error('Invalid JSON response:', text);
                throw new Error('Server returned invalid response');
            }
            
            if (data.success && Array.isArray(data.data.slots)) {
                if (data.data.timezone_label) {
                    config.timezoneLabel = data.data.timezone_label;
                    config.timezone = data.data.timezone || config.timezone;
                }
                renderSlots(data.data.slots);
            } else {
                throw new Error(data.data?.message || 'No available times');
            }
        } catch (e) {
            console.error('Time slot fetch error:', e);
            if (timeGrid) {
                timeGrid.innerHTML = '<p style="color:#64748b; grid-column:span 4; text-align:center; font-size:13px; padding:20px;">No available times for this date.<br><small>Try selecting a different date.</small></p>';
            }
            if (timeList) {
                timeList.innerHTML = '<div class="cbp-option" style="color:#94a3b8; cursor:default; text-align:center;">No available times</div>';
            }
        }
    };

    const renderSlots = (slots) => {
        const timeGrid = document.getElementById('cbp-time-grid');
        const timeList = document.getElementById('cbp-time-option-list');
        const wrapper = document.querySelector('.cbp-custom-select[data-id="booking_time"]');

        if (timeGrid) timeGrid.innerHTML = '';
        if (timeList) timeList.innerHTML = '';
        
        if (!wrapper) return;
        
        const triggerVal = wrapper.querySelector('.val');
        const hidden = wrapper.querySelector('input[type="hidden"]');
        
        if (triggerVal) triggerVal.textContent = 'Select a time';
        if (hidden) hidden.value = '';

        if (!slots || slots.length === 0) {
            if (timeGrid) {
                timeGrid.innerHTML = '<p style="grid-column:span 4; font-size:14px; text-align:center; color:#94a3b8; padding:20px;">No available times for this date.</p>';
            }
            if (timeList) {
                timeList.innerHTML = '<div class="cbp-option disabled" style="color:#94a3b8; cursor:default; text-align:center;">No available times</div>';
            }
            return;
        }

        slots.forEach((time_slot, idx) => {
            const displayTime = time_slot;
            
            // Grid Chips (top 4)
            if (idx < 4 && timeGrid) {
                const chip = document.createElement('div');
                chip.className = 'cbp-time-chip';
                chip.dataset.time = time_slot;
                chip.textContent = displayTime;
                chip.onclick = (e) => {
                    e.stopPropagation();
                    selectTime(time_slot);
                };
                timeGrid.appendChild(chip);
            }
            
            // Custom Dropdown options
            if (timeList) {
                const opt = document.createElement('div');
                opt.className = 'cbp-option';
                opt.dataset.value = time_slot;
                opt.innerHTML = `
                    <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                        <span>${displayTime}</span>
                        <svg class="cbp-check-icon" style="display:none; color:#ec1e28;" viewBox="0 0 24 24" width="14" height="14"><path d="M5 13l4 4L19 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </div>
                `;
                opt.onclick = (e) => {
                    e.stopPropagation();
                    e.preventDefault();
                    selectOptionDirectly(wrapper, opt);
                };
                timeList.appendChild(opt);
            }
        });
    };

    const selectTime = (time) => {
        state.selectedTime = time;
        const wrapper = document.querySelector('.cbp-custom-select[data-id="booking_time"]');
        if (!wrapper) return;
        
        const triggerVal = wrapper.querySelector('.val');
        const hidden = wrapper.querySelector('input[type="hidden"]');
        
        if (triggerVal) triggerVal.textContent = time;
        if (hidden) {
            hidden.value = time;
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }
        
        document.querySelectorAll('.cbp-time-chip').forEach(c => {
            c.classList.toggle('active', time === c.dataset.time);
        });

        // Sync with dropdown
        document.querySelectorAll('#cbp-time-option-list .cbp-option').forEach(opt => {
            const isSelected = opt.dataset.value === time;
            opt.classList.toggle('selected', isSelected);
            const check = opt.querySelector('.cbp-check-icon');
            if (check) check.style.display = isSelected ? 'block' : 'none';
        });
        
        if (hidden) validateField(hidden);
    };

    const formatTime = (time24) => {
        if (!time24) return '';
        const [h, m] = time24.split(':');
        const hh = parseInt(h);
        return `${hh % 12 || 12}:${m} ${hh >= 12 ? 'PM' : 'AM'}`;
    };

    // --- VALIDATION ---
    const setupFormValidation = () => {
        config.form.querySelectorAll('input, select, textarea').forEach(f => {
             f.addEventListener('blur', () => validateField(f));
             f.addEventListener('change', () => validateField(f));
        });
    };

    const validateField = (field) => {
        if (!field) return true;
        
        // For hidden inputs in custom selects, find the wrapper
        let parent;
        if (field.type === 'hidden' && field.closest('.cbp-custom-select')) {
            parent = field.closest('.cbp-custom-select');
        } else {
            parent = field.closest('.cbp-field');
        }
        
        if (!parent) return true;
        
        let error = '';
        let value = field.value ? field.value.trim() : '';
        
        if (field.required && !value) {
            error = 'This field is required';
        } else if (field.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            error = 'Please enter a valid email address';
        } else if (field.id === 'cbp-fld-message' && value && value.length < 10) {
            error = 'Please enter at least 10 characters';
        }
        
        // Remove existing error
        const existing = parent.querySelector('.cbp-error-text');
        if (existing) existing.remove();
        
        if (error) {
            field.classList.add('cbp-field-error');
            const trigger = parent.querySelector('.cbp-select-trigger');
            if (trigger) trigger.classList.add('cbp-field-error');
            
            const errEl = document.createElement('span');
            errEl.className = 'cbp-error-text';
            errEl.textContent = error;
            parent.appendChild(errEl);
            
            return false;
        } else {
            field.classList.remove('cbp-field-error');
            const trigger = parent.querySelector('.cbp-select-trigger');
            if (trigger) trigger.classList.remove('cbp-field-error');
            
            return true;
        }
    };

    // --- CHARACTER COUNTER ---
    const messageField = document.getElementById('cbp-fld-message');
    if (messageField) {
        messageField.addEventListener('input', () => {
            const len = messageField.value.length;
            const counter = messageField.parentElement.querySelector('.cbp-counter');
            if (counter) {
                counter.textContent = `${len}/500 characters`;
                if (len > 450) counter.style.color = '#ec1e28';
                else counter.style.color = '#94a3b8';
            }
        });
    }

    // --- FORM SUBMISSION ---
    config.form.onsubmit = async (e) => {
        e.preventDefault();
        if (state.isSubmitting) return;

        // Hide previous messages
        config.errorBox.style.display = 'none';
        config.successBox.style.display = 'none';

        let isValid = true;
        let firstError = null;
        
        // Validate all required fields
        config.form.querySelectorAll('[required]').forEach(f => {
            if (!validateField(f)) {
                isValid = false;
                if (!firstError) firstError = f;
            }
        });
        
        // Also validate hidden inputs in custom selects
        config.form.querySelectorAll('.cbp-custom-select input[type="hidden"][required]').forEach(f => {
            if (!validateField(f)) {
                isValid = false;
                if (!firstError) firstError = f;
            }
        });

        // Check date and time
        if (!state.selectedDate) {
            isValid = false;
            config.errorBox.innerHTML = '⚠️ Please select a date for your consultation.';
            config.errorBox.style.display = 'block';
        }
        if (!state.selectedTime) {
            isValid = false;
            config.errorBox.innerHTML = '⚠️ Please select an available time slot.';
            config.errorBox.style.display = 'block';
        }

        if (!isValid) {
            if (firstError && firstError.scrollIntoView) {
                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            if (!config.errorBox.style.display || config.errorBox.style.display === 'none') {
                config.errorBox.innerHTML = '⚠️ Please fill in all required fields highlighted below.';
                config.errorBox.style.display = 'block';
            }
            return;
        }

        // Gather form data
        const formData = new FormData(config.form);
        const data = {};
        formData.forEach((value, key) => {
            data[key] = value;
        });
        data.booking_date = state.selectedDate;
        data.booking_time = state.selectedTime;

        // Start submission
        state.isSubmitting = true;
        config.submitBtn.disabled = true;
        config.submitBtn.innerHTML = '<span class="cbp-spinner"></span> PROCESSING...';

        try {
            const res = await fetch(`${config.apiUrl}/book`, {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/json', 
                    'X-WP-Nonce': config.nonce 
                },
                body: JSON.stringify(data)
            });
            
            const result = await res.json();

            if (!res.ok || (result.code && !result.success)) {
                throw new Error(result.message || result.error || 'Booking failed. Please try again.');
            }

            if (result.success) {
                config.form.style.display = 'none';
                config.errorBox.style.display = 'none';
                config.successBox.style.display = 'block';

                const resDate = document.getElementById('res-date');
                const resTime = document.getElementById('res-time');
                if (resDate) {
                    resDate.textContent = result.booking_date_formatted || formatDisplayDate(state.selectedDate);
                }
                if (resTime) {
                    resTime.textContent = result.booking_time_formatted || state.selectedTime;
                }

                updateSuccessNotifications(result);

                config.successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
                state.isSubmitting = false;
                console.log('Booking successful:', result);
            } else {
                throw new Error(result.error || result.message || 'Booking failed. Please try again.');
            }
        } catch (err) {
            console.error('Booking error:', err);
            config.errorBox.innerHTML = `
                <div style="display:flex; align-items:center; gap:8px;">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/>
                        <line x1="12" y1="8" x2="12" y2="12"/>
                        <line x1="12" y1="16" x2="12.01" y2="16"/>
                    </svg>
                    <span>${err.message}</span>
                </div>
            `;
            config.errorBox.style.display = 'block';
            config.errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            
            config.submitBtn.disabled = false;
            config.submitBtn.innerHTML = 'Submit Booking';
            state.isSubmitting = false;
        }
    };

    // Start the app
    init();
});