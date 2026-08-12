<?php
/**
 * The single source of truth for admin navigation.
 *
 * sidebar.php (desktop, lg and up) and topbar.php (mobile menu, below lg)
 * both render from this list. They were previously two hardcoded copies,
 * which drifted: pages went missing from the mobile menu and dead links
 * survived in one file after being fixed in the other.
 *
 * To add a page to the nav, add one entry here. Both menus pick it up.
 *
 * Each link takes:
 *   href          target page
 *   icon          Material Icons Round name
 *   label         text shown in both menus
 *   mobile_label  optional override for the mobile menu only
 *   mobile_icon   optional override for the mobile menu only
 *   active        set false to opt out of active-state highlighting
 */

if (!function_exists('navGroups')) {
    /**
     * Navigation, grouped. A group with a heading renders a section label.
     *
     * @return array<int, array{heading: ?string, links: array<int, array<string, mixed>>}>
     */
    function navGroups(): array
    {
        return [
            [
                'heading' => null,
                'links'   => [
                    ['href' => 'dashboard.php',           'icon' => 'dashboard',            'label' => 'Dashboard'],
                    ['href' => 'memberlist.php',          'icon' => 'group',                'label' => 'Members'],
                    ['href' => 'addloan.php',             'icon' => 'payments',             'label' => 'Loans & Finance', 'mobile_label' => 'Loans'],
                    ['href' => 'withdrawal.php',          'icon' => 'account_balance_wallet', 'label' => 'Withdrawals'],
                    ['href' => 'bank_deposit.php',        'icon' => 'account_balance',      'label' => 'Bank Deposits'],
                    ['href' => 'loanContri_Compare.php',  'icon' => 'compare_arrows',       'label' => 'Loan Comparison'],
                    ['href' => 'editContributions.php',   'icon' => 'volunteer_activism',   'label' => 'Contributions'],
                    ['href' => 'bank_loan_report.php',    'icon' => 'account_balance',      'label' => 'Bank Loans'],
                    ['href' => 'mastertransaction.php',   'icon' => 'assessment',           'label' => 'Reports'],
                    ['href' => 'status.php',              'icon' => 'analytics',            'label' => 'Status'],
                    ['href' => 'bulksms.php',             'icon' => 'sms',                  'label' => 'SMS Center'],
                    ['href' => 'dnd_status_checker.php',  'icon' => 'do_not_disturb_on',    'label' => 'DND Checker'],
                ],
            ],
            [
                'heading' => 'System',
                'links'   => [
                    ['href' => 'transact_period.php', 'icon' => 'calendar_today',   'label' => 'Period'],
                    ['href' => 'process2.php',        'icon' => 'fact_check',       'label' => 'Process Transaction'],
                    ['href' => 'api_upload.php',      'icon' => 'cloud_upload',     'label' => 'API Upload'],
                    ['href' => 'registeruser.php',    'icon' => 'manage_accounts',  'label' => 'User Management', 'mobile_label' => 'User Settings', 'mobile_icon' => 'settings'],
                    ['href' => 'index.php?logout=true', 'icon' => 'logout',         'label' => 'Logout', 'active' => false],
                ],
            ],
        ];
    }
}

if (!function_exists('isNavActive')) {
    /** Is this link the page currently being viewed? */
    function isNavActive(array $link): bool
    {
        if (($link['active'] ?? true) === false) {
            return false;
        }

        return $link['href'] === basename($_SERVER['PHP_SELF']);
    }
}

if (!function_exists('navIcon')) {
    /** Icon for the given link, honouring a mobile-only override. */
    function navIcon(array $link, bool $isMobile = false): string
    {
        return $isMobile ? ($link['mobile_icon'] ?? $link['icon']) : $link['icon'];
    }
}

if (!function_exists('navLabel')) {
    /** Label for the given link, honouring a mobile-only override. */
    function navLabel(array $link, bool $isMobile = false): string
    {
        return $isMobile ? ($link['mobile_label'] ?? $link['label']) : $link['label'];
    }
}
