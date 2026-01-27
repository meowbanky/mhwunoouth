<?php
// Ensure session is started and user is logged in
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}

require_once('Connections/hms.php');
mysqli_select_db($hms, $database_hms);

// --- 1. Handle Excel Export ---
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    // Re-run the query without limits for export
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=members_list_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    // fputcsv(stream, fields, separator, enclosure, escape) - escape default changed in PHP 8.1
    // We explicitly provide the defaults to avoid warnings if needed, or just rely on 3 args if we don't need custom escape
    fputcsv($output, array('Member ID', 'Name', 'Gender', 'Mobile', 'Next of Kin', 'NOK Phone', 'Status'), ",", "\"", "\\");

    // Build Export Query (Simplified clone of the main query logic below)
    $search = isset($_GET['search']) ? $_GET['search'] : '';
    $status = isset($_GET['status']) ? $_GET['status'] : 'Active';
    
    $where = [];
    if ($status != 'All') {
        $where[] = "`Status` = '" . mysqli_real_escape_string($hms, $status) . "'";
    }
    if (!empty($search)) {
        $s = mysqli_real_escape_string($hms, $search);
        $where[] = "(tbl_personalinfo.patientid LIKE '%$s%' OR tbl_personalinfo.Fname LIKE '%$s%' OR tbl_personalinfo.Lname LIKE '%$s%')";
    }
    $whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';
    
    $query = "SELECT tbl_personalinfo.patientid, 
                     concat(ifnull(tbl_personalinfo.Lname,''),', ',ifnull(tbl_personalinfo.Fname,''),' ',ifnull(tbl_personalinfo.Mname,'')) AS namee,
                     ifnull(tbl_personalinfo.gender,'Male') as gender, 
                     tbl_personalinfo.MobilePhone, tbl_nok.NOkName, tbl_nok.NOKPhone, tbl_personalinfo.Status
                     FROM tbl_personalinfo 
                     LEFT JOIN tbl_nok ON tbl_nok.patientId = tbl_personalinfo.patientid 
                     $whereClause
                     ORDER BY patientid";
    
    $result = mysqli_query($hms, $query);
    while ($row = mysqli_fetch_assoc($result)) {
        fputcsv($output, $row, ",", "\"", "\\");
    }
    fclose($output);
    exit;
}

// --- 2. Pagination & Search Logic ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['limit']) ? (int)$_GET['limit'] : 20; // Default to 20 to match UI feel
$status = isset($_GET['status']) ? $_GET['status'] : 'Active';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Validation
if ($page < 1) $page = 1;

// Build Query
$where = [];
if ($status != 'All') {
    $where[] = "`Status` = '" . mysqli_real_escape_string($hms, $status) . "'";
}

if (!empty($search)) {
    $s = mysqli_real_escape_string($hms, $search);
    $where[] = "(tbl_personalinfo.patientid LIKE '%$s%' OR tbl_personalinfo.Fname LIKE '%$s%' OR tbl_personalinfo.Lname LIKE '%$s%' OR tbl_personalinfo.MobilePhone LIKE '%$s%')";
}

$whereClause = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

// Sort Logic
$sortClause = "ORDER BY patientid DESC"; // Default Newest
if ($sortBy == 'name_asc') {
    $sortClause = "ORDER BY namee ASC";
} elseif ($sortBy == 'id_asc') {
    $sortClause = "ORDER BY patientid ASC";
}

// Count Total for Pagination
$countQuery = "SELECT COUNT(*) as total FROM tbl_personalinfo $whereClause";
$countResult = mysqli_query($hms, $countQuery);
$totalRows = mysqli_fetch_assoc($countResult)['total'];
$totalPages = ceil($totalRows / $perPage);
$offset = ($page - 1) * $perPage;

// Main Data Query
$query_memberlist = "SELECT tbl_personalinfo.sfxname, tbl_personalinfo.patientid, 
                     concat(ifnull(tbl_personalinfo.Lname,''),', ',ifnull(tbl_personalinfo.Fname,''),' ',ifnull(tbl_personalinfo.Mname,'')) AS namee,
                     ifnull(tbl_personalinfo.gender,'Male') as gender, 
                     tbl_personalinfo.MobilePhone, tbl_personalinfo.passport, 
                     tbl_nok.NOkName, tbl_nok.NOKPhone
                     FROM tbl_personalinfo 
                     LEFT JOIN tbl_nok ON tbl_nok.patientId = tbl_personalinfo.patientid 
                     $whereClause
                     $sortClause
                     LIMIT $offset, $perPage";

$memberlist = mysqli_query($hms, $query_memberlist) or die(mysqli_error($hms));

