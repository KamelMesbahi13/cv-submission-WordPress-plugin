(function($) {
    'use strict';

    var table;
    
    $(document).ready(function() {
        table = $('#cv-submissions-table').DataTable({
            order: [[10, 'desc']],
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
                { orderable: false, targets: [8, 12] },
                { searchable: false, targets: [0, 8, 12] }
            ]
        });

        $('#cvsm-search-input').on('keyup', function() {
            table.search(this.value).draw();
        });

        $('#cvsm-page-length').on('change', function() {
            table.page.len(parseInt(this.value)).draw();
        });

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
                        
                        // Redirect after short delay
                        setTimeout(function() {
                            window.location.href = response.data.redirect;
                        }, 1500);
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
                            table.row(row).remove().draw(false);
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

})(jQuery);
