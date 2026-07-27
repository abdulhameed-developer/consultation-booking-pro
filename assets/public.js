document.addEventListener('DOMContentLoaded', function() {
    const app = document.getElementById('cbp-booking-app');
    if (!app) return;

    const apiUrl = app.dataset.apiUrl;
    const nonce = app.dataset.nonce;
    const form = document.getElementById('cbp-booking-form');
    const dynamicFields = document.getElementById('cbp-dynamic-fields');
    const quickDates = document.getElementById('cbp-quick-dates');
    const quickTimes = document.getElementById('cbp-quick-times');
    const datePicker = document.getElementById('cbp-date-picker');
    const timeDropdown = document.getElementById('cbp-time-dropdown');
    const submitBtn = document.getElementById('cbp-submit-btn');
    const errorBox = document.getElementById('cbp-error-message');

    let schema = [];
    let selectedDate = '';
    let selectedTime = '';
    let countries = [];

    // --- INITIALIZATION ---
    async function init() {
        try {
            const res = await fetch(apiUrl + '/form-schema', { headers: { 'X-WP-Nonce': nonce }});
            schema = await res.json();
            
            await loadCountries();
            renderForm();
            renderQuickDates();
        } catch (err) {
            console.error('CBP Init Error:', err);
        }
    }

    async function loadCountries() {
        countries = [
            { name: 'Pakistan', code: 'PK', dial: '+92', flag: '🇵🇰' },
            { name: 'United Kingdom', code: 'GB', dial: '+44', flag: '🇬🇧' },
            { name: 'United States', code: 'US', dial: '+1', flag: '🇺🇸' },
            { name: 'Canada', code: 'CA', dial: '+1', flag: '🇨🇦' },
            { name: 'Australia', code: 'AU', dial: '+61', flag: '🇦🇺' },
            { name: 'United Arab Emirates', code: 'AE', dial: '+971', flag: '🇦🇪' },
            { name: 'Saudi Arabia', code: 'SA', dial: '+966', flag: '🇸🇦' },
            { name: 'India', code: 'IN', dial: '+91', flag: '🇮🇳' },
            { name: 'Malaysia', code: 'MY', dial: '+60', flag: '🇲🇾' },
            // ... would ideally have more
        ];
    }

    // --- FORM RENDERING ---
    function renderForm() {
        dynamicFields.innerHTML = '';
        schema.forEach(field => {
            const wrapper = document.createElement('div');
            const colWidth = field.width || '100';
            wrapper.className = `cbp-field-wrapper ${colWidth === '100' ? 'full-width' : (colWidth === '50' ? 'half-width' : 'third-width')}`;
            
            const label = document.createElement('label');
            label.innerHTML = `${field.label}${field.required ? ' <span class="req">*</span>' : ''}`;
            wrapper.appendChild(label);

            let input;
            if (field.type === 'textarea') {
                input = document.createElement('textarea');
                input.rows = 4;
            } else if (field.type === 'country') {
                input = document.createElement('select');
                input.add(new Option(field.placeholder || 'Select Country', ''));
                countries.forEach(c => input.add(new Option(c.name, c.name)));
            } else if (field.type === 'tel') {
                input = renderWhatsAppInput(wrapper);
            } else if (field.type === 'select') {
                input = document.createElement('select');
                input.add(new Option(field.placeholder || 'Select Option', ''));
                const opts = (field.options || '').split(',').map(o => o.trim());
                opts.forEach(o => input.add(new Option(o, o)));
            } else {
                input = document.createElement('input');
                input.type = field.type || 'text';
            }

            input.name = field.key;
            if (field.required) input.required = true;
            input.placeholder = field.placeholder || '';
            input.id = `fld-${field.key}`;
            
            if (field.type !== 'tel') wrapper.appendChild(input);
            dynamicFields.appendChild(wrapper);
        });
    }

    function renderWhatsAppInput(wrapper) {
        const container = document.createElement('div');
        container.className = 'cbp-whatsapp-input';
        
        const dialTrigger = document.createElement('div');
        dialTrigger.className = 'cbp-dial-selector';
        const defaultC = countries[0];
        dialTrigger.innerHTML = `<span class="flag">${defaultC.flag}</span> <span class="dial">${defaultC.dial}</span>`;
        
        const dropdown = document.createElement('div');
        dropdown.className = 'cbp-country-dropdown';
        
        const searchDiv = document.createElement('div');
        searchDiv.className = 'cbp-dropdown-search';
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Search country or code...';
        searchDiv.appendChild(searchInput);
        dropdown.appendChild(searchDiv);
        
        const list = document.createElement('div');
        list.className = 'cbp-country-list';
        countries.forEach(c => {
            const item = document.createElement('div');
            item.className = 'cbp-country-item';
            item.innerHTML = `<span class="flag">${c.flag}</span> <span class="name">${c.name}</span> <span class="code">${c.dial}</span>`;
            item.onclick = (e) => {
                e.stopPropagation();
                dialTrigger.innerHTML = `<span class="flag">${c.flag}</span> <span class="dial">${c.dial}</span>`;
                hiddenDial.value = c.dial;
                dropdown.classList.remove('active');
            };
            list.appendChild(item);
        });
        dropdown.appendChild(list);
        
        // Search Logic Fix (Bug 7.4)
        searchInput.oninput = (e) => {
            const val = e.target.value.toLowerCase();
            list.querySelectorAll('.cbp-country-item').forEach(li => {
                const text = li.textContent.toLowerCase();
                li.style.display = text.includes(val) ? 'flex' : 'none';
            });
        };
        searchInput.onclick = (e) => e.stopPropagation();

        dialTrigger.onclick = (e) => {
            e.stopPropagation();
            const isActive = dropdown.classList.contains('active');
            document.querySelectorAll('.cbp-country-dropdown').forEach(d => d.classList.remove('active'));
            if (!isActive) dropdown.classList.add('active');
        };

        const hiddenDial = document.createElement('input');
        hiddenDial.type = 'hidden';
        hiddenDial.name = 'whatsapp_dial_code';
        hiddenDial.value = defaultC.dial;

        const mainInput = document.createElement('input');
        mainInput.type = 'tel';
        mainInput.placeholder = 'Phone number';
        mainInput.style.border = 'none';
        mainInput.style.flex = '1';

        container.appendChild(dialTrigger);
        container.appendChild(dropdown);
        container.appendChild(mainInput);
        container.appendChild(hiddenDial);
        wrapper.appendChild(container);
        return mainInput;
    }

    // --- DATE & TIME ---
    function toLocalISODate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${day}`;
    }

    function renderQuickDates() {
        quickDates.innerHTML = '';
        const now = new Date();
        for (let i = 1; i <= 7; i++) {
            const d = new Date(now.getFullYear(), now.getMonth(), now.getDate() + i);
            const dateStr = toLocalISODate(d);
            
            const card = document.createElement('div');
            card.className = 'cbp-date-card';
            card.innerHTML = `<span class="day">${d.toLocaleDateString('en-US', {weekday:'short'})}</span><span class="date">${d.getDate()} ${d.toLocaleDateString('en-US', {month:'short'})}</span>`;
            card.onclick = () => {
                selectedDate = dateStr;
                datePicker.value = dateStr;
                document.querySelectorAll('.cbp-date-card').forEach(c => c.classList.remove('active'));
                card.classList.add('active');
                loadTimes(dateStr);
            };
            quickDates.appendChild(card);
        }
    }

    async function loadTimes(date) {
        quickTimes.innerHTML = '<span class="cbp-spinner" style="border-top-color:var(--cbp-primary)"></span>';
        try {
            const res = await fetch(`${apiUrl}/available-slots?date=${date}`, { headers: { 'X-WP-Nonce': nonce }});
            const data = await res.json();
            renderTimes(data.slots || []);
        } catch(e) { quickTimes.innerHTML = 'Error loading times'; }
    }

    function renderTimes(slots) {
        quickTimes.innerHTML = '';
        timeDropdown.innerHTML = '<option value="">All Available Times</option>';
        
        if (!slots.length) {
            quickTimes.innerHTML = '<p style="grid-column:span 2; font-size:12px; color:#64748b;">No slots found for this date.</p>';
            return;
        }

        slots.forEach((slot, idx) => {
            // API already returns display format (e.g. "9:00 AM")
            const displayTime = slot;

            if (idx < 4) {
                const btn = document.createElement('div');
                btn.className = 'cbp-time-btn';
                btn.textContent = displayTime;
                btn.onclick = () => selectTime(slot, displayTime);
                quickTimes.appendChild(btn);
            }
            timeDropdown.add(new Option(displayTime, slot));
        });
    }

    function selectTime(val, label) {
        selectedTime = val;
        timeDropdown.value = val;
        document.querySelectorAll('.cbp-time-btn').forEach(b => {
            b.classList.toggle('active', b.textContent === label);
        });
    }

    datePicker.addEventListener('change', (e) => {
        selectedDate = e.target.value;
        loadTimes(selectedDate);
    });

    timeDropdown.addEventListener('change', (e) => selectTime(e.target.value, e.target.options[e.target.selectedIndex].text));

    // --- SUBMISSION ---
    form.onsubmit = async (e) => {
        e.preventDefault();
        
        // Validation Fix (Bug 7.5)
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        data.booking_date = selectedDate;
        data.booking_time = selectedTime;

        let missing = [];
        schema.forEach(f => {
            if (f.required && (!data[f.key] || data[f.key].trim() === '')) {
                missing.push(f.label);
            }
        });

        if (!selectedDate) missing.push('Booking Date');
        if (!selectedTime) missing.push('Booking Time');

        if (missing.length) {
            errorBox.textContent = 'Please fill in: ' + missing.join(', ');
            errorBox.style.display = 'block';
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="cbp-spinner"></span> Finalizing...';
        errorBox.style.display = 'none';

        try {
            const res = await fetch(apiUrl + '/book', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
                body: JSON.stringify(data)
            });
            const result = await res.json();
            
            if (result.success) {
                form.style.display = 'none';
                document.querySelector('.cbp-form-header').style.display = 'none';
                document.getElementById('cbp-success-message').style.display = 'block';
                document.getElementById('res-date').textContent = selectedDate;
                document.getElementById('res-time').textContent = selectedTime;
            } else {
                throw new Error(result.message || 'Booking failed');
            }
        } catch (err) {
            errorBox.textContent = err.message;
            errorBox.style.display = 'block';
            submitBtn.disabled = false;
            submitBtn.innerHTML = 'Submit Booking';
        }
    };

    init();
});
