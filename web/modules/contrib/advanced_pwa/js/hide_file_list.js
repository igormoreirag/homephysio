(function (Drupal, once) {
  Drupal.behaviors.hideScreenshotFileList = {
    attach: function (context, settings) {
      // Hide the file list initially
      once('hide-file-list', '.form-type-advanced-pwa-screenshot .form-type-checkbox', context).forEach(function (element) {
        element.style.display = 'none';
      });

      // Hide the file list initially
      once('hide-remove-button', 'input[id^="edit-screenshots-remove-button"]', context).forEach(function (element) {
        element.style.display = 'none';
      });
    }
  };
})(Drupal, once);
