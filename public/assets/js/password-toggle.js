// Adds a show/hide eye button to every password field. Without JavaScript the fields work as normal.
(function () {
  'use strict';
  function setVisible(input, button, visible) {
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
    button.title = visible ? 'Hide password' : 'Show password';
    button.querySelector('i').className = 'bi ' + (visible ? 'bi-eye-slash' : 'bi-eye');
  }
  document.querySelectorAll('input[type=password]').forEach(function (input) {
    var wrap = document.createElement('div');
    wrap.className = 'pw-wrap';
    input.parentNode.insertBefore(wrap, input);
    wrap.appendChild(input);
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'pw-toggle';
    button.innerHTML = '<i aria-hidden="true"></i>';
    wrap.appendChild(button);
    setVisible(input, button, false);
    button.addEventListener('click', function () {
      setVisible(input, button, input.type === 'password');
      input.focus();
    });
    // Hides it again on submit, so the browser never treats it as ordinary saved text.
    if (input.form) {
      input.form.addEventListener('submit', function () { setVisible(input, button, false); });
    }
  });
})();
