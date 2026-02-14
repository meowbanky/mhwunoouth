<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}

require_once('Connections/hms.php');
require_once('NotificationService.php');
use class\services\NotificationService;

// --- Backend API Handler for AJAX ---
if (isset($_GET['action']) && $_GET['action'] === 'check_dnd') {
    header('Content-Type: application/json');
    $phoneNumber = $_GET['phone'] ?? '';
    
    if (empty($phoneNumber)) {
        echo json_encode(['status' => 'error', 'message' => 'Phone number is required']);
        exit;
    }

    try {
        $notificationService = new NotificationService($conn);
        $result = $notificationService->checkDNDStatus($phoneNumber);
        
        // Termii response example:
        // { "number": "234...", "network_code": "MTN Nigeria", "dnd_active": false, "message": "...", "status": "...", "network": "..." }
        echo json_encode(['status' => 'success', 'data' => $result]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}

// --- Page Logic ---

// Fetch Active Members
// Joining with tbl_personalinfo to get names.
$members = [];
try {
    // Only fetch members with phone numbers
    $query = "SELECT patientid, Fname, Lname, Mname, MobilePhone 
              FROM tbl_personalinfo 
              WHERE Status = 'Active' AND MobilePhone != '' AND MobilePhone IS NOT NULL
              ORDER BY Fname ASC";
    $stmt = $conn->query($query);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching members: " . $e->getMessage());
}

?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-900">
    
    <?php include 'includes/topbar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto p-8">
        <div class="max-w-7xl mx-auto space-y-8">
            
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-slate-800 dark:text-white">DND Status Checker</h1>
                    <p class="text-slate-500 dark:text-slate-400">Verify Do-Not-Disturb status for active members</p>
                </div>
                <div>
                    <button id="checkAllBtn" class="bg-primary hover:bg-primary-600 text-white px-6 py-2 rounded-xl font-medium shadow-sm transition-all flex items-center space-x-2">
                        <span class="material-icons-round">play_circle</span>
                        <span>Check All Status</span>
                    </button>
                    <button id="stopBtn" class="hidden bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-xl font-medium shadow-sm transition-all flex items-center space-x-2">
                        <span class="material-icons-round">stop_circle</span>
                        <span>Stop</span>
                    </button>
                </div>
            </div>

            <!-- Progress Bar (Hidden by default) -->
            <div id="progressContainer" class="hidden bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                <div class="flex justify-between mb-2 text-sm font-medium">
                    <span id="progressText">Processing...</span>
                    <span id="progressPercent">0%</span>
                </div>
                <div class="w-full bg-slate-100 dark:bg-slate-700 rounded-full h-2.5">
                    <div id="progressBar" class="bg-primary h-2.5 rounded-full" style="width: 0%"></div>
                </div>
            </div>

            <!-- Members Table -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead class="bg-slate-50 dark:bg-slate-700/50 text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            <tr>
                                <th class="px-6 py-4">S/N</th>
                                <th class="px-6 py-4">Member Name</th>
                                <th class="px-6 py-4">Phone Number</th>
                                <th class="px-6 py-4">Network</th>
                                <th class="px-6 py-4">DND Status</th>
                                <th class="px-6 py-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700" id="membersTableBody">
                            <?php if (count($members) > 0): ?>
                                <?php foreach ($members as $index => $member): ?>
                                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors row-item" 
                                        data-phone="<?php echo htmlspecialchars($member['MobilePhone']); ?>"
                                        data-id="<?php echo $index; ?>">
                                        
                                        <td class="px-6 py-4 text-sm text-slate-500 font-mono"><?php echo $index + 1; ?></td>
                                        
                                        <td class="px-6 py-4 text-sm font-medium text-slate-800 dark:text-slate-200">
                                            <?php echo htmlspecialchars($member['Lname'] . ', ' . $member['Fname'] . ' ' . $member['Mname']); ?>
                                            <div class="text-xs text-slate-400 font-mono mt-0.5"><?php echo htmlspecialchars($member['patientid']); ?></div>
                                        </td>
                                        
                                        <td class="px-6 py-4 text-sm font-mono text-slate-600 dark:text-slate-300">
                                            <?php echo htmlspecialchars($member['MobilePhone']); ?>
                                        </td>
                                        
                                        <td class="px-6 py-4 text-sm">
                                            <span class="network-badge px-2 py-1 rounded text-xs font-medium bg-slate-100 text-slate-500" id="net-<?php echo $index; ?>">-</span>
                                        </td>
                                        
                                        <td class="px-6 py-4 text-sm">
                                            <span class="dnd-badge px-2 py-1 rounded text-xs font-bold bg-slate-100 text-slate-500" id="status-<?php echo $index; ?>">Unknown</span>
                                            <div class="text-[10px] text-slate-400 mt-1" id="msg-<?php echo $index; ?>"></div>
                                        </td>
                                        
                                        <td class="px-6 py-4 text-right">
                                            <button class="check-btn text-xs font-medium bg-white border border-slate-200 hover:bg-slate-50 text-slate-600 px-3 py-1.5 rounded-lg shadow-sm transition-all"
                                                    onclick="checkSingleStatus(<?php echo $index; ?>, '<?php echo htmlspecialchars($member['MobilePhone']); ?>')">
                                                Check
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-slate-500">
                                        <span class="material-icons-round text-4xl mb-2 text-slate-300">person_off</span>
                                        <p>No active members found with phone numbers.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</main>

<script>
    let isProcessing = false;
    let shouldStop = false;

    // Check Single One
    async function checkSingleStatus(index, phone) {
        const statusEl = document.getElementById(`status-${index}`);
        const netEl = document.getElementById(`net-${index}`);
        const msgEl = document.getElementById(`msg-${index}`);
        const btn = document.querySelector(`button[onclick="checkSingleStatus(${index}, '${phone}')"]`);

        // Set Loading State
        statusEl.innerHTML = '<span class="animate-pulse">Checking...</span>';
        statusEl.className = 'dnd-badge px-2 py-1 rounded text-xs font-bold bg-blue-100 text-blue-600';
        if(btn) btn.disabled = true;

        try {
            const response = await fetch(`?action=check_dnd&phone=${encodeURIComponent(phone)}`);
            const json = await response.json();

            if (json.status === 'success') {
                const data = json.data;
                // data example: { network_code: "MTN Nigeria", dnd_active: false, message: "...", ... }
                
                // Update Network
                // API inconsistency: Success case puts name in network_code ("MTN Nigeria"), 404 case puts name in network ("MTN")
                // Code is numeric ("62130"). Name has letters.
                let netName = 'Unknown';
                if (data.network && /[a-zA-Z]/.test(data.network)) {
                    netName = data.network;
                } else if (data.network_code && /[a-zA-Z]/.test(data.network_code)) {
                    netName = data.network_code;
                } else {
                    netName = data.network || data.network_code || 'Unknown';
                }
                // Shorten network names by removing "Nigeria"
                netName = netName.replace(/Nigeria/gi, '').trim();
                netEl.innerText = netName;
                netEl.className = 'network-badge px-2 py-1 rounded text-xs font-medium bg-indigo-50 text-indigo-600 border border-indigo-100';

                // Update DND Status
                if (data.dnd_active === true) {
                    statusEl.innerText = 'DND ACTIVE';
                    statusEl.className = 'dnd-badge px-2 py-1 rounded text-xs font-bold bg-red-100 text-red-600 border border-red-100';
                } else {
                    statusEl.innerText = 'NOT ACTIVE';
                    statusEl.className = 'dnd-badge px-2 py-1 rounded text-xs font-bold bg-emerald-100 text-emerald-600 border border-emerald-100';
                }

                // Update Message
                msgEl.innerText = data.message || data.status;

            } else {
                statusEl.innerText = 'ERROR';
                statusEl.className = 'dnd-badge px-2 py-1 rounded text-xs font-bold bg-amber-100 text-amber-600';
                msgEl.innerText = json.message || 'API Error';
            }
        
        } catch (error) {
            console.error(error);
            statusEl.innerText = 'FAILED';
            statusEl.className = 'dnd-badge px-2 py-1 rounded text-xs font-bold bg-gray-200 text-gray-600';
            msgEl.innerText = 'Network Error';
        }

        if(btn) btn.disabled = false;
    }

    // Bulk Check Logic
    document.getElementById('checkAllBtn').addEventListener('click', async () => {
        if (isProcessing) return;
        
        isProcessing = true;
        shouldStop = false;
        
        // Toggle Buttons
        document.getElementById('checkAllBtn').classList.add('hidden');
        document.getElementById('stopBtn').classList.remove('hidden');
        document.getElementById('progressContainer').classList.remove('hidden');

        const rows = document.querySelectorAll('.row-item');
        const total = rows.length;
        let processed = 0;

        for (let i = 0; i < total; i++) {
            if (shouldStop) break;

            const row = rows[i];
            const phone = row.getAttribute('data-phone');
            const index = row.getAttribute('data-id');

            // Scroll to row if needed
           // row.scrollIntoView({ behavior: 'smooth', block: 'center' });

            await checkSingleStatus(index, phone);
            
            processed++;
            
            // Update Progress
            const percent = Math.round((processed / total) * 100);
            document.getElementById('progressBar').style.width = `${percent}%`;
            document.getElementById('progressPercent').innerText = `${percent}%`;
            document.getElementById('progressText').innerText = `Processed ${processed} of ${total}`;

            // Small delay to be nice to the API
            await new Promise(r => setTimeout(r, 300)); 
        }

        isProcessing = false;
        document.getElementById('checkAllBtn').classList.remove('hidden');
        document.getElementById('stopBtn').classList.add('hidden');
    });

    document.getElementById('stopBtn').addEventListener('click', () => {
        shouldStop = true;
        document.getElementById('progressText').innerText = 'Stopping...';
    });

</script>

<?php include 'includes/footer.php'; ?>
