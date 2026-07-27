/**
 * Consultation Booking Pro - Admin Logic
 * Modern, Production-Ready Implementation - FULLY FIXED
 */

jQuery(document).ready(function($) {
    // --- STATE MANAGEMENT ---
    const state = {
        activeTab: 'form-editor',
        formConfig: [],
        activeFieldId: null,
        files: [],
        activeFilePath: null,
        editors: {},
        isDirty: false
    };

    // --- INITIALIZATION ---
    const init = () => {
        setupTabs();
        loadFormConfig();
        loadFiles();
        setupModals();
        setupCounselors();
        setupSettings();
    };

    // --- TABS LOGIC ---
    const setupTabs = () => {
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            const tab = $(this).data('tab');
            if (state.isDirty && !confirm('You have unsaved changes. Continue?')) return;

            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            $('.cbp-tab-content').hide();
            $(`#tab-${tab}`).show();
            state.activeTab = tab;

            if (tab === 'code-files') initCodeEditor();
            if (tab === 'email-templates') initEmailEditors();
        });

        const hash = window.location.hash.replace('#', '');
        if (hash) $(`.nav-tab[data-tab="${hash}"]`).click();
    };

    // --- COUNSELORS MANAGEMENT ---
    const setupCounselors = () => {
        const modal = $('#cbp-counselor-modal');
        const form = $('#cbp-counselor-form');
        let currentStep = 1;
        let customSlotsState = {
            monday: [], tuesday: [], wednesday: [], thursday: [], friday: [], saturday: [], sunday: []
        };

        const updateStepUI = (step) => {
            currentStep = step;
            $('.step-indicator .step').removeClass('active');
            $(`.step-indicator .step[data-step="${step}"]`).addClass('active');
            $('.modal-section').hide();
            $(`#section-step-${step}`).show();

            $('#cbp-prev-step').toggle(step > 1);
            $('#cbp-next-step').toggle(step < 3);
            $('#cbp-save-counselor').toggle(step === 3);

            $('.modal-section').parent().scrollTop(0);
            updateSaveButtonPulse();
        };

        const validateField = (el) => {
            const val = $(el).val();
            let isValid = true;
            const type = $(el).attr('type');

            if ($(el).prop('required') && !val) {
                isValid = false;
            } else if (type === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
                isValid = false;
            } else if ($(el).attr('id') === 'c-calendar-id' && val) {
                if (val !== 'primary' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val) && !val.endsWith('@group.calendar.google.com')) {
                    isValid = false;
                }
            }

            if (!isValid) {
                $(el).addClass('cbp-invalid').css('border-color', '#ec1e28');
                $(el).next('.cbp-error-msg').remove();
                $(el).after('<span class="cbp-error-msg" style="color:#ec1e28; font-size:10px; display:block; margin-top:4px;">Invalid or missing field</span>');
            } else {
                $(el).removeClass('cbp-invalid').css('border-color', '#e2e8f0');
                $(el).next('.cbp-error-msg').remove();
            }
            updateStepStatus();
            updateSaveButtonPulse();
            return isValid;
        };

        const updateStepStatus = () => {
            for (let i = 1; i <= 3; i++) {
                let stepValid = true;
                $(`#section-step-${i} [required], #section-step-${i} input[type="email"]`).each(function() {
                    const val = $(this).val();
                    if ($(this).prop('required') && !val) stepValid = false;
                    if ($(this).attr('type') === 'email' && val && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) stepValid = false;
                });
                
                const indicator = $(`.step-indicator .step[data-step="${i}"]`);
                indicator.find('.step-status-icon').remove();
                if (stepValid) {
                    indicator.append('<span class="step-status-icon dashicons dashicons-yes" style="color:#059669; font-size:16px;"></span>');
                } else {
                    indicator.append('<span class="step-status-icon dashicons dashicons-warning" style="color:#ec1e28; font-size:16px;"></span>');
                }
            }
        };

        const updateSaveButtonPulse = () => {
            let allValid = true;
            $('#cbp-counselor-form [required]').each(function() {
                if (!$(this).val()) allValid = false;
            });
            $('#cbp-save-counselor').toggleClass('cbp-pulse', allValid);
        };

        const buildGoogleAuthUrl = (counselorId) => {
            if (!cbpData.googleClientId || !counselorId) return '#';
            const scopes = [
                'https://www.googleapis.com/auth/calendar',
                'https://www.googleapis.com/auth/calendar.events'
            ].join(' ');
            const params = new URLSearchParams({
                client_id: cbpData.googleClientId,
                redirect_uri: cbpData.googleRedirectUri,
                scope: scopes,
                response_type: 'code',
                access_type: 'offline',
                prompt: 'consent',
                state: String(counselorId)
            });
            return 'https://accounts.google.com/o/oauth2/v2/auth?' + params.toString();
        };

        const updateGoogleOAuthUI = (counselorData = {}) => {
            const $connected = $('#cbp-google-status-connected');
            const $disconnected = $('#cbp-google-status-disconnected');
            const $missing = $('#cbp-google-status-missing-config');
            const counselorId = counselorData.id || $('#c-id').val();

            $connected.hide();
            $disconnected.hide();
            $missing.hide();

            if (!cbpData.googleClientId) {
                $missing.show();
                return;
            }

            if (counselorData.google_refresh_token) {
                $connected.css('display', 'flex');
                $('#cbp-disconnect-google').attr(
                    'href',
                    cbpData.adminUrl + '?page=cbp-counselors&action=disconnect&id=' + counselorId + '&_wpnonce=' + cbpData.disconnectNonce
                );
            } else {
                $disconnected.show();
                const hint = $('#cbp-google-connect-hint');
                if (counselorId) {
                    $('#cbp-connect-google').attr('href', buildGoogleAuthUrl(counselorId));
                    hint.text('Click to authorize Google Calendar access for this counselor.');
                } else {
                    $('#cbp-connect-google').attr('href', '#');
                    hint.text('Save the counselor first, then connect Google Calendar.');
                }
            }
        };

        const formatTimeAMPM = (time) => {
            if (!time) return '';
            const [h, m] = time.split(':');
            let hours = parseInt(h);
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = (hours === 0) ? 12 : hours;
            return hours + ':' + m + ' ' + ampm;
        };

        const renderCustomSlots = () => {
    const container = $('#cbp-mode-custom');
    Object.keys(customSlotsState).forEach(day => {
        const dayItem = container.find(`.cbp-acc-item[data-day="${day}"]`);
        const slotsWrap = dayItem.find('.cbp-custom-slots-container');
        const countTag = dayItem.find('.cbp-slot-count');
        
        slotsWrap.empty();
        const daySlots = (customSlotsState[day] || []).sort();
        
        daySlots.forEach((time, index) => {
            const chip = $(`
                <div class="cbp-custom-slot-chip">
                    <span>${formatTimeAMPM(time)}</span>
                    <span class="cbp-remove-slot" data-day="${day}" data-idx="${index}">&times;</span>
                </div>
            `);
            slotsWrap.append(chip);
        });
        
        countTag.text(`${daySlots.length} slot${daySlots.length !== 1 ? 's' : ''}`);
    });
    
    // CRITICAL: Update hidden field
    const jsonStr = JSON.stringify(customSlotsState);
    $('#cbp-custom-slots-json').val(jsonStr);
    console.log('Custom slots JSON updated:', jsonStr);
};

        // --- Event Listeners ---
        $(document).on('blur', '#cbp-counselor-form input, #cbp-counselor-form select', function() {
            validateField(this);
        });

        $('#cbp-add-counselor-btn').on('click', () => {
            $('#modal-title').text('Add New Counselor');
            form[0].reset();
            $('#c-id').val('');
            customSlotsState = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[],saturday:[],sunday:[]};
            renderCustomSlots();
            updateGoogleOAuthUI({});
            updateStepUI(1);
            modal.css('display', 'flex').hide().fadeIn(200);
        });

        $(document).on('click', '.cbp-counselor-edit', function() {
            const row = $(this).closest('tr');
            let data = {};
            try {
                data = JSON.parse(row.attr('data-counselor'));
            } catch(e) {
                console.error('Failed to parse counselor data');
                return;
            }
            
            $('#modal-title').text('Edit Counselor: ' + data.name);
            $('#c-id').val(data.id);
            $('#c-name').val(data.name);
            $('#c-email').val(data.email);
            $('#c-calendar-id').val(data.google_calendar_id);
            $('#c-timezone').val(data.timezone);
            
            // FIXED: Use correct selectors for Step 3 fields
            $('input[name="slot_duration"]').val(data.slot_duration || 30);
            $('input[name="buffer_time"]').val(data.buffer_time || 0);
            $('input[name="max_bookings_per_day"]').val(data.max_bookings_per_day || 15);
            
            // Mode Toggle
            // If custom mode but no custom slots exist, force auto mode
let mode = data.availability_mode || 'auto';
if (mode === 'custom') {
    let customSlots = {};
    try {
        customSlots = data.custom_slots 
            ? (typeof data.custom_slots === 'string' ? JSON.parse(data.custom_slots) : data.custom_slots)
            : {};
    } catch(e) { customSlots = {}; }
    
    // Check if ANY day has slots
    let hasAnySlots = false;
    if (customSlots && typeof customSlots === 'object') {
        Object.values(customSlots).forEach(slots => {
            if (Array.isArray(slots) && slots.length > 0) hasAnySlots = true;
        });
    }
    
    if (!hasAnySlots) {
        mode = 'auto';
        console.log('No custom slots found, switching to auto mode');
    }
}
console.log('Setting mode to:', mode);
            $(`input[name="availability_mode"][value="${mode}"]`).prop('checked', true).trigger('change');
            
            // Schedule - FIXED: Parse JSON properly
            if (data.availability_schedule) {
                try {
                    const sched = (typeof data.availability_schedule === 'string') 
                        ? JSON.parse(data.availability_schedule) 
                        : data.availability_schedule;
                    
                    if (sched && typeof sched === 'object') {
                        Object.keys(sched).forEach(day => {
                            const dayData = sched[day];
                            if (dayData && typeof dayData === 'object') {
                                $(`.cbp-day-row[data-day="${day}"] .cbp-day-toggle`).prop('checked', !!dayData.enabled);
                                $(`.cbp-day-row[data-day="${day}"] input[name*="[start]"]`).val(dayData.start || '09:00');
                                $(`.cbp-day-row[data-day="${day}"] input[name*="[end]"]`).val(dayData.end || '17:00');
                            }
                        });
                    }
                } catch(e) { 
                    console.error('Schedule parse fail', e); 
                }
            }
            
            // Custom Slots
            try {
                customSlotsState = data.custom_slots 
                    ? (typeof data.custom_slots === 'string' ? JSON.parse(data.custom_slots) : data.custom_slots)
                    : {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[],saturday:[],sunday:[]};
                renderCustomSlots();
            } catch(e) {
                customSlotsState = {monday:[],tuesday:[],wednesday:[],thursday:[],friday:[],saturday:[],sunday:[]};
                renderCustomSlots();
            }

            updateStepUI(1);
            updateGoogleOAuthUI(data);
            modal.css('display', 'flex').hide().fadeIn(200);
        });

        $('#cbp-next-step').on('click', () => {
            let stepValid = true;
            $(`#section-step-${currentStep} [required]`).each(function() {
                if (!validateField(this)) stepValid = false;
            });
            if (stepValid) updateStepUI(currentStep + 1);
        });

        $('#cbp-prev-step').on('click', () => {
            updateStepUI(currentStep - 1);
        });

        $(document).on('click', '.step-indicator .step', function() {
            const targetStep = parseInt($(this).data('step'));
            if (targetStep < currentStep) {
                updateStepUI(targetStep);
            } else if (targetStep === currentStep + 1) {
                $('#cbp-next-step').click();
            }
        });

        $(document).on('change', 'input[name="availability_mode"]', function() {
            const mode = $(this).val();
            $('.cbp-mode-btn').removeClass('active').css('color', '#64748b');
            $(this).closest('.cbp-mode-btn').addClass('active').css('color', 'var(--cbp-primary)');
            
            if (mode === 'auto') {
                $('#cbp-mode-auto').show();
                $('#cbp-mode-custom').hide();
            } else {
                $('#cbp-mode-auto').hide();
                $('#cbp-mode-custom').show();
                renderCustomSlots();
            }
        });

        $(document).on('click', '.cbp-acc-header', function() {
            const item = $(this).closest('.cbp-acc-item');
            const isActive = item.hasClass('active');
            $('.cbp-acc-item').removeClass('active').find('.cbp-acc-content').slideUp(200);
            if (!isActive) item.addClass('active').find('.cbp-acc-content').slideDown(200);
        });

        $(document).on('click', '.cbp-add-slot-btn', function() {
            const item = $(this).closest('.cbp-acc-item');
            const day = item.data('day');
            const time = item.find('.cbp-new-slot-input').val();
            if (time && (!customSlotsState[day] || !customSlotsState[day].includes(time))) {
                if (!customSlotsState[day]) customSlotsState[day] = [];
                customSlotsState[day].push(time);
                renderCustomSlots();
            }
        });

        $(document).on('click', '.cbp-remove-slot', function() {
            const day = $(this).data('day');
            const idx = parseInt($(this).data('idx'));
            customSlotsState[day].splice(idx, 1);
            renderCustomSlots();
        });

        $(document).on('click', '.cbp-gen-slots-btn', function() {
            const item = $(this).closest('.cbp-acc-item');
            const day = item.data('day');
            const autoRow = $(`.cbp-day-row[data-day="${day}"]`);
            const enabled = autoRow.find('.cbp-day-toggle').is(':checked');
            if (!enabled) return alert('This day is disabled in Auto hours.');
            
            const start = autoRow.find('input[name*="[start]"]').val();
            const end = autoRow.find('input[name*="[end]"]').val();
            const duration = parseInt($('input[name="slot_duration"]').val()) || 30;
            const buffer = parseInt($('input[name="buffer_time"]').val()) || 0;

            if (!start || !end) return;
            let curr = new Date(`2000-01-01T${start}:00`);
            const fin = new Date(`2000-01-01T${end}:00`);
            const added = [];
            while (curr < fin) {
                added.push(curr.toTimeString().substring(0, 5));
                curr.setMinutes(curr.getMinutes() + duration + buffer);
            }
            customSlotsState[day] = [...new Set([...(customSlotsState[day] || []), ...added])];
            renderCustomSlots();
        });

        $('#cbp-save-counselor').on('click', async function(e) {
    e.preventDefault();
    
    let allValid = true;
    let firstErrorStep = null;
    
    $('.modal-section').show();
    
    $('#cbp-counselor-form [required]').each(function() {
        if (!validateField(this)) {
            allValid = false;
            const stepSection = $(this).closest('.modal-section');
            const stepNum = parseInt(stepSection.attr('id').replace('section-step-', ''));
            if (!firstErrorStep || stepNum < firstErrorStep) {
                firstErrorStep = stepNum;
            }
        }
    });
    
    $('.modal-section').hide();
    $(`#section-step-${currentStep}`).show();
    
    if (!allValid) {
        if (firstErrorStep) updateStepUI(firstErrorStep);
        alert('Please fill in all required fields highlighted in red.');
        return;
    }

    const btn = $(this);
    const orig = btn.html();
    btn.prop('disabled', true).html('<span style="display:inline-block; width:16px; height:16px; border:2px solid rgba(255,255,255,0.3); border-top-color:#fff; border-radius:50%; animation:spin 0.8s linear infinite; vertical-align:middle; margin-right:6px;"></span> Saving...');

    const buildScheduleObject = () => {
        const schedule = {};
        const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        days.forEach(day => {
            const enabled = $(`input[name="schedule[${day}][enabled]"]`).is(':checked');
            const start = $(`input[name="schedule[${day}][start]"]`).val();
            const end = $(`input[name="schedule[${day}][end]"]`).val();
            schedule[day] = {
                enabled: enabled,
                start: start || '09:00',
                end: end || '17:00'
            };
        });
        return schedule;
    };

    // Get the current availability mode
    const currentMode = $('input[name="availability_mode"]:checked').val() || 'auto';
    
    // Get custom slots - ALWAYS update it before reading
    const customSlotsValue = $('#cbp-custom-slots-json').val() || JSON.stringify(customSlotsState);
    
    console.log('=== SAVE DEBUG ===');
    console.log('Mode:', currentMode);
    console.log('Custom Slots Raw:', customSlotsValue);
    console.log('Custom Slots State:', JSON.stringify(customSlotsState));
    console.log('Schedule:', JSON.stringify(buildScheduleObject(), null, 2));

    const payload = {
        action: 'cbp_save_counselor',
        nonce: cbpData.nonce,
        id: $('#c-id').val() || '',
        name: $('#c-name').val(),
        email: $('#c-email').val(),
        google_calendar_id: $('#c-calendar-id').val(),
        timezone: $('#c-timezone').val(),
        slot_duration: parseInt($('input[name="slot_duration"]').val()) || 30,
        buffer_time: parseInt($('input[name="buffer_time"]').val()) || 0,
        max_bookings_per_day: parseInt($('input[name="max_bookings_per_day"]').val()) || 10,
        availability_mode: currentMode,
        schedule: buildScheduleObject(),
        custom_slots_json: customSlotsValue,
        status: 'active'
    };

    const formData = new FormData();
    formData.append('action', payload.action);
    formData.append('nonce', payload.nonce);
    formData.append('id', payload.id);
    formData.append('name', payload.name);
    formData.append('email', payload.email);
    formData.append('google_calendar_id', payload.google_calendar_id);
    formData.append('timezone', payload.timezone);
    formData.append('slot_duration', payload.slot_duration);
    formData.append('buffer_time', payload.buffer_time);
    formData.append('max_bookings_per_day', payload.max_bookings_per_day);
    formData.append('availability_mode', payload.availability_mode);
    formData.append('schedule', JSON.stringify(payload.schedule));
    formData.append('custom_slots_json', payload.custom_slots_json);
    formData.append('status', payload.status);

    console.log('SAVING COUNSELOR - Payload:', payload);

    try {
        const res = await fetch(ajaxurl, { method: 'POST', body: formData });
        const text = await res.text();
        console.log('SAVING COUNSELOR - Response:', text);
        
        let json;
        try {
            json = JSON.parse(text);
        } catch (parseErr) {
            console.error('Invalid JSON response:', text);
            alert('Server returned an invalid response.');
            btn.prop('disabled', false).html(orig);
            return;
        }
        
        if (json.success) {
            alert('✓ Counselor saved successfully!');
            location.reload();
        } else {
            alert('✗ Error: ' + (json.data?.message || json.message || 'Unknown error'));
            btn.prop('disabled', false).html(orig);
        }
    } catch(e) { 
        console.error('Save failed:', e);
        alert('Request failed.');
        btn.prop('disabled', false).html(orig);
    }
});

        $(document).on('click', '#cbp-test-calendar', async function() {
            const calId = $('#c-calendar-id').val();
            if (!calId) return alert('Enter Calendar ID');
            const btn = $(this);
            btn.prop('disabled', true).text('Testing...');
            try {
                const res = await fetch(cbpData.apiUrl + '/test-calendar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cbpData.nonce },
                    body: JSON.stringify({ calendar_id: calId })
                });
                const data = await res.json();
                if (data.success) {
                    if (data.timezone) {
                        $('#c-timezone').val(data.timezone);
                    }
                    alert('Success! Connected to: ' + data.name + (data.timezone ? '\n\nTimezone synced to: ' + data.timezone + '\n(Save counselor to keep this timezone.)' : ''));
                } else {
                    alert('Failed: ' + (data.error || 'Unknown'));
                }
            } catch(e) { alert('Test failed'); }
            finally { btn.prop('disabled', false).text('Test Connection'); }
        });

        // Status Toggle
        $(document).on('change', '.cbp-status-toggle', function() {
            const id = $(this).data('id');
            const status = $(this).is(':checked') ? 'active' : 'inactive';
            fetch(cbpData.apiUrl + '/toggle-counselor', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cbpData.nonce },
                body: JSON.stringify({ id, status })
            }).then(() => location.reload());
        });
    };

    // --- FORM EDITOR ---
    const loadFormConfig = async () => {
        try {
            const res = await fetch(cbpData.apiUrl + '/file-content?path=form-config.json', { headers: { 'X-WP-Nonce': cbpData.nonce }});
            const data = await res.json();
            state.formConfig = JSON.parse(data.content || '[]');
            renderFieldTree();
            updateMiniPreview();
        } catch(e) { console.error('Failed to load form config', e); }
    };

    const renderFieldTree = () => {
        const tree = $('#cbp-field-tree');
        tree.empty();
        const renderItems = (items, depth = 0) => {
            items.forEach(item => {
                const el = $(`
                    <div class="tree-item ${state.activeFieldId === item.id ? 'active' : ''}" data-id="${item.id}" style="padding-left:${depth * 15}px;">
                        <span class="dashicons dashicons-${getIconForType(item.type)}"></span>
                        <span class="label">${item.label || item.id}</span>
                        <span class="type-tag">${item.type}</span>
                    </div>
                `);
                el.on('click', () => selectField(item.id));
                tree.append(el);
                if (item.fields) renderItems(item.fields, depth + 1);
                if (item.rows) renderItems(item.rows, depth + 1);
                if (item.widgets) renderItems(item.widgets, depth + 1);
            });
        };
        renderItems(state.formConfig);
    };

    const getIconForType = (type) => {
        const map = { section: 'editor-insertmore', column: 'columns', text: 'editor-textcolor', select: 'menu-alt', date: 'calendar' };
        return map[type] || 'admin-generic';
    };

    const selectField = (id) => {
        state.activeFieldId = id;
        renderFieldTree();
        renderFieldConfig(id);
    };

    const renderFieldConfig = (id) => {
        const field = findInConfig(id, state.formConfig);
        const panel = $('#cbp-field-config-panel');
        if (!field) return;
        panel.html(`
            <div style="max-width:500px;">
                <h3 style="margin-top:0;">Edit ${field.type.toUpperCase()}: ${field.id}</h3>
                <div class="config-row"><label>Label</label><input type="text" class="widefat" value="${field.label || ''}" oninput="app.updateField('${id}', 'label', this.value)"></div>
                ${field.type === 'text' || field.type === 'email' || field.type === 'whatsapp' ? `<div class="config-row"><label>Placeholder</label><input type="text" class="widefat" value="${field.placeholder || ''}" oninput="app.updateField('${id}', 'placeholder', this.value)"></div>` : ''}
                ${field.type === 'select' ? `<div class="config-row"><label>Options (Comma separated)</label><textarea class="widefat" oninput="app.updateField('${id}', 'options', this.value)">${field.options || ''}</textarea></div>` : ''}
                <div class="config-row"><label><input type="checkbox" ${field.required ? 'checked' : ''} onchange="app.updateField('${id}', 'required', this.checked)"> Required Field</label></div>
                <div style="margin-top:20px; padding-top:20px; border-top:1px solid #eee;"><button class="button" style="color:#ef4444;" onclick="app.deleteField('${id}')">Delete Field</button></div>
            </div>
        `);
    };

    window.app = {
        updateField: (id, key, val) => {
            const field = findInConfig(id, state.formConfig);
            if (field) { field[key] = val; updateMiniPreview(); state.isDirty = true; renderFieldTree(); }
        },
        deleteField: (id) => {
            if (!confirm('Are you sure?')) return;
            state.formConfig = removeFromConfig(id, state.formConfig);
            state.activeFieldId = null;
            renderFieldTree();
            $('#cbp-field-config-panel').html('<div style="text-align:center; padding:100px; color:#94a3b8;">Field deleted</div>');
            updateMiniPreview();
        },
        addRow: () => {
            const id = 'row_' + Math.random().toString(36).substr(2, 6);
            state.formConfig.push({ id, type: 'row', fields: [] });
            state.isDirty = true; renderFieldTree(); updateMiniPreview();
        },
        addField: () => {
            if (!state.activeFieldId) { alert('Please select a row or section first.'); return; }
            const id = 'fld_' + Math.random().toString(36).substr(2, 6);
            const parent = findInConfig(state.activeFieldId, state.formConfig);
            if (parent && (parent.fields || parent.rows || parent.widgets)) {
                const target = parent.fields || parent.rows || parent.widgets;
                target.push({ id, type: 'text', label: 'New Field', placeholder: 'Enter text...' });
            } else {
                state.formConfig.push({ id, type: 'text', label: 'New Field', placeholder: 'Enter text...' });
            }
            state.isDirty = true; renderFieldTree(); updateMiniPreview();
        },
        setPreviewSize: (size) => {
            $('#preview-frame-container').css('max-width', { desktop: '1100px', tablet: '768px', mobile: '400px' }[size]);
        },
        copyShortcode: () => {
            navigator.clipboard.writeText('[consultation_booking]');
            alert('Shortcode copied!');
        },
        saveAllChanges: async () => {
            const btn = $('.button-primary.button-large');
            btn.prop('disabled', true).text('SAVING...');
            try {
                await fetch(cbpData.apiUrl + '/save-file', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cbpData.nonce }, body: JSON.stringify({ path: 'form-config.json', content: JSON.stringify(state.formConfig, null, 4) }) });
                if (state.activeTab === 'code-files' && state.activeFilePath) {
                    await fetch(cbpData.apiUrl + '/save-file', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cbpData.nonce }, body: JSON.stringify({ path: state.activeFilePath, content: state.editors.code.getValue() }) });
                }
                if (state.activeTab === 'email-templates') { await app.saveEmail('student'); await app.saveEmail('counselor'); }
                state.isDirty = false;
                alert('All changes saved successfully!');
            } catch(e) { alert('Failed to save changes.'); }
            finally { btn.prop('disabled', false).text('SAVE ALL CHANGES'); }
        },
        downloadFiles: () => { window.location.href = cbpData.apiUrl + '/download-files?_wpnonce=' + cbpData.nonce; },
        uploadFiles: () => {
            const input = document.createElement('input'); input.type = 'file'; input.multiple = true;
            input.onchange = async (e) => {
                const files = e.target.files;
                for (let file of files) {
                    const reader = new FileReader();
                    reader.onload = async (ev) => {
                        await fetch(cbpData.apiUrl + '/save-file', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cbpData.nonce }, body: JSON.stringify({ path: file.name, content: ev.target.result }) });
                        if (file === files[files.length - 1]) loadFiles();
                    };
                    reader.readAsText(file);
                }
            };
            input.click();
        },
        saveEmail: async (type) => {
            const editor = type === 'student' ? state.editors.emailStudent : state.editors.emailCounselor;
            if (!editor) return;
            await fetch(cbpData.apiUrl + '/save-file', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cbpData.nonce }, body: JSON.stringify({ path: `email-${type}.html`, content: editor.getValue() }) });
        },
        previewEmail: (type) => {
            const editor = type === 'student' ? state.editors.emailStudent : state.editors.emailCounselor;
            const win = window.open('', '_blank');
            win.document.write(editor.getValue()); win.document.close();
        }
    };

    const findInConfig = (id, items) => {
        for (let item of items) {
            if (item.id === id) return item;
            if (item.fields) { let r = findInConfig(id, item.fields); if (r) return r; }
            if (item.rows) { let r = findInConfig(id, item.rows); if (r) return r; }
            if (item.widgets) { let r = findInConfig(id, item.widgets); if (r) return r; }
        }
        return null;
    };

    const removeFromConfig = (id, items) => {
        return items.filter(item => {
            if (item.id === id) return false;
            if (item.fields) item.fields = removeFromConfig(id, item.fields);
            if (item.rows) item.rows = removeFromConfig(id, item.rows);
            if (item.widgets) item.widgets = removeFromConfig(id, item.widgets);
            return true;
        });
    };

    const updateMiniPreview = () => {
        const container = $('#preview-container');
        container.html('<div style="text-align:center; padding:50px;">Rendering Preview...</div>');
        setTimeout(() => {
            container.html(`<div style="background:#fff; padding:20px;"><h3>${state.formConfig[0]?.fields?.[0]?.content || "Booking Pro Form"}</h3><div style="margin-top:20px; padding:15px; border:1px solid #eee; border-radius:8px;">Example Field: [ ${state.formConfig[1]?.rows?.[0]?.fields?.[0]?.label || "Name"} ]</div></div>`);
        }, 300);
    };

    const initCodeEditor = () => {
        if (state.editors.code) return;
        require(['vs/editor/editor.main'], function() {
            state.editors.code = monaco.editor.create(document.getElementById('cbp-monaco-container'), {
                value: '// Select a file to edit', language: 'javascript', theme: 'vs-dark', automaticLayout: true
            });
        });
    };

    const loadFiles = async () => {
        const res = await fetch(cbpData.apiUrl + '/files', { headers: { 'X-WP-Nonce': cbpData.nonce }});
        state.files = await res.json();
        renderFileExplorer();
    };

    const renderFileExplorer = () => {
        const list = $('#file-list'); list.empty();
        state.files.forEach(file => {
            const el = $(`<div class="file-link ${state.activeFilePath === file.path ? 'active' : ''}" data-path="${file.path}"><span class="dashicons dashicons-media-text"></span>${file.name}</div>`);
            el.on('click', () => openFile(file.path));
            list.append(el);
        });
    };

    const openFile = async (path) => {
        state.activeFilePath = path;
        renderFileExplorer();
        const res = await fetch(cbpData.apiUrl + '/file-content?path=' + path, { headers: { 'X-WP-Nonce': cbpData.nonce }});
        const data = await res.json();
        const lang = path.endsWith('.css') ? 'css' : (path.endsWith('.html') ? 'html' : 'javascript');
        if (state.editors.code) {
            monaco.editor.setModelLanguage(state.editors.code.getModel(), lang);
            state.editors.code.setValue(data.content || '');
            $('#cbp-editor-placeholder').hide();
        }
    };

    const initEmailEditors = async () => {
        if (state.editors.emailStudent) return;
        require(['vs/editor/editor.main'], async function() {
            state.editors.emailStudent = monaco.editor.create(document.getElementById('editor-student-email'), { value: 'Loading...', language: 'html', theme: 'vs-light', automaticLayout: true });
            state.editors.emailCounselor = monaco.editor.create(document.getElementById('editor-counselor-email'), { value: 'Loading...', language: 'html', theme: 'vs-light', automaticLayout: true });
            const resS = await fetch(cbpData.apiUrl + '/file-content?path=email-student.html', { headers: { 'X-WP-Nonce': cbpData.nonce }});
            state.editors.emailStudent.setValue((await resS.json()).content);
            const resC = await fetch(cbpData.apiUrl + '/file-content?path=email-counselor.html', { headers: { 'X-WP-Nonce': cbpData.nonce }});
            state.editors.emailCounselor.setValue((await resC.json()).content);
        });
    };

    const setupModals = () => {
        $('.cbp-modal-close').on('click', function() { $('.cbp-modal').fadeOut(200); });
    };

    const setupSettings = () => {};

    init();
});