(function($) {
    'use strict';

    var table;
    
    $(document).ready(function() {
        console.log('CVSM Admin Scripts Loaded'); // Debug: verify script is running
        
        // DataTable initialization
        table = $('#cv-submissions-table').DataTable({
            order: [[11, 'desc']], // Adjusted index due to new column
            pageLength: 25,
            dom: 'rt<"cvsm-table-bottom"<"cvsm-info"i><"cvsm-paging"p>>',
            language: {
                info: "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
                infoEmpty: "Aucune entrée à afficher",
                infoFiltered: "(filtré de _MAX_ entrées au total)",
                paginate: {
                    first: "Premier",
                    last: "Dernier",
                    next: "Suivant",
                    previous: "Précédent"
                },
                zeroRecords: "Aucun CV trouvé",
                emptyTable: "Aucun CV soumis pour le moment"
            },
            columnDefs: [
                { orderable: false, targets: [0, 9, 13] }, // Disable sorting on checkbox, CV link, actions
                { searchable: false, targets: [0, 9, 13] }
            ]
        });

        // Search input handler
        $('#cvsm-search-input').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Page length handler
        $('#cvsm-page-length').on('change', function() {
            table.page.len(parseInt(this.value)).draw();
        });

        // Filter tabs handler
        $('.filter-tab').on('click', function() {
            var filter = $(this).data('filter');
            
            // Update active tab
            $('.filter-tab').removeClass('active');
            $(this).addClass('active');
            
            // Apply filter
            if (filter === 'all') {
                table.column(11).search('').draw();
            } else {
                var statusMap = {
                    'pending': 'En attente',
                    'accepted': 'Accepté',
                    'rejected': 'Rejeté'
                };
                table.column(11).search(statusMap[filter] || filter).draw();
            }
        });
    });

    // =============================================
    // EVENT DELEGATION HANDLERS (outside document.ready)
    // These use event delegation so they work regardless of when elements are added
    // =============================================

    // Select All Handler - using event delegation
    $(document).on('click', '#cb-select-all-1', function() {
        var checked = this.checked;
        $('input[name="post[]"]').each(function() {
            this.checked = checked;
        });
    });

    // Bulk Action Handler - using event delegation for robustness
    $(document).on('click', '#cvsm-doaction', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('CVSM Bulk action button clicked');
        
        var action = $('#cvsm-bulk-action-selector').val();
        console.log('Selected action:', action);
        
        if (action === '-1') {
            alert('Veuillez sélectionner une action.');
            return;
        }
        
        var selected = [];
        $('input[name="post[]"]:checked').each(function() {
            selected.push($(this).val());
        });
        console.log('Selected IDs:', selected);
        
        if (selected.length === 0) {
            alert('Veuillez sélectionner au moins un élément.');
            return;
        }
        
        if (!confirm('Êtes-vous sûr de vouloir appliquer cette action sur ' + selected.length + ' éléments ?')) {
            return;
        }
        
        // Show processing state
        var btn = $(this);
        var originalText = btn.val();
        btn.prop('disabled', true).val('Traitement...');
        
        $.ajax({
            url: cvsmAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'cvsm_bulk_action',
                ids: selected,
                action_type: action,
                nonce: cvsmAjax.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    showToast(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('Erreur: ' + response.data, 'error');
                    btn.prop('disabled', false).val(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.log('Response:', xhr.responseText);
                showToast('Erreur de connexion', 'error');
                btn.prop('disabled', false).val(originalText);
            }
        });
    });

    // Accept button handler
    $(document).on('click', '.cvsm-btn-accept', function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var id = btn.data('id');
        var row = btn.closest('tr');
        
        if (!confirm('Êtes-vous sûr de vouloir accepter ce CV?')) {
            return;
        }
        
        // Show loading state
        btn.prop('disabled', true).html('<span class="cvsm-loading"></span> Traitement...');
        
        $.ajax({
            url: cvsmAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'cvsm_accept_cv',
                id: id,
                nonce: cvsmAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast('CV accepté avec succès!', 'success');
                    
                    // Update row status
                    row.find('.status-badge')
                        .removeClass('status-pending')
                        .addClass('status-accepted')
                        .text('Accepté');
                    
                    // Replace action buttons
                    row.find('.actions-cell').html('<span class="action-done">✓ Traité</span>');
                    
                    // Update statistics
                    updateStats();
                } else {
                    showToast('Erreur: ' + response.data, 'error');
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Accepter');
                }
            },
            error: function() {
                showToast('Erreur de connexion', 'error');
                btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Accepter');
            }
        });
    });

    // Reject button handler
    $(document).on('click', '.cvsm-btn-reject', function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var id = btn.data('id');
        var row = btn.closest('tr');
        
        if (!confirm('Êtes-vous sûr de vouloir rejeter ce CV?')) {
            return;
        }
        
        // Show loading state
        btn.prop('disabled', true).html('<span class="cvsm-loading"></span> Traitement...');
        
        $.ajax({
            url: cvsmAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'cvsm_reject_cv',
                id: id,
                nonce: cvsmAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast('CV rejeté', 'success');
                    
                    // Update row status
                    row.find('.status-badge')
                        .removeClass('status-pending')
                        .addClass('status-rejected')
                        .text('Rejeté');
                    
                    // Replace action buttons
                    row.find('.actions-cell').html('<span class="action-done">✗ Fermé</span>');
                    
                    // Update statistics
                    updateStats();
                } else {
                    showToast('Erreur: ' + response.data, 'error');
                    btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Rejeter');
                }
            },
            error: function() {
                showToast('Erreur de connexion', 'error');
                btn.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Rejeter');
            }
        });
    });

    // Delete button handler
    $(document).on('click', '.cvsm-btn-delete', function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var id = btn.data('id');
        var row = btn.closest('tr');
        
        if (!confirm('ATTENTION: Êtes-vous sûr de vouloir supprimer ce CV définitivement ? Cette action est irréversible.')) {
            return;
        }
        
        // Show loading state
        var originalContent = btn.html();
        btn.prop('disabled', true).html('<span class="cvsm-loading"></span>');
        
        $.ajax({
            url: cvsmAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'cvsm_delete_cv',
                id: id,
                nonce: cvsmAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast('CV supprimé avec succès', 'success');
                    
                    // Remove row from DataTable with animation
                    row.fadeOut(300, function() {
                        if (typeof table !== 'undefined' && table) {
                            table.row(row).remove().draw(false);
                        }
                        updateStats();
                    });
                } else {
                    showToast('Erreur: ' + response.data, 'error');
                    btn.prop('disabled', false).html(originalContent);
                }
            },
            error: function() {
                showToast('Erreur de connexion', 'error');
                btn.prop('disabled', false).html(originalContent);
            }
        });
    });

    // Show toast notification
    function showToast(message, type) {
        var toast = $('<div class="cvsm-toast ' + type + '">' + message + '</div>');
        $('body').append(toast);
        
        setTimeout(function() {
            toast.fadeOut(300, function() {
                $(this).remove();
            });
        }, 3000);
    }

    // Update statistics (simple page reload approach)
    function updateStats() {
        // For now, we'll update the stats on page reload
        // Could be enhanced with AJAX call to get updated counts
        var pendingCount = parseInt($('.cvsm-stat-card.pending .stat-number').text());
        var acceptedCount = parseInt($('.cvsm-stat-card.accepted .stat-number').text());
        var rejectedCount = parseInt($('.cvsm-stat-card.rejected .stat-number').text());
        
        // Simple update based on action taken
        // This is a visual update; actual counts refresh on page reload
    }

    // =============================================
    // DEVIS TABLE HANDLERS
    // =============================================

    var devisTable;

    $(document).ready(function() {
        // Devis DataTable initialization (only if table exists)
        if ($('#devis-submissions-table').length) {
            devisTable = $('#devis-submissions-table').DataTable({
                order: [[9, 'desc']], // Order by date column
                pageLength: 25,
                dom: 'rt<"cvsm-table-bottom"<"cvsm-info"i><"cvsm-paging"p>>',
                language: {
                    info: "Affichage de _START_ à _END_ sur _TOTAL_ entrées",
                    infoEmpty: "Aucune entrée à afficher",
                    infoFiltered: "(filtré de _MAX_ entrées au total)",
                    paginate: {
                        first: "Premier",
                        last: "Dernier",
                        next: "Suivant",
                        previous: "Précédent"
                    },
                    zeroRecords: "Aucun devis trouvé",
                    emptyTable: "Aucune demande de devis pour le moment"
                },
                columnDefs: [
                    { orderable: false, targets: [0, 8, 10] }, // Disable sorting on checkbox, plans, actions
                    { searchable: false, targets: [0, 8, 10] }
                ]
            });

            // Devis Search input handler
            $('#devis-search-input').on('keyup', function() {
                devisTable.search(this.value).draw();
            });

            // Devis Page length handler
            $('#devis-page-length').on('change', function() {
                devisTable.page.len(parseInt(this.value)).draw();
            });
        }
    });

    // Devis Select All Handler
    $(document).on('click', '#devis-cb-select-all', function() {
        var checked = this.checked;
        $('input[name="devis_post[]"]').each(function() {
            this.checked = checked;
        });
    });

    // Devis Bulk Action Handler
    $(document).on('click', '#devis-doaction', function(e) {
        e.preventDefault();
        e.stopPropagation();
        console.log('Devis Bulk action button clicked');
        
        var action = $('#devis-bulk-action-selector').val();
        console.log('Selected action:', action);
        
        if (action === '-1') {
            alert('Veuillez sélectionner une action.');
            return;
        }
        
        var selected = [];
        $('input[name="devis_post[]"]:checked').each(function() {
            selected.push($(this).val());
        });
        console.log('Selected IDs:', selected);
        
        if (selected.length === 0) {
            alert('Veuillez sélectionner au moins un élément.');
            return;
        }
        
        if (!confirm('Êtes-vous sûr de vouloir appliquer cette action sur ' + selected.length + ' éléments ?')) {
            return;
        }
        
        // Show processing state
        var btn = $(this);
        var originalText = btn.val();
        btn.prop('disabled', true).val('Traitement...');
        
        $.ajax({
            url: cvsmAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'devis_bulk_action',
                ids: selected,
                action_type: action,
                nonce: cvsmAjax.nonce
            },
            success: function(response) {
                console.log('AJAX Response:', response);
                if (response.success) {
                    showToast(response.data.message, 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showToast('Erreur: ' + response.data, 'error');
                    btn.prop('disabled', false).val(originalText);
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', error);
                console.log('Response:', xhr.responseText);
                showToast('Erreur de connexion', 'error');
                btn.prop('disabled', false).val(originalText);
            }
        });
    });

    // Devis Delete button handler
    $(document).on('click', '.devis-btn-delete', function(e) {
        e.preventDefault();
        
        var btn = $(this);
        var id = btn.data('id');
        var row = btn.closest('tr');
        
        if (!confirm('ATTENTION: Êtes-vous sûr de vouloir supprimer ce devis définitivement ? Cette action est irréversible.')) {
            return;
        }
        
        // Show loading state
        var originalContent = btn.html();
        btn.prop('disabled', true).html('<span class="cvsm-loading"></span>');
        
        $.ajax({
            url: cvsmAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'devis_delete',
                id: id,
                nonce: cvsmAjax.nonce
            },
            success: function(response) {
                if (response.success) {
                    showToast('Devis supprimé avec succès', 'success');
                    
                    // Remove row from DataTable with animation
                    row.fadeOut(300, function() {
                        if (typeof devisTable !== 'undefined' && devisTable) {
                            devisTable.row(row).remove().draw(false);
                        }
                        updateStats();
                    });
                } else {
                    showToast('Erreur: ' + response.data, 'error');
                    btn.prop('disabled', false).html(originalContent);
                }
            },
            error: function() {
                showToast('Erreur de connexion', 'error');
                btn.prop('disabled', false).html(originalContent);
            }
        });
    });

})(jQuery);
