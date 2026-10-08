define(['jquery', 'core/modal_factory', 'core/modal_events'], function($, ModalFactory, ModalEvents) {

    let modal;

    return {
        init: function() {

            $('.exam-btn').on('click', function() {
                let examCode = $(this).data('exam');

                // Create modal
                ModalFactory.create({
                    type: ModalFactory.types.DEFAULT,
                    title: 'Exam Details',
                    body: '<div id="exam-modal-body">Loading...</div>',
                    large: true
                }).then(function(m) {
                    modal = m;

                    modal.show();

                    // Load data via AJAX
                    $.ajax({
                        url: M.cfg.wwwroot + '/blocks/examdashboard/ajax.php',
                        method: 'POST',
                        data: { exam_code: examCode },
                        success: function(res) {
                            $('#exam-modal-body').html(res);
                        }
                    });

                    // Destroy on close (cleanup)
                    modal.getRoot().on(ModalEvents.hidden, function() {
                        modal.destroy();
                    });
                });
            });
        }
    };
});
