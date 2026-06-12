<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("location: index.php");
    exit();
}
require_once('classes/OOUTHSalaryAPIClient.php');
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Extra CDN deps not in base header -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.1/css/all.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden">
<?php include 'includes/topbar.php'; ?>

<div class="flex-1 overflow-y-auto p-6">

    <!-- Upload Progress Modal -->
    <div id="loadingModal" class="fixed inset-0 bg-black bg-opacity-50 z-50 hidden flex items-center justify-center">
        <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl p-8 max-w-md w-full mx-4">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 mb-6">
                    <div class="animate-spin rounded-full h-20 w-20 border-b-4 border-primary"></div>
                </div>
                <h3 id="modalTitle" class="text-2xl font-bold text-slate-800 dark:text-white mb-2">Uploading Data</h3>
                <p id="modalMessage" class="text-slate-600 dark:text-slate-400 mb-6">Please wait while we process your data...</p>
                <div class="w-full bg-slate-200 dark:bg-slate-700 rounded-full h-3 mb-4">
                    <div id="modalProgressBar" class="bg-primary h-3 rounded-full transition-all duration-500" style="width:0%"></div>
                </div>
                <div class="flex justify-between text-sm text-slate-600 dark:text-slate-400">
                    <span id="modalProgressText">Preparing upload...</span>
                    <span id="modalProgressPercent" class="font-semibold">0%</span>
                </div>
                <div id="recordCounter" class="mt-4 text-sm text-slate-500 hidden">
                    Processing: <span id="processedCount" class="font-semibold text-primary">0</span> /
                    <span id="totalCount" class="font-semibold">0</span> records
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto space-y-6">

        <!-- Page Header -->
        <div class="bg-gradient-to-r from-primary to-secondary rounded-2xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold mb-1 flex items-center gap-3">
                        <span class="material-icons-round text-3xl">cloud_upload</span>
                        API Data Upload
                    </h1>
                    <p class="text-white/80 text-sm">Fetch and import payroll deduction data from OOUTH Salary API</p>
                </div>
                <div class="bg-white/20 backdrop-blur-sm p-4 rounded-xl hidden md:block">
                    <span class="material-icons-round text-3xl">sync_alt</span>
                </div>
            </div>
        </div>

        <!-- API Status Card -->
        <div id="apiStatusCard" class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-5 hidden">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div id="statusIndicator" class="w-3 h-3 rounded-full"></div>
                    <div>
                        <p class="text-xs text-slate-500 dark:text-slate-400">API Connection</p>
                        <p id="statusText" class="font-semibold text-slate-800 dark:text-white"></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-xs text-slate-500 dark:text-slate-400">Resource</p>
                    <p class="font-semibold text-primary"><?php echo OOUTH_RESOURCE_NAME; ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Controls Panel -->
            <div class="lg:col-span-1">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 sticky top-4">
                    <h2 class="text-lg font-bold text-slate-800 dark:text-white mb-5 flex items-center gap-2">
                        <span class="material-icons-round text-primary">tune</span> Controls
                    </h2>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            <i class="fas fa-cloud mr-1 text-primary"></i> API Period (Source)
                        </label>
                        <select id="apiPeriodSelect"
                            class="w-full px-4 py-3 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none text-sm">
                            <option value="">Loading periods from API...</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Period to fetch data from</p>
                    </div>

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-2">
                            <i class="fas fa-database mr-1 text-secondary"></i> Local Period (Destination)
                        </label>
                        <select id="localPeriodSelect"
                            class="w-full px-4 py-3 border border-slate-300 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:ring-2 focus:ring-secondary/20 focus:border-secondary outline-none text-sm">
                            <option value="">Loading local periods...</option>
                        </select>
                        <p class="text-xs text-slate-500 mt-1">Period to save data to in DB</p>
                    </div>

                    <div class="space-y-3">
                        <button id="fetchDataBtn" disabled
                            class="w-full px-4 py-3 bg-primary hover:bg-primary/90 text-white rounded-xl transition-colors font-medium disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            <span class="material-icons-round text-base">download</span> Fetch Data from API
                        </button>
                        <button id="uploadDataBtn" disabled
                            class="w-full px-4 py-3 bg-secondary hover:bg-secondary/90 text-white rounded-xl transition-colors font-medium disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            <span class="material-icons-round text-base">upload</span> Upload to Database
                        </button>
                        <button id="clearDataBtn" disabled
                            class="w-full px-4 py-3 bg-slate-500 hover:bg-slate-600 text-white rounded-xl transition-colors font-medium disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                            <span class="material-icons-round text-base">clear_all</span> Clear Data
                        </button>
                    </div>

                    <div id="statsCard" class="mt-5 bg-slate-50 dark:bg-slate-800 rounded-xl p-4 hidden">
                        <h3 class="text-sm font-semibold text-slate-700 dark:text-slate-300 mb-3">Data Summary</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Total Records:</span>
                                <span id="totalRecords" class="font-bold text-primary">0</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Total Amount:</span>
                                <span id="totalAmount" class="font-bold text-secondary">₦0.00</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Period:</span>
                                <span id="selectedPeriod" class="font-bold text-slate-800 dark:text-white">-</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Panel -->
            <div class="lg:col-span-2">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <span class="material-icons-round text-primary">table_chart</span> Staff Data
                        </h2>
                        <div class="flex items-center gap-2">
                            <input type="text" id="searchInput" placeholder="Search staff..."
                                class="px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:ring-2 focus:ring-primary/20 outline-none"
                                disabled>
                            <button id="exportBtn" disabled
                                class="px-3 py-2 bg-primary hover:bg-primary/90 text-white rounded-xl text-sm transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex items-center gap-1">
                                <span class="material-icons-round text-sm">file_download</span> Excel
                            </button>
                        </div>
                    </div>

                    <!-- Loading State -->
                    <div id="loadingState" class="text-center py-16 hidden">
                        <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-primary mb-4"></div>
                        <p class="text-slate-600 dark:text-slate-400 font-medium">Fetching data from API...</p>
                    </div>

                    <!-- Empty State -->
                    <div id="emptyState" class="text-center py-16">
                        <span class="material-icons-round text-6xl text-slate-300 dark:text-slate-700 mb-4 block">cloud_download</span>
                        <p class="text-slate-500 dark:text-slate-400 font-medium mb-1">No Data Loaded</p>
                        <p class="text-sm text-slate-400 dark:text-slate-500">Select a period and click "Fetch Data from API"</p>
                    </div>

                    <!-- Data Table -->
                    <div id="dataTable" class="hidden">
                        <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700">
                            <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-700">
                                <thead class="bg-slate-50 dark:bg-slate-800">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Staff ID</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-slate-500 uppercase tracking-wider">Amount (₦)</th>
                                    </tr>
                                </thead>
                                <tbody id="tableBody" class="bg-white dark:bg-slate-900 divide-y divide-slate-100 dark:divide-slate-800"></tbody>
                            </table>
                        </div>
                        <div class="mt-4 flex items-center justify-between text-sm text-slate-600 dark:text-slate-400">
                            <span>Showing <span id="showingFrom">0</span>–<span id="showingTo">0</span> of <span id="showingTotal">0</span></span>
                            <div class="flex gap-2">
                                <button id="prevPageBtn" class="px-3 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded-lg text-sm disabled:opacity-40" disabled>Prev</button>
                                <button id="nextPageBtn" class="px-3 py-1 bg-slate-100 dark:bg-slate-800 hover:bg-slate-200 rounded-lg text-sm disabled:opacity-40" disabled>Next</button>
                            </div>
                        </div>
                    </div>

                    <!-- Upload Results -->
                    <div id="uploadResults" class="mt-5 hidden"></div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<script>
