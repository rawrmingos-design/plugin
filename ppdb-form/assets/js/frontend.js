(function () {
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.ppdb-form label').forEach(function (l) {
      var input = l.querySelector('input,textarea,select');
      if (input && input.required) {
        var span = document.createElement('span');
        span.textContent = ' *';
        span.style.color = '#ef4444';
        l.firstChild && l.insertBefore(span, l.firstChild.nextSibling);
      }
    });
  });
})();
