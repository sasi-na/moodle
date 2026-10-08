define(['jquery',
    'core/ajax',
    'core/templates',
    'core/notification'],
        function ($,
                ajax,
                templates,
                notification) {
            return  {
                /**
                 * Load the form!
                 *
                 * @method addform
                 */
                addmoreform: function () {
                    // Add a change event handler to the dropdown.


                    $('body').on('change', "#id_categoryid", function (e) {
                        e.preventDefault();
                        var catid = $(this).val();

                        $.ajax({
                            url: "ajax.php",
                            data: {catid: catid, action: 'getcourses'},
                            success: function (result) {
                                $('#id_courseid').html(result);
                                $('#id_quizid').html('<option value="0">Select Quiz</option>');
                            }
                        });
                    });

                    $('body').on('change', "#id_courseid", function (e) {
                        e.preventDefault();
                        var courseid = $(this).val();

                        $.ajax({
                            url: "ajax.php",
                            data: {courseid: courseid, action: 'getquizzes'},
                            success: function (result) {
                                $('#id_quizid').html(result);
                            }
                        });
                    });

                    var urlParams = new URLSearchParams(window.location.search);

                    if (urlParams.get('page') != '') {
                        $('.nav-link').removeClass('active');
                        $('#' + urlParams.get('page')).addClass('active');
                    }

                },

                pagetimetrack: function () {
                    var startTime;
                    var cmid;
                    if ($('#page-mod-scorm-player').length) {

                        var classNames = $('#page-mod-scorm-player').attr('class');
                        var classArray = classNames.split(' ');
                        $.each(classArray, function (index, value) {

                            if (value.includes('cmid')) {
                                var cmidclass = value.split('-');
                                cmid = cmidclass[1];
                            }
                        });
                    } else {
                        var urlParams = new URLSearchParams(window.location.search);
                        cmid = urlParams.get('id');
                    }
//                    window.onload = function () {
                    // Capture the start time
                    startTime = new Date().getTime();
                    console.log('start:', startTime);
//                    }

                    window.onbeforeunload = function () {
                        // Capture the end time and calculate the time spent
                        let endTime = new Date().getTime();
                        let timeSpent = endTime - startTime; // Time spent in milliseconds
                        console.log('end:', endTime);
                        console.log('spent:', timeSpent);
                        // Send the time spent to the server
                        navigator.sendBeacon(M.cfg.wwwroot + '/local/systemreports/timelog.php', JSON.stringify({timeSpent, cmid}));
                    }
                }

            };

        });