let apiData = [], filteredData = [];
let currentPage = 1, recordsPerPage = 50;
let selectedApiPeriodId = null, selectedApiPeriodInfo = null;
let selectedLocalPeriodId = null, selectedLocalPeriodInfo = null;

document.addEventListener('DOMContentLoaded', () => {
    loadApiPeriods();
    loadLocalPeriods();
    setupEventListeners();
});

async function loadApiPeriods() {
    try {
        showApiStatus('connecting', 'Connecting to API...');
        const result = await fetch('api/fetch_api_data.php?action=get_periods').then(r => r.json());
        const sel = document.getElementById('apiPeriodSelect');
        sel.innerHTML = '<option value="">Select API period...</option>';
        if (result.success && result.data) {
            showApiStatus('connected', 'Connected');
            result.data.forEach(p => {
                const o = document.createElement('option');
                o.value = p.period_id;
                o.textContent = `${p.description} ${p.year}${p.is_active ? ' (Active)' : ''}`;
                o.dataset.periodInfo = JSON.stringify(p);
                sel.appendChild(o);
            });
        } else {
            showApiStatus('error', result.message || 'Failed to load API periods');
            Swal.fire('Error', result.message || 'Failed to load periods from API', 'error');
        }
    } catch (e) {
        showApiStatus('error', 'Connection failed');
        Swal.fire('Error', 'Failed to connect to API: ' + e.message, 'error');
    }
}

