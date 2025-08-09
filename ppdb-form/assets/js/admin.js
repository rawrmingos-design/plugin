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

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ppdb-table').forEach(makeSortable);
  });
})();
