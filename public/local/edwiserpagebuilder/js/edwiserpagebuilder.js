define("local_edwiserpagebuilder/edwiserpagebuilder", [
  "local_edwiserpagebuilder/jquery",
  "core/ajax",
  "core/url",
  "core/fragment",
  "core/templates",
], function (jQuery, ajax, url, Fragment, Templates) {
  var CONTEXTID = 1;
  return {
    init: function (contextid) {
      CONTEXTID = contextid;
      $(document).ready(function () {
        var fileinput_refrance = null;
        // if (window.location.hash.indexOf("no-right-panel") != -1) {
        $("#vvveb-builder").addClass("no-right-panel");
        $(".component-properties-tab").show();
        Vvveb.Components.componentPropertiesElement =
          "#left-panel .component-properties";
        // } else {
        //     $(".component-properties-tab").hide();
        // }

        var block_url = $("#epb_get_blk_url").val();
        console.log({ block_url });
        Vvveb.Builder.init(block_url, function () {
          // Initialize Navigator after Builder is ready
          Vvveb.TreeList.init();
          // Load components to populate the Navigator
          Vvveb.TreeList.loadComponents();
        });

        Vvveb.Gui.init();
        Vvveb.FileManager.init();
        Vvveb.SectionList.init();
        Vvveb.Breadcrumb.init();
        Vvveb.CssEditor.init();

        // Vvveb.FileManager.addPages(pages);
        Vvveb.Gui.toggleRightColumn(false);

        /**
         * Set block css and js to the editor textareas.
         */
        $(".epb-editor-iframe").on("load", function () {
          $("#edwiser-block-style-editor").val(
            css_beautify(
              $(".epb-editor-iframe").contents().find("#vvvebjs-styles").text()
            )
          );
          $("#edwiser-block-script-editor").val(
            js_beautify(
              $(".epb-editor-iframe").contents().find("#block-script").text()
            )
          );
          $("#iframe1")
            .contents()
            .find("body")
            .on("click", function (e) {
              var container = $("#add-section-box");
              if (
                $(container).is(":visible") &&
                !$(e.target).closest(container).length
              ) {
                container.hide();
              }
            });
        });

        /**
         * Block style edit textarea change event handler.
         */
        $("#edwiser-block-style-editor").on("change keyup paste", function () {
          clearTimeout(edwdelay);
          var edwdelay = setTimeout(function () {
            $(".epb-editor-iframe")
              .contents()
              .find("#vvvebjs-styles")
              .text($("#edwiser-block-style-editor").val());
          }, 1000);
        });

        $("#edwiser-block-style-editor").on("focusin", function () {
          $("#edwiser-block-style-editor").val(
            css_beautify(
              $(".epb-editor-iframe").contents().find("#vvvebjs-styles").text()
            )
          );
        });

        /**
         * Block script edit textarea change event handler.
         */
        var scriptdelay;
        $("#edwiser-block-script-editor").on("change keyup paste", function () {
          clearTimeout(scriptdelay);
          scriptdelay = setTimeout(function () {
            $(".epb-editor-iframe").contents().find("#block-script").remove();
            var jscript = document.createElement("script");
            jscript.setAttribute("type", "text/javascript");
            jscript.setAttribute("id", "block-script");
            $(jscript).append($("#edwiser-block-script-editor").val());
            $(".epb-editor-iframe").contents().find("head").append(jscript);
          }, 2000);
        });

        $("#edwiser-script-modal .modal-header .close").on(
          "click",
          function () {
            clearTimeout(scriptdelay);
          }
        );

        $("#edwiser-block-script-editor").on("focusin", function () {
          $("#edwiser-block-script-editor").val(
            js_beautify(
              $(".epb-editor-iframe").contents().find("#block-script").text()
            )
          );
        });

        /**
         * Function to return the block editor html with parsed shortcode content.
         * @returns HTMl content from the iframe.
         */
        function get_block_html() {
          var html = Vvveb.Builder.getHtml();
          var dummyDoc = new DOMParser().parseFromString(html, "text/html");
          $.each(
            $(dummyDoc.body).find("[data-edwiser-dynamic]"),
            function (index) {
              var shortcode = $(this).data("shortcode");
              switch (shortcode) {
                case "edwiser-form":
                  $(this)
                    .empty()
                    .append(`[${shortcode} id="${$(this).data("formid")}"]`);
                  break;
                case "edwiser-courses":
                  $(this)
                    .empty()
                    .append(
                      `[${shortcode} catid="${$(this).data(
                        "catid"
                      )}" layout="${$(this).data("layout")}"]`
                    );
                  break;
                case "edwiser-categories":
                  var shortc = `[${shortcode} layout="${$(this).data(
                    "layout"
                  )}" btnlabel="${$(this).data("btnlabel")}" count="${$(
                    this
                  ).data("count")}"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-cnc":
                  var shortc = `[edwiser-cnc layout="${$(this).data(
                    "layout"
                  )}" show="${$(this).data("show")}" catid="${$(this).data(
                    "catid"
                  )}" date="all"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-fc":
                  var shortc = `[edwiser-fc layout="${$(this).data(
                    "layout"
                  )}" show="${$(this).data("show")}" courseid="${$(this).data(
                    "courseid"
                  )}" date="all"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-addnotes":
                  var shortc = `[edwiser-remuiblck-addnotes layout="addnotes"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-courseanalytics":
                  var shortc = `[edwiser-remuiblck-courseanalytics layout="courseanalytics"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-courseprogress":
                  var shortc = `[edwiser-remuiblck-courseprogress layout="courseprogress"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-enrolledusers":
                  var shortc = `[edwiser-remuiblck-enrolledusers layout="enrolledusers"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-quizattempts":
                  var shortc = `[edwiser-remuiblck-quizattempts layout="quizattempts"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-recentforums":
                  var shortc = `[edwiser-remuiblck-recentforums layout="recentforums"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-todolist":
                  var shortc = `[edwiser-remuiblck-todolist layout="todolist"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-latestmembers":
                  var shortc = `[edwiser-remuiblck-latestmembers layout="latestmembers"]`;
                  $(this).empty().append(shortc);
                  break;
                case "edwiser-recentfeedback":
                  var shortc = `[edwiser-remuiblck-recentfeedback layout="recentfeedback"]`;
                  $(this).empty().append(shortc);
                  break;
                default:
                  break;
              }
            }
          );
          return dummyDoc.body.innerHTML;
        }

        /**
         * Page builder content save event handler.
         */
        $("#save-btn").on("click", function () {
          var blockhtml = get_block_html();
          var instanceid = $("#epb_get_blk_id").val();
          var blockcss = $(".epb-editor-iframe")
            .contents()
            .find("#vvvebjs-styles")
            .text();
          var blockjs = $("#edwiser-block-script-editor")?.val();

          console.log("instanceid", instanceid);
          console.log("blockjs", blockjs);

          var promises = ajax.call([
            {
              methodname: "block_edwiseradvancedblock_set_block_config",
              args: {
                instanceid: instanceid,
                blockhtml: blockhtml,
                blockcss: blockcss,
                blockjs: blockjs || "",
              },
            },
          ]);
          promises[0].done(function (responce) {
            if (responce.status) {
              show_toast_message("alert-success", responce.msg);
            } else {
              show_toast_message("alert-danger", responce.msg);
            }
          });
        });

        /**
         * Function to show the custome toast message.
         *
         * @param {string} css_class CSS class name string
         * @param {string} msg Message to display.
         */
        function show_toast_message(css_class, msg) {
          $("#epb-toast-message > .toast-msg").empty();
          $("#epb-toast-message > .toast-msg").append(`${msg}`);
          $("#epb-toast-message").addClass(css_class).removeClass("d-none");
          setTimeout(function () {
            $("#epb-toast-message").removeClass(css_class);
            $("#epb-toast-message").removeClass("d-flex").addClass("d-none");
          }, 5000);
        }

        $("#epb-toast-message > .close").on("click", function () {
          $("#epb-toast-message").removeClass("d-flex").addClass("d-none");
        });

        /**
         * File peker dialog image list lazy loading implimentation start.
         */
        $("#epb_media_list_wrap").on("scroll", function () {
          if (
            $(this).scrollTop() + $(this).innerHeight() >=
            $(this)[0].scrollHeight - 1
          ) {
            if ($("#file_list_fromlimit").val() > 0) {
              load_media_files();
            }
          }
        });

        /**
         * Function to load the images in file piker dialog.
         */
        function load_media_files() {
          $("#file_list_fromlimit").attr("data-loadmore", false);
          var limit = $("#file_list_fromlimit").val();
          var promises = ajax.call([
            {
              methodname: "local_edwiserpagebuilder_get_media_list",
              args: {
                limitfrom: limit,
                offset: 70,
              },
            },
          ]);
          promises[0].done(function (responce) {
            if (responce.media) {
              var limitto = false;
              var medias = responce.media;
              var limitto = medias.length > 0 ? responce.limitto : -1;

              if (limitto > 0) {
                $("#epb_media_list_empty").hide();
              } else {
                $("#epb_media_list_empty").show();
              }
              for (media in medias) {
                var file = medias[media];
                var fileurl = file["file_path"];
                if ($(`#fid-${file.id}`).length === 0) {
                  $("#epb_media_list_wrap")
                    .append(
                      `<div class='epb-media-item' data-is_author='${file.is_author}' data-time='${file.time_created}' data-size='${file.size}' data-dimension='${file.dimension}' data-name='${file.file_name}' data-path='${fileurl}' data-id='${file.file_id}' id='fid-${file.id}'><img class='img-thumbnail epb-media-img' src='${fileurl}?preview=thumb'/></div>`
                    )
                    .fadeIn("slow");
                }
              }
              $("#file_list_fromlimit").val(limitto);
            } else {
              show_toast_message("alert-danger", responce.msg);
            }
          });
        }

        /**
         * Opens file Piker dialog on select image button click on page builder.
         */
        $("#vvveb-builder").on("click", ".edwiser-select-file", function () {
          $("#edwiser-page-builder-fp").trigger("focus");
          fileinput_refrance = $(this).parent().find(".epbe-file-url-in");
          $("#epb_media_list_wrap").empty();
          $("#file_list_fromlimit").val(0);
          $('[href="#edwiser-tab-file-upload"]').removeClass("active");
          $('[href="#edwiser-tab-file-selector"]').addClass("active");
          $("#edwiser-tab-file-upload").removeClass("active show");
          $("#edwiser-tab-file-selector").addClass("active show");
          load_media_files();
        });

        /**
         * Click event handler for the media  item selected in popup.
         */
        $(document).on("click", ".epb-media-item", function () {
          var name = $(this).data("name");
          var fileid = $(this).data("id");
          var objdate = new Date(0);
          objdate.setUTCSeconds($(this).data("time"));
          $(".epb-media-item").removeClass(
            "border-primary epbm-selected-mfile"
          );
          $(this).addClass("epbm-selected-mfile border-primary ");
          $("#epb-selected-file").attr(
            "src",
            $(this).data("path") + "?preview=thumb"
          );
          $("#epbmd-name").text(name);
          $("#epbmd-time").text(objdate.toDateString());
          $("#epbmd-size").text($(this).data("size"));
          $("#epbmd-dimensions").text($(this).data("dimension"));
          $("#epbfm-file-name").val(name);
          $("#epbfm-file-id").val(fileid);
          $("#epbfm-media-id").val($(this).attr("id"));

          $("#epbfm-file-path").val($(this).data("path"));

          $("#epbmd-btn-delete-file").show();
          if (!$(this).data("is_author")) {
            $("#epbmd-btn-delete-file").hide();
          }
          $("#media-details, #epb-btn-setfile").removeClass("d-none disabled");
        });

        /**
         * File select handler.
         */
        $("#epb-btn-setfile").on("click", function () {
          fileinput_refrance.val($("#epbfm-file-path").val());
          // Trigger native change event (not jQuery event) to work with addEventListener
          var nativeInput = fileinput_refrance[0]; // Get native DOM element
          var event = new Event('change', { bubbles: true });
          nativeInput.dispatchEvent(event);
          fileinput_refrance.select();
          fileinput_refrance = null;
          $("#media-details").addClass("d-none");
        });

        /**
         * Meida piker popup tab change event detector. To toggle the visibility of component.
         */
        $("#file-piker-tabs.nav-tabs a").on("click", function () {
          var tabid = $(this).attr("href"); // Active tab id.
          if ("#edwiser-tab-file-upload" === tabid) {
            Fragment.loadFragment(
              "local_edwiserpagebuilder",
              "upload_media_filepicker",
              CONTEXTID,
              []
            ).done(function (html, js) {
              Templates.replaceNode("#edwiser-tab-file-upload", html, js);
            });
            $("#epb-save-popup-media").removeClass("d-none");
            $("#epb-btn-setfile").addClass("d-none");
          } else {
            $("#epb-btn-setfile").removeClass("d-none");
            $("#epb-save-popup-media").addClass("d-none");
            $("#epb_media_list_wrap").empty();
            $("#file_list_fromlimit").val(0);
            load_media_files();
          }
        });

        /**
         * Save popu uploaded media files.
         */
        $("#edwiser-page-builder-fp").on(
          "click",
          "#epb-save-popup-media",
          function () {
            var itemid_value = $("#file_upload_item_id").val();
            var promises = ajax.call([
              {
                methodname: "local_edwiserpagebuilder_save_media_files",
                args: {
                  itemid: itemid_value,
                },
              },
            ]);
            promises[0].done(function (responce) {
              if (true === responce.status) {
                show_toast_message("alert-success", responce.msg);
                setTimeout(function () {
                  $('.nav-tabs a[href="#edwiser-tab-file-selector"]').tab(
                    "show"
                  );
                }, 2000);
              } else {
                show_toast_message("alert-danger", responce.msg);
              }
            });
          }
        );

        /**
         * Delete image from media galary.
         */
        $(document).on("click", "#epbmd-btn-delete-file", function () {
          var filename = $("#epbfm-file-name").val();
          var fileid = $("#epbfm-file-id").val();
          var eleid = `#${$("#epbfm-media-id").val()}`;
          $("#edwiser-page-builder-fp").css("cursore", "progress");
          var promises = ajax.call([
            {
              methodname: "local_edwiserpagebuilder_delete_media_file",
              args: {
                file_name: filename,
                file_id: fileid,
              },
            },
          ]);
          promises[0]
            .done(function (responce) {
              if (responce.status) {
                $(eleid).remove().fadeOut("slow");
                show_toast_message("alert-success", responce.msg);
                $("#media-details").addClass("d-none");
              } else {
                show_toast_message("alert-danger", responce.msg);
              }
              $("#edwiser-page-builder-fp").css("cursore", "auto");
            })
            .then(
              setTimeout(function () {
                $("#epbfm-deletefile")
                  .text("")
                  .addClass("d-none")
                  .removeClass("alert-danger alert-success");
              }, 3000)
            );
        });
        $(
          "#toggle-left-column-btn,  #toggle-right-column-btn, #mobile-view,#tablet-view,#desktop-view"
        ).click(function () {
          $(this).toggleClass("active");
        });
        $("#mobile-view,#tablet-view,#desktop-view").click(function () {
          $("#mobile-view, #tablet-view, #desktop-view").removeClass("active");
          $(this).addClass("active");
        });

        $(document).on("keyup", ".edwcustomdropdowncustomsearch", function () {
          var filter = $(this).val().toUpperCase();
          var selector = $(this).attr("data-searchtag");
          var texttag = $(this).attr("data-texttag");
          $(selector).each(function () {
            var txtValue = $(this).find(texttag).text();
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
              $(this).show();
            } else {
              $(this).hide();
            }
          });
        });

        // Handle dropdown toggle for Boost theme compatibility
        $(document).on("click", "#edwcustomdropdownmenutooglebtn, .dropdown[id*='edwcustomdropdown'] .dropdown-toggle", function (e) {
          e.preventDefault();
          e.stopPropagation();
          var $toggle = $(this);
          var $dropdown = $toggle.closest('.dropdown');
          var $menu = $dropdown.find('.dropdown-menu');
          var isOpen = $dropdown.hasClass('show');

          // Close all other dropdowns in the properties panel
          $('#left-panel .dropdown.show').not($dropdown).removeClass('show').find('.dropdown-menu').removeClass('show');
          $('#left-panel .dropdown-toggle').not($toggle).attr('aria-expanded', 'false');

          // Toggle current dropdown
          if (isOpen) {
            $dropdown.removeClass('show');
            $menu.removeClass('show');
            $toggle.attr('aria-expanded', 'false');
          } else {
            $dropdown.addClass('show');
            $menu.addClass('show');
            $toggle.attr('aria-expanded', 'true');
          }
        });

        // Close dropdown when clicking outside (use namespace to avoid conflicts)
        $(document).on("click.edwcustomdropdown", function (e) {
          if (!$(e.target).closest('#left-panel .dropdown').length) {
            $('#left-panel .dropdown.show').removeClass('show').find('.dropdown-menu').removeClass('show');
            $('#left-panel .dropdown-toggle').attr('aria-expanded', 'false');
          }
        });
        // Dirty fix to make upload file input clickable when modal opens
      $(document).on("mouseup", "input[name='repo_upload_file']", function(e){
        if($(e.target).attr("name") == "repo_upload_file"){
          return false;
        }
      }).mousedown(function(e){
        if($(e.target).attr("name") == "repo_upload_file"){
          return false;
        }
      });
      });
    },
  };
});
