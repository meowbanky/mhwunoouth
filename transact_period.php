<?php
session_start();
// Authentication Check only
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit();
}
require_once('Connections/hms.php');
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Main Content Area -->
<div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <main class="w-full flex-grow p-6">
        
        <!-- Header Section -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6 mb-8">
            <div>
                <h2 class="text-2xl font-bold text-slate-800 dark:text-white">Transaction Periods</h2>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1">Manage and create financial transaction periods.</p>
            </div>
            
            <!-- Create Form -->
            <form id="createForm" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 p-4 rounded-2xl flex flex-col sm:flex-row items-end sm:items-center gap-4 shadow-sm">
                <input type="hidden" name="action" value="create">
                <div>
                    <label class="block text-[10px] uppercase font-bold text-primary mb-1 pl-1">Select Month</label>
                    <input name="period_date" id="period_date" required class="bg-slate-50 dark:bg-slate-700 border-slate-200 dark:border-slate-600 rounded-lg text-sm focus:ring-primary focus:border-primary w-full sm:w-auto" type="month"/>
                </div>
                <button type="submit" id="btnSave" class="bg-primary hover:bg-blue-600 text-white px-6 py-2.5 rounded-lg font-semibold flex items-center gap-2 shadow-lg shadow-blue-500/20 transition-all active:scale-95 w-full sm:w-auto justify-center">
                    <span class="material-icons-round text-sm">add</span>
                    Create New Period
                </button>
            </form>
        </div>

        <!-- Toolbar & Pagination Controls -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4 mb-6">
            <h3 class="text-xl font-bold text-slate-800 dark:text-white flex items-center gap-2">
                Historical Timeline 
                <span id="totalBadge" class="bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs px-2 py-0.5 rounded-full">0</span>
            </h3>
            
            <div class="flex items-center gap-3">
                 <!-- Rows Per Page -->
                 <select id="rowsPerPage" class="bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-sm rounded-lg focus:ring-primary focus:border-primary">
                    <option value="8">8 rows</option>
                    <option value="12" selected>12 rows</option>
                    <option value="24">24 rows</option>
                    <option value="48">48 rows</option>
                </select>

                <!-- Pagination Buttons -->
                <div class="flex rounded-lg shadow-sm">
                    <button type="button" id="prevPage" class="px-3 py-2 text-sm font-medium bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-l-lg hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50">
                        Prev
                    </button>
                    <span id="pageInfo" class="px-4 py-2 text-sm bg-slate-50 dark:bg-slate-900 border-t border-b border-slate-200 dark:border-slate-700 flex items-center">
                        Page 1
                    </span>
                    <button type="button" id="nextPage" class="px-3 py-2 text-sm font-medium bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-r-lg hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50">
                        Next
                    </button>
                </div>
            </div>
        </div>

        <!-- Loader -->
        <div id="loader" class="hidden py-12 text-center">
            <div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-r-primary"></div>
            <p class="text-slate-500 mt-2 text-sm">Loading periods...</p>
        </div>

        <!-- Grid Container -->
        <div id="periodGrid" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            <!-- Content Injected via JS -->
        </div>

    </main>
    <?php include 'includes/footer.php'; ?>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    let currentPage = 1;
    let limit = 12; // Default matches HTML select

    // Load Data Function
    function loadData() {
        $('#loader').removeClass('hidden');
        $('#periodGrid').addClass('opacity-50');

        $.ajax({
            url: 'transact_period_api.php',
            type: 'GET',
            data: { action: 'fetch', page: currentPage, limit: limit },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    renderGrid(response.data, response.meta);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                console.error(xhr);
                Swal.fire('Error', 'Failed to load data', 'error');
            },
            complete: function() {
                $('#loader').addClass('hidden');
                $('#periodGrid').removeClass('opacity-50');
            }
        });
    }

    // Render Function
    function renderGrid(data, meta) {
        const grid = $('#periodGrid');
        grid.empty();
        
        // Update Meta UI
        $('#totalBadge').text(meta.total);
        $('#pageInfo').text(`Page ${meta.page} of ${meta.pages || 1}`);
        $('#prevPage').prop('disabled', meta.page <= 1);
        $('#nextPage').prop('disabled', meta.page >= meta.pages);

        if (data.length === 0) {
            grid.html(`
                <div class="col-span-full py-12 text-center text-slate-500">
                    <span class="material-icons-round text-4xl mb-2 text-slate-300">event_busy</span>
                    <p>No transaction periods found.</p>
                </div>
            `);
            return;
        }

        data.forEach((period, index) => {
            // First item on Page 1 is "Current" logic from request, or just first item
            const isCurrent = (currentPage === 1 && index === 0);
            const cardClass = isCurrent 
                ? "bg-white dark:bg-slate-800 border-2 border-primary/30 shadow-md" 
                : "bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 hover:border-primary/40";
            
            const badgeHtml = isCurrent 
                ? `<div class="absolute top-0 right-0 p-3"><span class="bg-primary text-white text-[10px] font-bold px-2 py-1 rounded uppercase">Current</span></div>` 
                : '';

            const iconHtml = !isCurrent 
                ? `<span class="material-icons-round text-slate-300 dark:text-slate-600">event</span>` 
                : '';

            const html = `
                <div class="${cardClass} p-5 rounded-2xl relative overflow-hidden group transition-all cursor-default relative">
                    ${badgeHtml}
                    ${!isCurrent ? `
                    <button onclick="deletePeriod(${period.Periodid})" class="absolute top-2 right-2 p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 rounded-full transition-colors z-10 opacity-0 group-hover:opacity-100" title="Delete Period">
                        <span class="material-icons-round text-sm">delete</span>
                    </button>
                    ` : ''}

                    <div class="flex justify-between items-start mb-4 pr-6">
                        <div>
                            <p class="text-slate-400 text-xs font-medium uppercase tracking-widest">${period.PhysicalYear}</p>
                            <h4 class="text-lg font-bold text-slate-800 dark:text-white group-hover:text-primary transition-colors">
                                ${period.PhysicalMonth}
                            </h4>
                        </div>
                        ${iconHtml}
                    </div>
                    <div class="flex items-center gap-4 py-2 border-t border-slate-50 dark:border-slate-700 mt-2">
                            <p class="text-[10px] text-slate-500 uppercase font-semibold">
                            ID: ${period.Periodid}
                            </p>
                            <span class="text-[10px] text-slate-400 ml-auto truncate max-w-[100px]" title="${period.InsertedBy}">
                            By: ${period.InsertedBy}
                            </span>
                    </div>
                </div>
            `;
            grid.append(html);
        });
    }

    // Expose delete function separately or bind event delegation
    window.deletePeriod = function(id) {
        Swal.fire({
            title: 'Delete Period?',
            text: "This action cannot be undone. Checks will be performed to ensure no transactions exist.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'transact_period_api.php',
                    type: 'POST',
                    data: { action: 'delete', id: id },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: response.message,
                                timer: 1500,
                                showConfirmButton: false
                            });
                            loadData();
                        } else {
                            Swal.fire('Cannot Delete', response.message, 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Error', 'Request failed', 'error');
                    }
                });
            }
        });
    }

    // Handlers
    $('#createForm').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSave');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<span class="inline-block animate-spin rounded-full h-4 w-4 border-2 border-white border-r-transparent mr-2"></span> Saving...');

        $.ajax({
            url: 'transact_period_api.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message,
                        timer: 1500,
                        showConfirmButton: false
                    });
                    $('#periodGrid').empty(); // Clear to show loading
                    currentPage = 1; // Reset to page 1 to see new item
                    loadData();
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function(xhr) {
                Swal.fire('Error', 'Request failed', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    $('#rowsPerPage').on('change', function() {
        limit = $(this).val();
        currentPage = 1;
        loadData();
    });

    $('#prevPage').on('click', function() {
        if (currentPage > 1) {
            currentPage--;
            loadData();
        }
    });

    $('#nextPage').on('click', function() {
        currentPage++;
        loadData();
    });

    // Initial Load
    loadData();
});
</script>
</body>
</html>
