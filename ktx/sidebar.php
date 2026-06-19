<nav>
    <a href="index.php" class="<?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-chart-pie"></i> Dashboard
    </a>
    <a href="BuildingListView.php" class="<?= basename($_SERVER['PHP_SELF']) == 'BuildingListView.php' || basename($_SERVER['PHP_SELF']) == 'RoomDetailView.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-building"></i> Buildings
    </a>
    <a href="StudentListView.php"  class="<?= basename($_SERVER['PHP_SELF']) == 'StudentListView.php' || basename($_SERVER['PHP_SELF']) == 'StudentFormView.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-users"></i> Students
    </a>
    <a href="ContractListView.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ContractListView.php' || basename($_SERVER['PHP_SELF']) == 'ContractFormView.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-contract"></i> Contracts
    </a>
    <a href="InvoiceView.php"      class="<?= basename($_SERVER['PHP_SELF']) == 'InvoiceView.php' || basename($_SERVER['PHP_SELF']) == 'InvoiceFormView.php' ? 'active' : '' ?>">
        <i class="fa-solid fa-file-invoice"></i> Invoices
    </a>
    <a href="ProfileView.php"      class="<?= basename($_SERVER['PHP_SELF']) == 'ProfileView.php' || basename($_SERVER['PHP_SELF']) == 'EditProfileView.php' ? 'active' : '' ?>">Profile</a>
</nav>