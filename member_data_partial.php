<?php
// Ensure $memberlist variable is available (passed from parent)
// Ensure $totalRows, $offset, $perPage, $page, $totalPages, $status, $sortBy, $search are available
?>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
    <?php if (mysqli_num_rows($memberlist) > 0): ?>
        <?php while ($row = mysqli_fetch_assoc($memberlist)): ?>
            <?php 
                // Gender Logic
                $gender = !empty($row['gender']) ? $row['gender'] : 'Male';
                $isFemale = (strtolower(trim($gender)) === 'female');
                $genderIcon = $isFemale ? 'female' : 'male';
                
                // Fallback Avatar Logic
                $fallbackAvatar = $isFemale ? 'images/female_avatar.png' : 'images/male_avatar.png';
                $passport = !empty($row['passport']) ? $row['passport'] : '';
                $imgSrc = (!empty($passport) && (file_exists($passport) || strpos($passport, 'http') !== false)) ? $passport : $fallbackAvatar;
            ?>
            
            <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 hover:border-primary/50 dark:hover:border-primary/50 transition-all group">
                <div class="flex flex-col sm:flex-row gap-5 items-start">
                    <div class="relative">
                        <div class="w-24 h-24 rounded-2xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center overflow-hidden border-2 border-slate-100 dark:border-slate-800 group-hover:border-primary/20 transition-colors">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($row['namee']); ?>&background=random" class="w-full h-full object-cover" alt="Member" onerror="this.src='https://ui-avatars.com/api/?name=<?php echo urlencode($row['namee']); ?>&background=random';">
                        </div>
                        <div class="absolute -bottom-2 left-1/2 -translate-x-1/2 bg-primary text-white text-[10px] font-bold px-2 py-0.5 rounded-full shadow-md whitespace-nowrap">
                            ID: <?php echo $row['patientid']; ?>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-bold text-slate-800 dark:text-white truncate group-hover:text-primary transition-colors"><?php echo htmlspecialchars($row['namee']); ?></h3>
                                <div class="flex items-center gap-3 mt-1">
                                    <span class="flex items-center gap-1 text-xs font-medium text-slate-500 dark:text-slate-400">
                                        <span class="material-icons-round text-[14px]"><?php echo $genderIcon; ?></span> <?php echo $gender; ?>
                                    </span>
                                    <span class="w-1 h-1 bg-slate-300 dark:bg-slate-600 rounded-full"></span>
                                    <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Regular Member</span>
                                </div>
                            </div>
                            <button class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                                <span class="material-icons-round">more_horiz</span>
                            </button>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-y-3 mt-4">
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wider">Mobile Phone</span>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($row['MobilePhone']); ?></span>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wider">Next of Kin</span>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($row['NOkName'] ?? '—'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-span-full p-12 text-center text-slate-500">
            <div class="flex flex-col items-center justify-center">
                <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                     <span class="material-icons-round text-3xl text-slate-300">search_off</span>
                </div>
                <h3 class="text-lg font-medium text-slate-900 dark:text-white">No members found</h3>
                <p class="max-w-sm mt-1 mb-6">We couldn't find any members matching your search criteria.</p>
                <button onclick="clearSearch()" class="text-primary hover:underline font-medium">Clear Filters</button>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Pagination -->
<div class="mt-10 flex flex-col sm:flex-row items-center justify-between gap-4">
    <p class="text-sm text-slate-500 dark:text-slate-400">Showing <?php echo $offset + 1; ?>-<?php echo min($offset + $perPage, $totalRows); ?> of <?php echo $totalRows; ?> members</p>
    <div class="flex gap-2">
        <!-- Previous Button -->
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?>&limit=<?php echo $perPage; ?>&status=<?php echo $status; ?>&sort=<?php echo $sortBy; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                <span class="material-icons-round">chevron_left</span>
            </a>
        <?php else: ?>
            <button class="w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 text-slate-300 dark:text-slate-600 cursor-not-allowed">
                <span class="material-icons-round">chevron_left</span>
            </button>
        <?php endif; ?>

        <!-- Page Numbers -->
        <button class="w-10 h-10 flex items-center justify-center rounded-xl bg-primary text-white font-bold shadow-md shadow-primary/20 transition-all"><?php echo $page; ?></button>
        
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page+1; ?>&limit=<?php echo $perPage; ?>&status=<?php echo $status; ?>&sort=<?php echo $sortBy; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all"><?php echo $page + 1; ?></a>
        <?php endif; ?>

        <!-- Next Button -->
        <?php if ($page < $totalPages): ?>
            <a href="?page=<?php echo $page+1; ?>&limit=<?php echo $perPage; ?>&status=<?php echo $status; ?>&sort=<?php echo $sortBy; ?>&search=<?php echo urlencode($search); ?>" class="pagination-link w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                <span class="material-icons-round">chevron_right</span>
            </a>
        <?php else: ?>
             <button class="w-10 h-10 flex items-center justify-center rounded-xl border border-slate-200 dark:border-slate-800 text-slate-300 dark:text-slate-600 cursor-not-allowed">
                <span class="material-icons-round">chevron_right</span>
            </button>
        <?php endif; ?>
    </div>
</div>
