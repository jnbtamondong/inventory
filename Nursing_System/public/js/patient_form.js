const PSGC_BASE_URL = 'https://psgc.gitlab.io/api';

function calculateAgeFromBirthday(value) {
    if (!value) return '';
    const parts = value.split('-').map(part => Number(part));
    if (parts.length !== 3 || parts.some(part => Number.isNaN(part))) return '';
    const yearFirst = parts[0] > 31;
    const year = yearFirst ? parts[0] : parts[2];
    const month = yearFirst ? parts[1] : parts[0];
    const day = yearFirst ? parts[2] : parts[1];
    const birthday = new Date(year, month - 1, day);
    if (birthday.getFullYear() !== year || birthday.getMonth() !== month - 1 || birthday.getDate() !== day) return '';
    const today = new Date();
    let age = today.getFullYear() - birthday.getFullYear();
    const monthDiff = today.getMonth() - birthday.getMonth();

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
        age--;
    }

    return age >= 0 ? age : '';
}

function setSelectedName(select, input) {
    if (!select || !input) return;
    input.value = select.options[select.selectedIndex]?.textContent || '';
}

function normalizePsgcItems(items) {
    return items
        .map(item => ({
            id: item.code,
            name: item.name
        }))
        .sort((a, b) => a.name.localeCompare(b.name));
}

function fillSelect(select, items, placeholder, selectedValue, selectedName) {
    select.innerHTML = `<option value="">${placeholder}</option>`;

    if (selectedValue && selectedName && !items.some(item => String(item.id) === String(selectedValue))) {
        items.unshift({ id: selectedValue, name: selectedName });
    }

    items.forEach(item => {
        const option = document.createElement('option');
        option.value = item.id;
        option.textContent = item.name;
        option.selected = String(item.id) === String(selectedValue);
        select.appendChild(option);
    });
}

async function fetchPsgc(path) {
    const response = await fetch(`${PSGC_BASE_URL}${path}`);
    if (!response.ok) throw new Error('Unable to load PSGC locations');
    return normalizePsgcItems(await response.json());
}

async function loadProvinces(select, selectedValue, selectedName) {
    fillSelect(select, [], 'Loading provinces...', '', '');
    const provinces = await fetchPsgc('/provinces/');
    fillSelect(select, provinces, 'Select Province', selectedValue, selectedName);
}

async function loadMunicipalities(provinceCode, select, selectedValue, selectedName) {
    if (!provinceCode) {
        fillSelect(select, [], 'Select City/Municipality', '', '');
        return;
    }

    fillSelect(select, [], 'Loading cities/municipalities...', '', '');
    const municipalities = await fetchPsgc(`/provinces/${provinceCode}/cities-municipalities/`);
    fillSelect(select, municipalities, 'Select City/Municipality', selectedValue, selectedName);
}

async function loadBarangays(municipalityCode, select, selectedValue, selectedName) {
    if (!municipalityCode) {
        fillSelect(select, [], 'Select Barangay', '', '');
        return;
    }

    fillSelect(select, [], 'Loading barangays...', '', '');
    const barangays = await fetchPsgc(`/cities-municipalities/${municipalityCode}/barangays/`);
    fillSelect(select, barangays, 'Select Barangay', selectedValue, selectedName);
}

document.addEventListener('DOMContentLoaded', () => {
    const patientForm = document.querySelector('.patient-form');
    const patientCode = document.getElementById('patient-code');
    const triageDate = document.getElementById('triage-date');
    const birthday = document.getElementById('dob');
    const age = document.getElementById('age');
    const province = document.getElementById('province');
    const municipality = document.getElementById('municipality');
    const barangay = document.getElementById('barangay');
    const provinceName = document.getElementById('province-name');
    const municipalityName = document.getElementById('municipality-name');
    const barangayName = document.getElementById('barangay-name');

    if (birthday && age) {
        age.value = calculateAgeFromBirthday(birthday.value);
        birthday.addEventListener('change', () => {
            age.value = calculateAgeFromBirthday(birthday.value);
        });
    }

    if (patientForm?.dataset.action === 'add' && patientCode && triageDate) {
        const refreshPatientCode = async () => {
            if (!triageDate.value) return;

            try {
                const response = await fetch(`../api/get_patient_code.php?date=${encodeURIComponent(triageDate.value)}`);
                if (!response.ok) return;

                const data = await response.json();
                if (data.patient_code) {
                    patientCode.value = data.patient_code;
                }
            } catch (error) {
                // Keep the current code; the server regenerates the final ID on save.
            }
        };

        triageDate.addEventListener('change', refreshPatientCode);
    }

    if (province && municipality && barangay) {
        const selectedProvince = province.dataset.selected;
        const selectedMunicipality = municipality.dataset.selected;
        const selectedBarangay = barangay.dataset.selected;

        loadProvinces(province, selectedProvince, province.dataset.selectedName)
            .then(() => {
                setSelectedName(province, provinceName);
                return loadMunicipalities(province.value || selectedProvince, municipality, selectedMunicipality, municipality.dataset.selectedName);
            })
            .then(() => {
                setSelectedName(municipality, municipalityName);
                return loadBarangays(municipality.value || selectedMunicipality, barangay, selectedBarangay, barangay.dataset.selectedName);
            })
            .then(() => setSelectedName(barangay, barangayName))
            .catch(() => {
                fillSelect(province, [], 'Unable to load provinces', '', '');
            });

        province.addEventListener('change', () => {
            setSelectedName(province, provinceName);
            municipalityName.value = '';
            barangayName.value = '';
            loadMunicipalities(province.value, municipality, '', '');
            fillSelect(barangay, [], 'Select Barangay', '', '');
        });

        municipality.addEventListener('change', () => {
            setSelectedName(municipality, municipalityName);
            barangayName.value = '';
            loadBarangays(municipality.value, barangay, '', '');
        });

        barangay.addEventListener('change', () => {
            setSelectedName(barangay, barangayName);
        });
    }
});
