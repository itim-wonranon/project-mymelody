// js/script.js

// 1. Toggle Band Fields based on Solo/Duo/Band selection
function toggleBandFields() {
    const typeSolo = document.getElementById('type_solo');
    if (!typeSolo) return; // Not on the musician form page

    const isSolo = typeSolo.checked;
    
    document.getElementById('solo_fields').style.display = isSolo ? 'block' : 'none';
    document.getElementById('band_fields').style.display = (!isSolo) ? 'block' : 'none';
}

// 2. Add Band Member Field
function addMemberField() {
    const container = document.getElementById('members_container');
    const newRow = document.createElement('div');
    newRow.className = 'row mb-3 member-row';
    newRow.innerHTML = `
        <div class="col-md-4"><input type="text" class="form-control bg-dark border-secondary text-white" name="member_name[]" placeholder="ชื่อ-นามสกุล"></div>
        <div class="col-md-3"><input type="number" class="form-control bg-dark border-secondary text-white" name="member_age[]" placeholder="อายุ" min="1"></div>
        <div class="col-md-4"><input type="text" class="form-control bg-dark border-secondary text-white" name="member_instruments[]" placeholder="เครื่องดนตรี"></div>
        <div class="col-md-1"><button type="button" class="btn btn-outline-danger w-100" onclick="this.parentElement.parentElement.remove()"><i class="fas fa-trash"></i></button></div>
    `;
    container.appendChild(newRow);
}

// 3. Thai Address Cascading Dropdown Logic
let thaiData = {
    provinces: [],
    amphures: [],
    tambons: []
};

async function initThaiAddress() {
    const provSelect = document.getElementById('prov_select');
    if (!provSelect) return; // Not on the form page

    try {
        // Fetch data from local files
        const [provRes, amphRes, tamRes] = await Promise.all([
            fetch('js/data/provinces.json'),
            fetch('js/data/amphures.json'),
            fetch('js/data/tambons.json')
        ]);

        thaiData.provinces = await provRes.json();
        thaiData.amphures = await amphRes.json();
        thaiData.tambons = await tamRes.json();

        // Populate Provinces
        provSelect.innerHTML = '<option value="">-- เลือกจังหวัด --</option>';
        thaiData.provinces.forEach(p => {
            if (p.name_th !== 'กรุงเทพมหานคร') { // Exclude BKK as it's handled separately
                const option = document.createElement('option');
                option.value = p.id;
                option.textContent = p.name_th;
                provSelect.appendChild(option);
            }
        });

    } catch (error) {
        console.error("Error loading Thai address data:", error);
        provSelect.innerHTML = '<option value="">เกิดข้อผิดพลาดในการโหลดข้อมูล</option>';
    }
}

function onProvinceChange() {
    const provId = document.getElementById('prov_select').value;
    const amphSelect = document.getElementById('amph_select');
    const tamSelect = document.getElementById('tam_select');
    
    amphSelect.innerHTML = '<option value="">-- เลือกอำเภอ --</option>';
    tamSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
    amphSelect.disabled = true;
    tamSelect.disabled = true;

    if (!provId) return;

    const amphures = thaiData.amphures.filter(a => a.province_id == provId);
    amphures.forEach(a => {
        const option = document.createElement('option');
        option.value = a.id;
        option.textContent = a.name_th;
        amphSelect.appendChild(option);
    });
    
    amphSelect.disabled = false;
}

function onAmphureChange() {
    const amphId = document.getElementById('amph_select').value;
    const tamSelect = document.getElementById('tam_select');
    
    tamSelect.innerHTML = '<option value="">-- เลือกตำบล --</option>';
    tamSelect.disabled = true;

    if (!amphId) return;

    const tambons = thaiData.tambons.filter(t => t.amphure_id == amphId);
    tambons.forEach(t => {
        const option = document.createElement('option');
        option.value = t.id;
        option.textContent = t.name_th;
        tamSelect.appendChild(option);
    });
    
    tamSelect.disabled = false;
}

function addProvincialArea() {
    const provSelect = document.getElementById('prov_select');
    const amphSelect = document.getElementById('amph_select');
    const tamSelect = document.getElementById('tam_select');

    if (!provSelect.value) {
        alert("กรุณาเลือกจังหวัดอย่างน้อย 1 แหล่ง");
        return;
    }

    const provName = provSelect.options[provSelect.selectedIndex].text;
    const amphName = amphSelect.value ? amphSelect.options[amphSelect.selectedIndex].text : '';
    const tamName = tamSelect.value ? tamSelect.options[tamSelect.selectedIndex].text : '';

    let fullAddress = provName;
    if (amphName) fullAddress += ' > ' + amphName;
    if (tamName) fullAddress += ' > ' + tamName;

    // Check for duplicates
    const existing = document.querySelectorAll('input[name="work_areas[]"]');
    for(let inp of existing) {
        if(inp.value === fullAddress) {
            alert("คุณได้เพิ่มพื้นที่นี้ไปแล้ว");
            return;
        }
    }

    const container = document.getElementById('provincial_areas_container');
    const badge = document.createElement('div');
    badge.className = 'badge bg-secondary p-2 me-2 mb-2 fs-6 d-inline-flex align-items-center';
    badge.innerHTML = `
        <span>${fullAddress}</span>
        <input type="hidden" name="work_areas[]" value="${fullAddress}">
        <button type="button" class="btn-close btn-close-white ms-2" style="font-size: 0.6rem;" onclick="this.parentElement.remove()"></button>
    `;
    container.appendChild(badge);

    // Reset selects
    provSelect.value = '';
    onProvinceChange(); // This will disable amph and tam
}

function toggleTimeInputs(dayCheckbox) {
    const timeContainer = document.getElementById('time_inputs_' + dayCheckbox.value);
    if (!timeContainer) return;

    if (dayCheckbox.checked) {
        timeContainer.style.display = 'flex';
        const startInput = timeContainer.querySelector(`input[name="time_start[${dayCheckbox.value}]"]`);
        const endInput = timeContainer.querySelector(`input[name="time_end[${dayCheckbox.value}]"]`);
        if(startInput) startInput.required = true;
        if(endInput) endInput.required = true;
    } else {
        timeContainer.style.display = 'none';
        const startInput = timeContainer.querySelector(`input[name="time_start[${dayCheckbox.value}]"]`);
        const endInput = timeContainer.querySelector(`input[name="time_end[${dayCheckbox.value}]"]`);
        if(startInput) {
            startInput.required = false;
            startInput.value = '';
        }
        if(endInput) {
            endInput.required = false;
            endInput.value = '';
        }
    }
}

// Initialize on DOM Load
document.addEventListener('DOMContentLoaded', function() {
    initThaiAddress();
    
    // Check initial state for time inputs (for edit page)
    const dayCheckboxes = document.querySelectorAll('.day-checkbox');
    dayCheckboxes.forEach(cb => {
        toggleTimeInputs(cb);
    });

    // Password Visibility Toggle
    const togglePassword = document.getElementById('togglePassword');
    const password = document.getElementById('password');
    const togglePasswordIcon = document.getElementById('togglePasswordIcon');

    if (togglePassword && password && togglePasswordIcon) {
        togglePassword.addEventListener('click', function () {
            const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
            password.setAttribute('type', type);
            
            if (type === 'password') {
                togglePasswordIcon.classList.remove('fa-eye-slash');
                togglePasswordIcon.classList.add('fa-eye');
            } else {
                togglePasswordIcon.classList.remove('fa-eye');
                togglePasswordIcon.classList.add('fa-eye-slash');
            }
        });
    }
});