// AJAX Request Handler - Grid Partial
if (isset($_GET['ajax']) && !isset($_GET['action'])) {
    include 'member_data_partial.php';
    exit;
}

// AJAX Request Handler - Autocomplete JSON
if (isset($_GET['action']) && $_GET['action'] == 'autocomplete') {
    header('Content-Type: application/json');
    $s = isset($_GET['q']) ? mysqli_real_escape_string($hms, $_GET['q']) : '';
    
    if(strlen($s) < 2) { echo json_encode([]); exit; }

    $query = "SELECT patientid, Fname, Lname, MobilePhone FROM tbl_personalinfo 
              WHERE patientid LIKE '%$s%' OR Fname LIKE '%$s%' OR Lname LIKE '%$s%' OR MobilePhone LIKE '%$s%'
              LIMIT 10";
    $result = mysqli_query($hms, $query);
    
    $data = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $name = trim($row['Fname'] . ' ' . $row['Lname']);
        $data[] = [
            'value' => $row['patientid'],
            'label' => "$name ({$row['patientid']})",
            'desc' => $row['MobilePhone']
        ];
    }
    echo json_encode($data);
    exit;
}

?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    
    <?php include 'includes/topbar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto p-4 md:p-8">
        <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">List of Members</h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Managing <?php echo $totalRows; ?> active members across the portal.</p>
            </div>
            <div class="flex items-center gap-3">
                 <button onclick="window.location.href='registration.php'" class="bg-primary hover:bg-primary/90 text-white px-5 py-2.5 rounded-xl font-semibold shadow-lg shadow-primary/20 flex items-center gap-2 transition-all transform active:scale-95">
                    <span class="material-icons-round text-[20px]">person_add</span>
                    Add New Member
                </button>
                 <button onclick="exportToExcel()" class="bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-5 py-2.5 rounded-xl font-semibold hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors flex items-center gap-2">
                    <span class="material-icons-round text-[20px]">file_download</span>
                    Export
                </button>
            </div>
        </header>

        <!-- Filter Bar -->
        <form method="GET" action="memberlist.php" id="filterForm" class="bg-white dark:bg-slate-900 p-4 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 mb-6 flex flex-col lg:flex-row gap-4 items-center">
            <div class="relative flex-1 w-full z-50">
                <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
                <input type="text" name="search" id="searchInput" value="<?php echo htmlspecialchars($search); ?>" class="w-full pl-10 pr-10 py-2.5 bg-slate-50 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-primary/50 text-sm dark:text-white transition-all" placeholder="Search by name, ID, or mobile..." autocomplete="off">
                
                <!-- Autocomplete Dropdown Container -->
                <div id="autocompleteList" class="hidden absolute top-full left-0 w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-xl shadow-xl mt-2 max-h-60 overflow-y-auto z-50"></div>

                <button type="button" onclick="clearSearch()" id="clearSearchBtn" class="hidden absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 transition-colors">
                    <span class="material-icons-round text-sm">close</span>
                </button>
            </div>
            <div class="flex items-center gap-2 w-full lg:w-auto">
                <select name="status" id="statusFilter" class="flex-1 lg:w-40 py-2.5 bg-slate-50 dark:bg-slate-800 border-none rounded-xl text-sm focus:ring-2 focus:ring-primary/50 dark:text-white transition-all cursor-pointer">
                    <option value="Active" <?php if($status == 'Active') echo 'selected'; ?>>Active</option>
                    <option value="In-active" <?php if($status == 'In-active') echo 'selected'; ?>>In-active</option>
                    <option value="All" <?php if($status == 'All') echo 'selected'; ?>>All Genders</option> <!-- Keeping label 'All Genders' per design ref, but logic is status -->
                </select>
                <select name="sort" id="sortFilter" class="flex-1 lg:w-48 py-2.5 bg-slate-50 dark:bg-slate-800 border-none rounded-xl text-sm focus:ring-2 focus:ring-primary/50 dark:text-white transition-all cursor-pointer">
                    <option value="newest" <?php if($sortBy == 'newest') echo 'selected'; ?>>Sort by: Newest</option>
                    <option value="name_asc" <?php if($sortBy == 'name_asc') echo 'selected'; ?>>Sort by: Name (A-Z)</option>
                    <option value="id_asc" <?php if($sortBy == 'id_asc') echo 'selected'; ?>>Sort by: ID (Low to High)</option>
                </select>
                <button type="submit" class="hidden">Submit</button>
            </div>
        </form>

        <div id="membersResults">
            <?php include 'member_data_partial.php'; ?>
        </div>

    </div>
