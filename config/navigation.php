<?php
// Sidebar. An item appears only if the user holds its permission.
return [
    ['label' => 'Overview', 'items' => [
        ['Dashboard', 'admin/dashboard', 'bi-speedometer2', 'dashboard.view'],
    ]],
    ['label' => 'Farm', 'items' => [
        ['Farms', 'admin/farms', 'bi-buildings', 'farms.view'],
        ['Sheds', 'admin/sheds', 'bi-house-door', 'sheds.view'],
        ['Batches', 'admin/batches', 'bi-collection', 'batches.view'],
    ]],
    ['label' => 'Daily operations', 'items' => [
        ['Bird stock', 'admin/bird-stock', 'bi-clipboard-data', 'birds.view'],
        ['Bird transfers', 'admin/bird-transfers', 'bi-arrow-left-right', 'birds.view'],
        ['Mortality', 'admin/mortality', 'bi-heartbreak', 'mortality.view'],
        ['Feed stock', 'admin/feed-stock', 'bi-box-seam', 'feed.view'],
        ['Feed consumption', 'admin/feed-consumption', 'bi-basket', 'feed_consumption.view'],
        ['Egg production', 'admin/egg-production', 'bi-egg', 'eggs.view'],
        ['Medicines', 'admin/medicines', 'bi-capsule', 'medicines.view'],
        ['Treatments', 'admin/medicine-usage', 'bi-bandaid', 'medicines.view'],
        ['Vaccinations', 'admin/vaccination-records', 'bi-shield-plus', 'vaccinations.view'],
    ]],
    ['label' => 'Business', 'items' => [
        ['Purchases', 'admin/purchases', 'bi-cart-plus', 'purchases.view'],
        ['Sales', 'admin/sales', 'bi-receipt', 'sales.view'],
        ['Expenses', 'admin/expenses', 'bi-wallet2', 'expenses.view'],
        ['Customers', 'admin/customers', 'bi-people', 'customers.view'],
        ['Suppliers', 'admin/suppliers', 'bi-truck', 'suppliers.view'],
        ['Employees', 'admin/employees', 'bi-person-badge', 'employees.view'],
        ['Salaries', 'admin/salaries', 'bi-cash-stack', 'salaries.view'],
    ]],
    ['label' => 'Finance', 'items' => [
        ['Financial overview', 'admin/finance', 'bi-graph-up-arrow', 'finance.view'],
        ['Cash & bank', 'admin/accounts', 'bi-bank', 'accounts.view'],
        ['Payments', 'admin/payments', 'bi-credit-card', 'payments.view'],
        ['Reports', 'admin/reports', 'bi-file-earmark-bar-graph', 'any:reports.view,reports_financial.view'],
    ]],
    ['label' => 'Website', 'items' => [
        ['Website content', 'admin/website', 'bi-window-stack', 'cms.view'],
        ['Products', 'admin/products', 'bi-grid', 'cms.view'],
        ['Services', 'admin/services', 'bi-tools', 'cms.view'],
        ['Facilities', 'admin/facilities', 'bi-fan', 'cms.view'],
        ['Why choose us', 'admin/why-items', 'bi-patch-check', 'cms.view'],
        ['Farming process', 'admin/process-steps', 'bi-signpost-split', 'cms.view'],
        ['Gallery', 'admin/gallery', 'bi-images', 'cms.view'],
        ['Testimonials', 'admin/testimonials', 'bi-chat-quote', 'cms.view'],
        ['Blog', 'admin/blog', 'bi-journal-text', 'cms.view'],
        ['Messages', 'admin/messages', 'bi-envelope', 'cms.view'],
        ['Subscribers', 'admin/subscribers', 'bi-mailbox', 'cms.view'],
    ]],
    ['label' => 'Administration', 'items' => [
        ['Users', 'admin/users', 'bi-person-gear', 'users.view'],
        ['Roles', 'admin/roles', 'bi-shield-lock', 'roles.view'],
        ['Activity log', 'admin/activity-logs', 'bi-activity', 'activity_logs.view'],
        ['Settings', 'admin/settings', 'bi-gear', 'settings.view'],
    ]],
];