async function loadLocalPeriods() {
    try {
        const result = await fetch('api/fetch_api_data.php?action=get_local_periods').then(r => r.json());
        const sel = document.getElementById('localPeriodSelect');
        sel.innerHTML = '<option value="">Select local period...</option>';
        if (result.success && result.data) {
            result.data.forEach(p => {
                const o = document.createElement('option');
                o.value = p.Periodid;
                o.textContent = `${p.PayrollPeriod} (${p.PhysicalYear})`;
                o.dataset.periodInfo = JSON.stringify(p);
                sel.appendChild(o);
            });
        } else {
            Swal.fire('Error', result.message || 'Failed to load local periods', 'error');
        }
    } catch (e) {
        Swal.fire('Error', 'Failed to load local periods: ' + e.message, 'error');
    }
}

function showApiStatus(status, message) {
    const card = document.getElementById('apiStatusCard');
    const dot  = document.getElementById('statusIndicator');
    card.classList.remove('hidden');
    document.getElementById('statusText').textContent = message;
    dot.classList.remove('bg-green-500','bg-yellow-500','bg-red-500','animate-pulse');
    if (status === 'connected')  dot.classList.add('bg-green-500');
    if (status === 'connecting') dot.classList.add('bg-yellow-500','animate-pulse');
    if (status === 'error')      dot.classList.add('bg-red-500');
}

function setupEventListeners() {
    document.getElementById('apiPeriodSelect').addEventListener('change', function() {
        selectedApiPeriodId   = this.value;
        selectedApiPeriodInfo = this.options[this.selectedIndex].dataset.periodInfo
            ? JSON.parse(this.options[this.selectedIndex].dataset.periodInfo) : null;
        document.getElementById('fetchDataBtn').disabled = !selectedApiPeriodId;
        clearData();
    });

    document.getElementById('localPeriodSelect').addEventListener('change', function() {
        selectedLocalPeriodId   = this.value;
        selectedLocalPeriodInfo = this.options[this.selectedIndex].dataset.periodInfo
            ? JSON.parse(this.options[this.selectedIndex].dataset.periodInfo) : null;
        updateUploadBtn();
    });

    document.getElementById('fetchDataBtn').addEventListener('click', fetchData);
    document.getElementById('uploadDataBtn').addEventListener('click', uploadData);
    document.getElementById('clearDataBtn').addEventListener('click', clearData);
    document.getElementById('searchInput').addEventListener('input', function() { filterData(this.value); });
    document.getElementById('exportBtn').addEventListener('click', exportToExcel);
    document.getElementById('prevPageBtn').addEventListener('click', () => { currentPage--; displayData(); });
    document.getElementById('nextPageBtn').addEventListener('click', () => { currentPage++; displayData(); });
}

function updateUploadBtn() {
    document.getElementById('uploadDataBtn').disabled = !(apiData.length > 0 && selectedLocalPeriodId);
}

async function fetchData() {
    if (!selectedApiPeriodId) { Swal.fire('Error','Please select an API period','error'); return; }
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('dataTable').classList.add('hidden');
    document.getElementById('loadingState').classList.remove('hidden');
    document.getElementById('fetchDataBtn').disabled = true;
    try {
        const result = await fetch(`api/fetch_api_data.php?action=get_data&period=${selectedApiPeriodId}`).then(r => r.json());
        if (result.success && result.data) {
            apiData = result.data;
            filteredData = [...apiData];
            updateStats(result.metadata);
            displayData();
            updateUploadBtn();
            document.getElementById('clearDataBtn').disabled = false;
            document.getElementById('searchInput').disabled = false;
            document.getElementById('exportBtn').disabled = false;
            Swal.fire('Success', `Fetched ${apiData.length} records from API`, 'success');
        } else {
            throw new Error(result.message || 'Failed to fetch data');
        }
    } catch (e) {
        Swal.fire('Error', 'Failed to fetch data: ' + e.message, 'error');
        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('emptyState').classList.remove('hidden');
    } finally {
        document.getElementById('fetchDataBtn').disabled = false;
    }
}

function updateStats(meta) {
    document.getElementById('statsCard').classList.remove('hidden');
    document.getElementById('totalRecords').textContent = meta.total_records || apiData.length;
    document.getElementById('totalAmount').textContent  = '₦' + (meta.total_amount || 0).toLocaleString('en-NG',{minimumFractionDigits:2});
    document.getElementById('selectedPeriod').textContent = `${meta.period.description} ${meta.period.year}`;
}