</main>
<script src="jquery-1.8.0.min.js"></script>
<script>
function exportToExcel() {
    // Clone the current URL search params
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'excel');
    window.location.href = window.location.pathname + '?' + params.toString();
}

function toggleClearButton() {
    var input = document.getElementById('searchInput');
    var btn = document.getElementById('clearSearchBtn');
    if (input.value.length > 0) {
        btn.classList.remove('hidden');
    } else {
        btn.classList.add('hidden');
    }
}

function clearSearch() {
    var input = document.getElementById('searchInput');
    input.value = '';
    // Use the global fetch function if available, else submit form
    if(typeof window.fetchMembersGlobal === 'function') {
        window.fetchMembersGlobal('memberlist.php'); // Reset URL to base
    } else {
        document.getElementById('filterForm').submit();
    }
}

// AJAX Implementation & Autocomplete (jQuery 1.8 Compatible)
$(document).ready(function() {
    var searchTimeout;
    
    // Global fetch function
    window.fetchMembersGlobal = function(urlOverride) {
        var url = urlOverride || "memberlist.php";
        var data = $('#filterForm').serialize();
        
        // Ensure ajax param
        if(url.indexOf('?') === -1) {
             data += '&ajax=1';
        } else {
             if(url.indexOf('ajax=1') === -1) url += '&ajax=1';
             if(urlOverride) data = null; 
        }

        $('#membersResults').addClass('opacity-50 pointer-events-none');
        $('.pagination-link').css('pointer-events', 'none'); 

        $.ajax({
            url: url,
            type: 'GET',
            data: data,
            success: function(response) {
                $('#membersResults').html(response).removeClass('opacity-50 pointer-events-none');
                
                // History
                if(window.history && window.history.pushState) {
                    var historyUrl = urlOverride ? url.replace('&ajax=1', '').replace('?ajax=1', '') 
                                                 : window.location.pathname + '?' + $('#filterForm').serialize();
                    window.history.pushState({path: historyUrl}, '', historyUrl);
                }
            },
            error: function() {
                // Silently handle error or log to console
                $('#membersResults').removeClass('opacity-50 pointer-events-none');
            }
        });
    };

    // Event Delegation using .on()
    $(document).on('click', '.pagination-link', function(e) {
        e.preventDefault();
        window.fetchMembersGlobal($(this).attr('href'));
    });

    // AUTOCOMPLETE LOGIC
    $('#searchInput').on('keyup', function() {
        var val = $(this).val();
        var $list = $('#autocompleteList');
        // Toggle clear button
        toggleClearButton();

        if(val.length < 2) {
            $list.addClass('hidden').html('');
            return;
        }

        $.getJSON("memberlist.php", { action: 'autocomplete', q: val }, function(data) {
            if(data.length > 0) {
                var html = '<ul>';
                for(var i=0; i < data.length; i++) {
                    var item = data[i];
                    html += '<li class="p-3 hover:bg-slate-50 dark:hover:bg-slate-800 cursor-pointer border-b border-slate-100 dark:border-slate-800 last:border-0 transition-colors" data-id="'+item.value+'" data-label="'+item.label+'">';
                    html += '<div class="flex justify-between items-center">';
                    html += '<div><p class="font-bold text-slate-800 dark:text-slate-200 text-sm">' + item.label + '</p>';
                    html += '<p class="text-xs text-slate-500">' + item.desc + '</p></div>';
                    html += '<span class="material-icons-round text-slate-300 text-sm">chevron_right</span>';
                    html += '</div></li>';
                }
                html += '</ul>';
                $list.html(html).removeClass('hidden');
            } else {
                $list.html('<div class="p-4 text-center text-sm text-slate-500">No matches found</div>').removeClass('hidden');
            }
        });
    });

    // Delegated click for autocomplete items
    $(document).on('click', '#autocompleteList li', function() {
        var id = $(this).data('id');
        var label = $(this).data('label');
        selectMember(id, label);
    });

    // Hide autocomplete on click outside
    $(document).on('click', function(e) {
        if($(e.target).closest('#autocompleteList, #searchInput').length === 0) {
            $('#autocompleteList').addClass('hidden');
        }
    });

    window.selectMember = function(id, name) {
        $('#searchInput').val(name); 
        $('#autocompleteList').addClass('hidden');
        $('#searchInput').val(id); 
        window.fetchMembersGlobal();
    };

    // Filter Changes trigger refresh
    $('#statusFilter, #sortFilter').on('change', function() {
        window.fetchMembersGlobal();
    });

    $('#filterForm').on('submit', function(e) {
        e.preventDefault();
        window.fetchMembersGlobal();
        return false;
    });
});
</script>
<?php include 'includes/footer.php'; ?>
<?php mysqli_free_result($memberlist); ?>
