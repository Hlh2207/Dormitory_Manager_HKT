<nav>
    <a href="BuildingListView.php" class="<?= basename($_SERVER['PHP_SELF']) == 'BuildingListView.php' || basename($_SERVER['PHP_SELF']) == 'RoomDetailView.php' ? 'active' : '' ?>">Buildings</a>
    <a href="StudentListView.php"  class="<?= basename($_SERVER['PHP_SELF']) == 'StudentListView.php' || basename($_SERVER['PHP_SELF']) == 'StudentFormView.php' ? 'active' : '' ?>">Students</a>
    <a href="ContractListView.php" class="<?= basename($_SERVER['PHP_SELF']) == 'ContractListView.php' || basename($_SERVER['PHP_SELF']) == 'ContractFormView.php' ? 'active' : '' ?>">Contracts</a>
    <a href="InvoiceView.php"      class="<?= basename($_SERVER['PHP_SELF']) == 'InvoiceView.php' || basename($_SERVER['PHP_SELF']) == 'InvoiceFormView.php' ? 'active' : '' ?>">Invoices</a>
    <a href="#">Violations</a>
    <a href="ProfileView.php"      class="<?= basename($_SERVER['PHP_SELF']) == 'ProfileView.php' || basename($_SERVER['PHP_SELF']) == 'EditProfileView.php' ? 'active' : '' ?>">Profile</a>
</nav>