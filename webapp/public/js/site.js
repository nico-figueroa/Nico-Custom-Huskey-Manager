document.addEventListener('DOMContentLoaded', function () {

    //
    // 1. Auto-submit vault selector
    //
    const vaultSelect = document.getElementById('vault');
    if (vaultSelect) {
        vaultSelect.addEventListener('change', () => vaultSelect.form?.submit());
    }


    //
    // 2. Search input (Enter key)
    //
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                searchTable(event);
            }
        });
    }


    //
    // 3. Edit Vault + Edit User buttons
    //
    document.querySelectorAll('.edit-btn').forEach(button => {
        button.addEventListener('click', () => {

            // Vault edit
            const editVaultId   = document.getElementById('editVaultId');
            const editVaultName = document.getElementById('editVaultName');
            if (editVaultId)   editVaultId.value   = button.dataset.vaultId   || '';
            if (editVaultName) editVaultName.value = button.dataset.vaultName || '';

            // User edit
            const editUserId    = document.getElementById('editUserId');
            const editUsername  = document.getElementById('editUsername');
            const editFirstName = document.getElementById('editFirstName');
            const editLastName  = document.getElementById('editLastName');
            const editEmail     = document.getElementById('editEmail');
            const editApproved  = document.getElementById('editApproved');

            if (button.dataset.userid) {
                if (editUserId)    editUserId.value    = button.dataset.userid;
                if (editUsername)  editUsername.value  = button.dataset.username  || '';
                if (editFirstName) editFirstName.value = button.dataset.firstName || '';
                if (editLastName)  editLastName.value  = button.dataset.lastName  || '';
                if (editEmail)     editEmail.value     = button.dataset.email     || '';
                if (editApproved)  editApproved.checked =
                    button.dataset.approved === '1' || button.dataset.approved === 'true';
            }
        });
    });


    //
    // 4. Delete buttons — populate modal fields before modal opens
    //
    document.querySelectorAll('.delete-btn').forEach(button => {
        button.addEventListener('click', () => {

            // Delete user
            const deleteUserId = document.getElementById('deleteUserId');
            if (deleteUserId && button.dataset.userid) {
                deleteUserId.value = button.dataset.userid;
            }

            // Delete permission
            const deletePermissionId = document.getElementById('deletePermissionId');
            if (deletePermissionId && button.dataset.deletePermissionId) {
                deletePermissionId.value = button.dataset.deletePermissionId;
            }

            // Delete password entry
            const deletePasswordId = document.getElementById('deletePasswordId');
            if (deletePasswordId && button.dataset.passwordId) {
                deletePasswordId.value = button.dataset.passwordId;
            }

            // Warning text for vault delete
            const deleteWarningPara = document.getElementById('deleteWarningPara');
            if (deleteWarningPara && button.dataset.vaultName) {
                deleteWarningPara.innerText =
                    `Are you sure you want to delete the ${button.dataset.vaultName} vault?`;
            }
        });
    });


    //
    // 5. Edit Password Modal
    //
    document.querySelectorAll('.edit-password-btn').forEach(button => {
        button.addEventListener('click', () => {
            const setVal = (id, val) => {
                const el = document.getElementById(id);
                if (el) el.value = val || '';
            };
            setVal('editPasswordId', button.dataset.passwordId);
            setVal('editUsername',   button.dataset.passwordUsername);
            setVal('editWebsite',    button.dataset.passwordWebsite);
            setVal('editPassword',   button.dataset.passwordPassword);
            setVal('editNotes',      button.dataset.passwordNotes);
        });
    });


    //
    // 6. Show/Hide Password Button
    //
    document.querySelectorAll('.show-password-btn').forEach(button => {
        button.addEventListener('click', () => {
            const passwordField = button.closest('tr')?.querySelector('.password-field');
            if (!passwordField) return;

            const showing = passwordField.type === 'text';
            passwordField.type    = showing ? 'password' : 'text';
            button.textContent    = showing ? 'Show Password' : 'Hide Password';

            if (!showing && button.dataset.entryId) {
                fetch('/components/logger.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({ entry_id: button.dataset.entryId })
                });
            }
        });
    });


    //
    // 7. Custom Modal System
    //

    // Open modal — data-target triggers
    document.querySelectorAll('[data-target]').forEach(function (trigger) {
        trigger.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const modal    = document.querySelector(targetId);
            if (!modal) return;

            modal.style.display         = 'flex';
            document.body.style.overflow = 'hidden';
        });
    });

    // Close modal — data-dismiss buttons
    document.querySelectorAll('[data-dismiss="modal"]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const modal = this.closest('.modal');
            if (modal) {
                modal.style.display          = 'none';
                document.body.style.overflow = '';
            }
        });
    });

    // Close modal — click on backdrop
    document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === this) {
                this.style.display           = 'none';
                document.body.style.overflow = '';
            }
        });
    });

    // Close modal — Escape key
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal').forEach(function (modal) {
                modal.style.display = 'none';
            });
            document.body.style.overflow = '';
        }
    });

});


//
// 8. Search Helpers
//
function handleSearchSubmit(event) {
    if (event.keyCode === 13) {
        const searchInput = document.getElementById('searchInput');
        if (searchInput && searchInput.value.trim() !== '') {
            window.location.href =
                './index.php?searchQuery=' + encodeURIComponent(searchInput.value);
        }
    }
}

function searchTable(event) {
    if (event.keyCode === 13) {
        const searchInput = document.getElementById('searchInput');
        if (!searchInput) return;

        const value = searchInput.value.trim();
        if (value === '') return;

        const currentPage   = window.location.pathname.split('/').pop();
        const vaultIdMatch  = window.location.search.match(/vault_id=(\d+)/);
        const vaultId       = vaultIdMatch ? vaultIdMatch[1] : '';

        if (currentPage === 'vault_details.php') {
            window.location.href =
                `./vault_details.php?vault_id=${vaultId}&searchQuery=${encodeURIComponent(value)}`;
        } else {
            window.location.href =
                `./index.php?searchQuery=${encodeURIComponent(value)}`;
        }
    }
}