function displayData() {
    document.getElementById('loadingState').classList.add('hidden');
    document.getElementById('emptyState').classList.add('hidden');
    document.getElementById('dataTable').classList.remove('hidden');
    const body  = document.getElementById('tableBody');
    const start = (currentPage - 1) * recordsPerPage;
    const end   = Math.min(start + recordsPerPage, filteredData.length);
    body.innerHTML = filteredData.slice(start, end).map((item, i) => `
        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
            <td class="px-4 py-3 text-sm text-slate-500">${start + i + 1}</td>
            <td class="px-4 py-3 text-sm font-medium text-slate-800 dark:text-white">${item.staff_id}</td>
            <td class="px-4 py-3 text-sm text-slate-700 dark:text-slate-300">${item.name}</td>
            <td class="px-4 py-3 text-sm text-right font-semibold text-slate-800 dark:text-white">₦${parseFloat(item.amount).toLocaleString('en-NG',{minimumFractionDigits:2})}</td>
        </tr>`).join('');
    updatePagination(start, end);
}

function updatePagination(start, end) {
    const total = filteredData.length;
    document.getElementById('showingFrom').textContent  = total > 0 ? start + 1 : 0;
    document.getElementById('showingTo').textContent    = end;
    document.getElementById('showingTotal').textContent = total;
    document.getElementById('prevPageBtn').disabled     = currentPage === 1;
    document.getElementById('nextPageBtn').disabled     = currentPage >= Math.ceil(total / recordsPerPage);
}

function filterData(q) {
    q = q.toLowerCase().trim();
    filteredData = q ? apiData.filter(i => String(i.staff_id).toLowerCase().includes(q) || String(i.name).toLowerCase().includes(q)) : [...apiData];
    currentPage = 1;
    displayData();
}

function showLoadingModal() {
    const m = document.getElementById('loadingModal');
    m.classList.remove('hidden'); m.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    updateModalProgress(0, 'Preparing upload...');
    document.getElementById('recordCounter').classList.add('hidden');
}

function hideLoadingModal() {
    const m = document.getElementById('loadingModal');
    m.classList.add('hidden'); m.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
}

function updateModalProgress(pct, msg) {
    document.getElementById('modalProgressBar').style.width    = pct + '%';
    document.getElementById('modalProgressPercent').textContent = pct + '%';
    document.getElementById('modalProgressText').textContent   = msg;
}

