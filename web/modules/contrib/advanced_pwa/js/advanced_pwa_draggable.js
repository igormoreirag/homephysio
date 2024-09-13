(function ($, Drupal, once) {
  Drupal.behaviors.advancedPwaDraggable = {
    attach: function (context, settings) {

      // Ensure the tabledrag behavior is attached
      function attachTableDrag(context) {
        if (typeof Drupal.tableDrag !== 'undefined') {
          $(context).find('#screenshots-table-wrapper').each(function () {
            Drupal.behaviors.tableDrag.attach(context, settings);
          });
        } else {
          console.log('TableDrag is not available');
        }
      }

      // Wait for the document to be ready
      $(document).ready(function () {
        attachTableDrag(context);
      });

      // Apply behaviors only once
      once('advancedPwaDraggable', function () {
        attachTableDrag(document);
      });
    }
  };
})(jQuery, Drupal, once);
