(function () {
  window.ppdbFilterTable = function (input, selector) {
    var term = (input.value || '').toLowerCase();
    var table = input.closest('.ppdb-card') ? input.closest('.ppdb-card').querySelector(selector) : document.querySelector(selector);
    if (!table) return;
    Array.from(table.tBodies[0].rows).forEach(function (row) {
      if (row.classList.contains('ppdb-detail-row')) return;
      var text = row.innerText.toLowerCase();
      row.style.display = text.indexOf(term) > -1 ? '' : 'none';
    });
  };

  function makeSortable(table) {
    if (!table || table.tHead == null) return;
    var headers = Array.from(table.tHead.rows[0].cells);
    headers.forEach(function (th, idx) {
      th.style.cursor = 'pointer';
      th.addEventListener('click', function () {
        var asc = th.getAttribute('data-sort') !== 'asc';
        headers.forEach(function (h) { h.removeAttribute('data-sort'); });
        th.setAttribute('data-sort', asc ? 'asc' : 'desc');
        var rows = Array.from(table.tBodies[0].rows).filter(function (r) { return !r.classList.contains('ppdb-detail-row'); });
        rows.sort(function (a, b) {
          var av = (a.cells[idx] ? a.cells[idx].innerText.trim() : '').toLowerCase();
          var bv = (b.cells[idx] ? b.cells[idx].innerText.trim() : '').toLowerCase();
          if (!isNaN(parseFloat(av)) && !isNaN(parseFloat(bv))) { av = parseFloat(av); bv = parseFloat(bv); }
          if (av < bv) return asc ? -1 : 1;
          if (av > bv) return asc ? 1 : -1;
          return 0;
        });
        rows.forEach(function (r) { table.tBodies[0].appendChild(r); });
      });
    });
  }

  // Export Modal Functions
  window.ppdbExportModal = {
    open: function(exportType, formId, submissionIds) {
      var modal = document.getElementById('ppdb-export-modal');
      if (!modal) return;
      
      // Set export type and parameters
      var exportTypeInput = modal.querySelector('input[name="ppdb_export_type"]');
      var formIdInput = modal.querySelector('input[name="ppdb_form_id"]');
      
      if (exportTypeInput) exportTypeInput.value = exportType;
      if (formIdInput && formId) formIdInput.value = formId;
      
      // Handle submission IDs for selected export
      if (exportType === 'selected' && submissionIds) {
        // Create hidden input for submission IDs if it doesn't exist
        var submissionIdsInput = modal.querySelector('input[name="submission_ids"]');
        if (!submissionIdsInput) {
          submissionIdsInput = document.createElement('input');
          submissionIdsInput.type = 'hidden';
          submissionIdsInput.name = 'submission_ids';
          modal.querySelector('#ppdb-export-form').appendChild(submissionIdsInput);
        }
        submissionIdsInput.value = submissionIds.join(',');
      }
      
      modal.style.display = 'block';
      document.body.style.overflow = 'hidden';
    },
    
    close: function() {
      var modal = document.getElementById('ppdb-export-modal');
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }
    },
    
    toggleCategory: function(checkbox) {
      var categoryDiv = checkbox.closest('.ppdb-field-group');
      var fieldCheckboxes = categoryDiv.querySelectorAll('.ppdb-fields-list input[type="checkbox"]');
      
      fieldCheckboxes.forEach(function(cb) {
        cb.checked = checkbox.checked;
      });
    },
    
    toggleField: function(checkbox) {
      var categoryDiv = checkbox.closest('.ppdb-field-group');
      var categoryCheckbox = categoryDiv.querySelector('.ppdb-category-toggle');
      var fieldCheckboxes = categoryDiv.querySelectorAll('.ppdb-fields-list input[type="checkbox"]');
      
      var checkedCount = Array.from(fieldCheckboxes).filter(function(cb) { return cb.checked; }).length;
      
      if (checkedCount === 0) {
        categoryCheckbox.checked = false;
        categoryCheckbox.indeterminate = false;
      } else if (checkedCount === fieldCheckboxes.length) {
        categoryCheckbox.checked = true;
        categoryCheckbox.indeterminate = false;
      } else {
        categoryCheckbox.checked = false;
        categoryCheckbox.indeterminate = true;
      }
    },
    
    selectAll: function() {
      var checkboxes = document.querySelectorAll('#ppdb-export-modal input[type="checkbox"]');
      checkboxes.forEach(function(cb) {
        cb.checked = true;
        cb.indeterminate = false;
      });
    },
    
    selectNone: function() {
      var checkboxes = document.querySelectorAll('#ppdb-export-modal input[type="checkbox"]');
      checkboxes.forEach(function(cb) {
        cb.checked = false;
        cb.indeterminate = false;
      });
    },
    
    validateAndSubmit: function() {
      var fieldCheckboxes = document.querySelectorAll('#ppdb-export-modal .ppdb-fields-list input[type="checkbox"]:checked');
      
      if (fieldCheckboxes.length === 0) {
        alert('Silakan pilih minimal satu field untuk diexport.');
        return false;
      }
      
      // Submit the form
      document.getElementById('ppdb-export-form').submit();
      return true;
    }
  };

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ppdb-table').forEach(makeSortable);
    
    // Modal event listeners
    var modal = document.getElementById('ppdb-export-modal');
    if (modal) {
      // Close modal when clicking outside
      modal.addEventListener('click', function(e) {
        if (e.target === modal) {
          ppdbExportModal.close();
        }
      });
      
      // Close button
      var closeBtn = modal.querySelector('.ppdb-modal-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', ppdbExportModal.close);
      }
      
      // Category toggle handlers
      modal.addEventListener('change', function(e) {
        if (e.target.classList.contains('ppdb-category-toggle')) {
          ppdbExportModal.toggleCategory(e.target);
        } else if (e.target.type === 'checkbox' && e.target.closest('.ppdb-fields-list')) {
          ppdbExportModal.toggleField(e.target);
        }
      });
      
      // Field item click handlers
      modal.addEventListener('click', function(e) {
        if (e.target.closest('.ppdb-field-item') && !e.target.matches('input[type="checkbox"]')) {
          var checkbox = e.target.closest('.ppdb-field-item').querySelector('input[type="checkbox"]');
          if (checkbox) {
            checkbox.checked = !checkbox.checked;
            ppdbExportModal.toggleField(checkbox);
          }
        }
      });
    }
    
    // Replace export button handlers
    document.addEventListener('click', function(e) {
      // Handle export current filter
      if (e.target.matches('input[name="export_current_filter"]')) {
        e.preventDefault();
        ppdbExportModal.open('current_filter');
      }
      
      // Handle export form registrants
      if (e.target.matches('input[name="export_registrants"]')) {
        e.preventDefault();
        var formId = e.target.getAttribute('data-form-id');
        ppdbExportModal.open('registrants', formId);
      }
      
      // Handle bulk export selected
      if (e.target.matches('input[value="export_selected"]')) {
        e.preventDefault();
        var form = e.target.closest('form');
        var checkedBoxes = form.querySelectorAll('input[name="submission_ids[]"]:checked');
        
        if (checkedBoxes.length === 0) {
          alert('Silakan pilih submission yang ingin diexport.');
          return;
        }
        
        var submissionIds = Array.from(checkedBoxes).map(function(cb) { return cb.value; });
        ppdbExportModal.open('selected', null, submissionIds);
      }
      
      // Handle modal buttons
      if (e.target.matches('.ppdb-select-all')) {
        e.preventDefault();
        ppdbExportModal.selectAll();
      }
      
      if (e.target.matches('.ppdb-select-none')) {
        e.preventDefault();
        ppdbExportModal.selectNone();
      }
      
      if (e.target.matches('.ppdb-modal-cancel')) {
        e.preventDefault();
        ppdbExportModal.close();
      }
      
      if (e.target.matches('.ppdb-export-submit')) {
        e.preventDefault();
        ppdbExportModal.validateAndSubmit();
      }
    });
  });
})();
