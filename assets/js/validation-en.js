/**
 * Pesan validasi form selalu bahasa Inggris.
 * Bawaan browser mengikuti bahasa perangkat pengunjung (mis. "Isi kolom ini"), jadi pesannya diganti di sini.
 */
(function () {
  'use strict';

  function messageFor(field) {
    var v = field.validity;
    if (v.valueMissing) {
      if (field.type === 'checkbox') return 'Please tick this box to continue.';
      if (field.type === 'radio' || field.tagName === 'SELECT') return 'Please select an option.';
      if (field.type === 'file') return 'Please choose a file.';
      return 'Please fill out this field.';
    }
    if (v.typeMismatch) {
      if (field.type === 'email') return 'Please enter a valid email address.';
      if (field.type === 'url') return 'Please enter a valid URL.';
      return 'Please enter a valid value.';
    }
    if (v.tooShort) return 'Please use at least ' + field.minLength + ' characters.';
    if (v.tooLong) return 'Please use no more than ' + field.maxLength + ' characters.';
    if (v.rangeUnderflow) return 'Value must be ' + field.min + ' or more.';
    if (v.rangeOverflow) return 'Value must be ' + field.max + ' or less.';
    if (v.stepMismatch) return 'Please enter a valid value.';
    if (v.patternMismatch) return field.title || 'Please match the requested format.';
    if (v.badInput) return 'Please enter a number.';
    return '';
  }

  // Tangkap di fase capture: event "invalid" tidak bubble.
  document.addEventListener('invalid', function (e) {
    var field = e.target;
    if (!field || typeof field.setCustomValidity !== 'function') return;
    field.setCustomValidity('');
    if (!field.validity.valid) field.setCustomValidity(messageFor(field));
  }, true);

  // Begitu pengunjung mengubah isian, hapus pesan supaya validasi dicek ulang dari awal.
  function reset(e) {
    var field = e.target;
    if (field && typeof field.setCustomValidity === 'function') field.setCustomValidity('');
  }
  document.addEventListener('input', reset, true);
  document.addEventListener('change', reset, true);
})();