async function uploadData() {
    if (!apiData.length)       { Swal.fire('Error','No data to upload','error'); return; }
    if (!selectedLocalPeriodId){ Swal.fire('Error','Please select a local period','error'); return; }

    const confirm = await Swal.fire({
        title: 'Confirm Upload',
        html: `Upload <strong>${apiData.length}</strong> records?<br>
               <small>From: ${selectedApiPeriodInfo.description} ${selectedApiPeriodInfo.year}</small><br>
               <small>To: ${selectedLocalPeriodInfo.PayrollPeriod} (${selectedLocalPeriodInfo.PhysicalYear})</small>`,
        icon: 'question', showCancelButton: true,
        confirmButtonText: 'Yes, Upload', cancelButtonText: 'Cancel'
    });
    if (!confirm.isConfirmed) return;

    showLoadingModal();
    document.getElementById('uploadDataBtn').disabled = true;
    try {
        updateModalProgress(10, 'Connecting to server...');
        document.getElementById('recordCounter').classList.remove('hidden');
        document.getElementById('totalCount').textContent = apiData.length;
        await new Promise(r => setTimeout(r, 300));
        updateModalProgress(25, 'Sending data...');

        const res = await fetch('api/upload_json_data.php', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({
                api_period:       selectedApiPeriodId,
                api_period_info:  selectedApiPeriodInfo,
                local_period:     selectedLocalPeriodId,
                local_period_info: selectedLocalPeriodInfo,
                resource_type:    '<?php echo OOUTH_RESOURCE_TYPE; ?>',
                resource_id:      '<?php echo OOUTH_RESOURCE_ID; ?>',
                resource_name:    '<?php echo OOUTH_RESOURCE_NAME; ?>',
                data:             apiData
            })
        });

        updateModalProgress(75, 'Processing records...');
        await new Promise(r => setTimeout(r, 300));
        const result = await res.json();

        updateModalProgress(100, 'Upload completed!');
        if (result.success && result.data) {
            document.getElementById('processedCount').textContent = result.data.success;
        }
        await new Promise(r => setTimeout(r, 800));
        hideLoadingModal();

        const resultsDiv = document.getElementById('uploadResults');
        resultsDiv.classList.remove('hidden');

        if (result.success) {
            const nf = result.data?.not_found_list?.length > 0
                ? `<div class="mt-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-xl p-4">
                     <p class="text-yellow-800 dark:text-yellow-300 font-semibold mb-2">
                       ⚠️ ${result.data.not_found_count} Staff Not Found in Database
                     </p>
                     <div class="max-h-40 overflow-y-auto">
                       <table class="w-full text-sm text-yellow-900 dark:text-yellow-200">
                         <thead class="bg-yellow-100 dark:bg-yellow-900/40 sticky top-0"><tr>
                           <th class="px-2 py-1 text-left">Staff ID</th>
                           <th class="px-2 py-1 text-left">Name</th>
                           <th class="px-2 py-1 text-right">Amount</th>
                         </tr></thead>
                         <tbody>${result.data.not_found_list.map(s => `
                           <tr class="border-b border-yellow-200 dark:border-yellow-800">
                             <td class="px-2 py-1">${s.staff_id}</td>
                             <td class="px-2 py-1">${s.name}</td>
                             <td class="px-2 py-1 text-right">₦${parseFloat(s.amount).toLocaleString('en-NG',{minimumFractionDigits:2})}</td>
                           </tr>`).join('')}
                         </tbody>
                       </table>
                     </div>
                   </div>` : '';

            resultsDiv.innerHTML = `
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-xl p-4">
                    <div class="flex items-center gap-3">
                        <span class="material-icons-round text-green-500 text-3xl">check_circle</span>
                        <div class="flex-1">
                            <p class="font-semibold text-green-800 dark:text-green-300">Upload Successful!</p>
                            <p class="text-sm text-green-700 dark:text-green-400 mt-1">${result.message}</p>
                            ${result.details ? `<p class="text-xs text-green-600 dark:text-green-500 mt-1">${result.details}</p>` : ''}
                        </div>
                    </div>${nf}
                </div>`;
            Swal.fire('Success!', result.message + (result.data?.not_found_count > 0 ? `\n\n⚠️ ${result.data.not_found_count} staff not found` : ''), 'success');
        } else {
            resultsDiv.innerHTML = `
                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-xl p-4 flex items-center gap-3">
                    <span class="material-icons-round text-red-500 text-3xl">error</span>
                    <div>
                        <p class="font-semibold text-red-800 dark:text-red-300">Upload Failed</p>
                        <p class="text-sm text-red-700 dark:text-red-400 mt-1">${result.message || 'An error occurred'}</p>
                    </div>
                </div>`;
            Swal.fire('Error', result.message || 'Upload failed', 'error');
        }
    } catch (e) {
        hideLoadingModal();
        Swal.fire('Error', 'Failed to upload: ' + e.message, 'error');
    } finally {
        document.getElementById('uploadDataBtn').disabled = false;
    }
}

function clearData() {
    apiData = []; filteredData = []; currentPage = 1;
    ['dataTable','uploadResults'].forEach(id => document.getElementById(id).classList.add('hidden'));
    document.getElementById('emptyState').classList.remove('hidden');
    document.getElementById('statsCard').classList.add('hidden');
    document.getElementById('uploadDataBtn').disabled = true;
    document.getElementById('clearDataBtn').disabled  = true;
    document.getElementById('searchInput').disabled   = true;
    document.getElementById('searchInput').value      = '';
    document.getElementById('exportBtn').disabled     = true;
}

function exportToExcel() {
    if (!filteredData.length) { Swal.fire('Error','No data to export','error'); return; }
    const rows = filteredData.map((item, i) => ({'S/N': i+1, 'Staff ID': item.staff_id, 'Name': item.name, 'Amount (N)': parseFloat(item.amount)}));
    const wb = XLSX.utils.book_new();
    const ws = XLSX.utils.json_to_sheet(rows);
    ws['!cols'] = [{wch:5},{wch:15},{wch:40},{wch:15}];
    XLSX.utils.book_append_sheet(wb, ws, 'Staff Data');
    const name = `Data_Upload_${selectedApiPeriodInfo?.description||'Period'}_${selectedApiPeriodInfo?.year||''}_${Date.now()}.xlsx`.replace(/ /g,'_');
    XLSX.writeFile(wb, name);
    Swal.fire('Success','Data exported to Excel','success');
}
</script>

<?php include 'includes/footer.php'; ?>
