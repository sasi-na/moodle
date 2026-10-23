// This file is part of VPL for Moodle - http://vpl.dis.ulpgc.es/
//
// VPL for Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// VPL for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with VPL for Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * IDE Control
 *
 * @copyright 2017 Juan Carlos Rodríguez-del-Pino
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author Juan Carlos Rodríguez-del-Pino <jcrodriguez@dis.ulpgc.es>
 */

/* globals openpopup */

define(
    [
        'jquery',
        'jqueryui',
        'core/url',
        'mod_vpl/vplutil',
        'mod_vpl/vplui',
        'mod_vpl/vplidefile',
        'mod_vpl/vplidebutton',
        'mod_vpl/vplterminal',
        'mod_vpl/vplvnc',
    ],
    function ($, jqui, coreURL, VPLUtil, VPLUI, VPLFile, VPLIDEButtons, VPLTerminal, VPLVNCClient) {
        if (typeof VPLIDE !== 'undefined') {
            return VPLIDE;
        }
        var vplIdeInstance;
        var VPLIDE = function (rootId, options) {
            var self = this;
            var fileManager;
            var adjustTabsTitles;
            var autoResizeTab;
            var showErrorMessage;
            var updateMenu;
            var executionActions;
            var minNumberOfFiles = options.minfiles || 0;
            var maxNumberOfFiles = options.maxfiles || 0;
            var restrictedEdit = options.restrictededitor || options.example;
            var readOnly = options.example;
            var readOnlyFiles = options.readOnlyFiles;
            var isTeacher = options.isTeacher;
            var fullScreen = false;
            var scrollBarWidth = VPLUI.scrollBarWidth();
            var str = VPLUtil.str;
            // Track test case results for CA mode submission validation.
            var passedTestCases = 0;
            var totalTestCases = 0;
            var lastEvaluationText = '';
            var activityMode = options.activity_mode !== undefined ? options.activity_mode : 0;
            var rootObj = $('#' + rootId);
            $("head").append('<meta name="viewport" content="initial-scale=1">')
                .append('<meta name="viewport" width="device-width">')
                .append('<link rel="stylesheet" href="' + coreURL.relativeUrl('/mod/vpl/editor/VPLIDE.css') + '"/>');
            if (typeof rootObj != 'object') {
                throw new Error("VPL: constructor tag_id not found");
            }
            var optionsToCheck = {
                'new': true,
                'rename': true,
                'delete': true,
                'save': true,
                'run': true,
                'edit': true,
                'debug': true,
                'evaluate': true,
                'import': true,
                'resetfiles': true,
                'sort': true,
                'multidelete': true,
                'showparentfiles': true,
                'acetheme': true,
                'console': true,
                'comments': true,
                'aihints': true,
                'aigrading': true,
                'generatetestcases': true
            };
            if ((typeof options.loadajaxurl) == 'undefined') {
                options.loadajaxurl = options.ajaxurl;
            }
            (function () {
                var activateModification = (minNumberOfFiles < maxNumberOfFiles);
                options.new = activateModification;
                options.rename = activateModification;
                options.delete = activateModification;
                options.comments = options.comments && !options.example;
                options.acetheme = true;
                options.aihints = true;
                options.aigrading = true;
            })();
            options.sort = (maxNumberOfFiles - minNumberOfFiles >= 2);
            options.multidelete = options.sort;
            options.import = !restrictedEdit;
            var isOptionAllowed = function (op) {
                if (!optionsToCheck[op]) {
                    return true;
                }
                return options[op];
            };
            options.console = isOptionAllowed('run') || isOptionAllowed('debug');
            if ((typeof options.fontSize) == 'undefined') {
                options.fontSize = 12;
            }
            options.fontSize = parseInt(options.fontSize);

            /**
             * Parse evaluation text to count passed and total test cases.
             * @param {string} evalText The evaluation result text.
             */
            function parseTestCaseResults(evalText) {
                if (!evalText || typeof evalText !== 'string') {
                    passedTestCases = 0;
                    totalTestCases = 0;
                    return;
                }

                lastEvaluationText = evalText;
                passedTestCases = 0;
                totalTestCases = 0;

                // Parse the evaluation text line by line
                var lines = evalText.split(/\r\n|\n|\r/);
                var inTestCase = false;
                var currentTestPassed = false;

                for (var i = 0; i < lines.length; i++) {
                    var line = lines[i].trim();

                    // Check if line starts with '-' (test case title)
                    if (line.match(/^-/)) {
                        // If we were tracking a test case, finalize it
                        if (inTestCase && currentTestPassed) {
                            passedTestCases++;
                        }

                        // Start tracking new test case
                        totalTestCases++;
                        inTestCase = true;
                        currentTestPassed = false;

                        // Check if title contains grade indication (positive grade = passed)
                        var gradeMatch = line.match(/\(([+-]?[\d.]+)\)\s*$/);
                        if (gradeMatch) {
                            var grade = parseFloat(gradeMatch[1]);
                            if (grade > 0) {
                                currentTestPassed = true;
                            }
                        }
                    }
                    // Look for common pass indicators in test output
                    else if (inTestCase) {
                        if (line.match(/pass/i) || line.match(/success/i) || line.match(/correct/i) ||
                            line.match(/^>/)) {
                            // If we see output lines (starting with >), assume test ran
                            if (line.match(/^>/)) {
                                // Don't override if already marked as passed
                                if (!currentTestPassed) {
                                    currentTestPassed = true; // Assume passed if we see output
                                }
                            }
                        }
                        // Check for fail indicators
                        if (line.match(/fail/i) || line.match(/error/i) || line.match(/incorrect/i)) {
                            currentTestPassed = false;
                        }
                    }
                }

                // Finalize last test case if any
                if (inTestCase && currentTestPassed) {
                    passedTestCases++;
                }

                VPLUtil.log('Test cases parsed - Passed: ' + passedTestCases + ' / Total: ' + totalTestCases);
            }

            /**
             * Handler for dragover event.
             * @param {object} e event.
             */
            function dragoverHandler(e) {
                if (restrictedEdit) {
                    e.originalEvent.dataTransfer.dropEffect = 'none';
                } else {
                    e.originalEvent.dataTransfer.dropEffect = 'copy';
                }
                e.preventDefault();
            }
            /**
             * Handler for drop event.
             * @param {object} e event.
             * @returns {boolean}
             */
            function dropHandler(e) {
                if (restrictedEdit) { // No drop allowed.
                    e.stopImmediatePropagation();
                    return false;
                }
                var droppedFiles = [];
                // Function that lists all files and subfiles of given entry into droppedFiles.
                var listDroppedFiles = function (entry, path = '') {
                    return new Promise(function (resolve) {
                        if (entry.isFile) {
                            // Current entry is a file : add it to the list.
                            entry.file(function (file) {
                                // Change its name s.t. it preserves directories structure.
                                var fullName = path + file.name;
                                Object.defineProperty(file, "name", {
                                    get: function () {
                                        return fullName;
                                    }
                                });
                                droppedFiles.push(file);
                                resolve();
                            });
                        } else if (entry.isDirectory) {
                            // Current entry is a directory : process its content.
                            var dirReader = entry.createReader();
                            dirReader.readEntries(function (entries) {
                                var dirPromises = [];
                                for (var i = 0; i < entries.length; i++) {
                                    dirPromises.push(listDroppedFiles(entries[i], path + entry.name + "/"));
                                }
                                Promise.all(dirPromises).then(resolve).catch(function (err) {
                                    VPLUtil.log("Error reading directory entries: " + err);
                                });
                            });
                        } else {
                            // This is neither a directory nor a file : ignore it.
                            resolve();
                        }
                    });
                };
                var dt = e.originalEvent.dataTransfer;

                // List every element of the drop event.
                var promises = [];
                for (var i = 0; i < dt.items.length; i++) {
                    var entry = dt.items[i].webkitGetAsEntry();
                    if (!entry) { // Used if testing with Behat
                        const file = dt.items[i].getAsFile();
                        if (file) {
                            // Create a fake entry to handle it like a file.
                            entry = {
                                isFile: true,
                                isDirectory: false,
                                file: function (callback) {
                                    callback(file);
                                }
                            };
                            promises.push(listDroppedFiles(entry));
                        }
                    } else if (entry.isFile || entry.isDirectory) {
                        promises.push(listDroppedFiles(entry));
                    }
                }

                // Drop files.
                if (dt.files.length > 0) {
                    Promise.all(promises)
                        .then(function () {
                            VPLUI.readSelectedFiles(droppedFiles, function (file) {
                                return fileManager.addFile(file, true, updateMenu, showErrorMessage);
                            },
                                function () {
                                    fileManager.fileListVisibleIfNeeded();
                                });
                            return;
                        })
                        .catch(function (err) {
                            VPLUtil.log("Error processing dropped files: " + err);
                        });

                    e.stopImmediatePropagation();
                    return false;
                }
                return false;
            }
            rootObj.on('drop', dropHandler);
            rootObj.on('dragover', dragoverHandler);
            /**
             * Handler for paste limited by restrictedEdit var.
             * @param {object} e event.
             * @returns {boolean}
             */
            function restrictedPaste(e) {
                if (restrictedEdit) {
                    e.stopPropagation();
                    return false;
                }
                return true;
            }
            // Init editor vars.
            var menu = $('#vpl_menu');
            var menuButtons = new VPLIDEButtons(menu, isOptionAllowed);
            var tr = $('#vpl_tr');
            var fileListContainer = $('#vpl_filelist');
            var fileList = $('#vpl_filelist_header');
            var fileListContent = $('#vpl_filelist_content');
            var tabsUl = $('#vpl_tabs_ul');
            var tabs = $('#vpl_tabs');
            var resultContainer = $('#vpl_results');
            var result = $('#vpl_results_accordion');
            var compilationContainer = $('#vpl_compilation');
            var compilationAccordion = $('#vpl_compilation_accordion');
            var renameDiretoryAction = VPLUtil.doNothing;
            fileListContainer.vplMinWidth = 80;
            resultContainer.vplMinWidth = 100;

            /**
             * Set up resize functionality for the results panel
             */
            function setupResultsResize() {
                var isResizing = false;
                var parentContainer = $('#vpl_tr');

                // Debug: Check if resize handles exist
                var horizontalHandle = $('#vpl_horizontal_resize');
                var verticalHandle = $('#vpl_vertical_resize');

                if (horizontalHandle.length === 0) {
                    VPLUtil.log('Horizontal resize handle not found');
                    return;
                }
                if (verticalHandle.length === 0) {
                    VPLUtil.log('Vertical resize handle not found');
                    return;
                }

                VPLUtil.log('Resize handles found, setting up events');

                /**
                 * Handle resize updates
                 * @param {number} newWidth The new width value
                 */
                function handleResize(newWidth) {
                    if (!isResizing) {
                        isResizing = true;
                        requestAnimationFrame(function () {
                            // Update the CSS custom property to adjust layout
                            parentContainer.css('--results-width', newWidth + 'px');
                            // Update horizontal resize handle position
                            $('#vpl_horizontal_resize').css('left', newWidth + 'px');
                            // Trigger resize for any open editors
                            setTimeout(function () {
                                fileManager.currentFile('adjustSize');
                            }, 50);
                            isResizing = false;
                        });
                    }
                }

                /**
                 * Handle compilation panel resize
                 * @param {number} newHeight The new height value
                 */
                function handleCompilationResize(newHeight) {
                    parentContainer.css('--compilation-height', newHeight + 'px');
                    setTimeout(function () {
                        fileManager.currentFile('adjustSize');
                    }, 50);
                }

                // Custom horizontal resize handle
                var isHorizontalResizing = false;
                var startX = 0;
                var startWidth = 0;

                horizontalHandle.on('mousedown', function (e) {
                    e.preventDefault();
                    isHorizontalResizing = true;
                    startX = e.clientX;
                    startWidth = resultContainer.width();

                    $(document).on('mousemove.resize', function (e) {
                        if (isHorizontalResizing) {
                            var newWidth = startWidth + (e.clientX - startX);
                            newWidth = Math.max(250, Math.min(newWidth,
                                parentContainer.width() * 0.6));
                            resultContainer.width(newWidth);
                            handleResize(newWidth);
                        }
                    });

                    $(document).on('mouseup.resize', function () {
                        isHorizontalResizing = false;
                        $(document).off('.resize');
                    });
                });

                // Custom vertical resize handle
                var verticalHandle = $('#vpl_vertical_resize');
                var isVerticalResizing = false;
                var startY = 0;
                var startHeight = 0;

                verticalHandle.on('mousedown', function (e) {
                    e.preventDefault();
                    isVerticalResizing = true;
                    startY = e.clientY;
                    startHeight = compilationContainer.height();

                    $(document).on('mousemove.vresize', function (e) {
                        if (isVerticalResizing) {
                            var deltaY = e.clientY - startY;
                            var newHeight = Math.max(100, startHeight - deltaY);
                            var containerHeight = $('.vpl_editor_compilation_container').height();
                            newHeight = Math.min(newHeight, containerHeight * 0.7);

                            compilationContainer.height(newHeight);
                            handleCompilationResize(newHeight);
                        }
                    });

                    $(document).on('mouseup.vresize', function () {
                        isVerticalResizing = false;
                        $(document).off('.vresize');
                    });
                });

                // Use ResizeObserver as fallback to watch for changes
                if (window.ResizeObserver) {
                    var resizeObserver = new ResizeObserver(function (entries) {
                        var entry = entries[0];
                        if (entry && !isHorizontalResizing) {
                            handleResize(entry.contentRect.width);
                        }
                    });
                    resizeObserver.observe(resultContainer[0]);

                    var compilationResizeObserver = new ResizeObserver(function (entries) {
                        var entry = entries[0];
                        if (entry && !isVerticalResizing) {
                            handleCompilationResize(entry.contentRect.height);
                        }
                    });
                    compilationResizeObserver.observe(compilationContainer[0]);
                }
            }

            // Initialize the resize functionality after DOM is ready
            setTimeout(function () {
                setupResultsResize();

                // Auto-enable fullscreen mode after a short delay
                setTimeout(function () {
                    if (!fullScreen) {
                        $('body').addClass('vpl_body_fullscreen').scrollTop(0);
                        rootObj.addClass('vpl_ide_root_fullscreen');
                        menuButtons.setText('fullscreen', 'regularscreen');
                        if (options.username) {
                            $('#vpl_ide_user').show();
                        }
                        fullScreen = true;
                        if ($('.ui-dialog:visible').length === 0) {
                            focusCurrentFile();
                        }
                        setTimeout(autoResizeTab, 10);
                        // Ensure editor gets focus after load
                        setTimeout(function () {
                            if ($('.ui-dialog:visible').length === 0) {
                                focusCurrentFile();
                            }
                        }, 100);
                    }
                }, 500); // Wait 500ms before auto-fullscreen
            }, 2000);
            fileListContainer.vplMinWidth = 80;
            resultContainer.vplMinWidth = 100;
            /**
             * Avoids selecting grade.
             * @param {object} event Unuse.
             * @param {object} ui UI origen.
             * @returns {boolean}
             */
            function avoidSelectGrade(event, ui) {
                if ("newHeader" in ui) {
                    if (ui.newHeader.hasClass('vpl_ide_accordion_t_grade')) {
                        return false;
                    }
                }
                return true;
            }
            /**
             * Constructor of FileManager objects
             */
            function FileManager() {
                var tabsUl = $('#vpl_tabs_ul');
                $('#vpl_tabs').tabs();
                var tabs = $('#vpl_tabs').tabs("widget");
                var files = [];
                var openFiles = [];
                var modified = true;
                var self = this;
                (function () {
                    var version;
                    self.setVersion = function (v) {
                        version = v;
                    };
                    self.getVersion = function () {
                        return version;
                    };
                })();
                this.updateFileList = function () {
                    self.generateFileList();
                };
                this.fileNameExists = function (name) {
                    var checkName = name.toLowerCase();
                    for (var i = 0; i < files.length; i++) {
                        if (files[i].getFileName().toLowerCase() == checkName) {
                            return i;
                        }
                    }
                    return -1;
                };
                /**
                 * Checks if name is included in current files names
                 * @param {string} name Name of file
                 * @returns {boolean} if found or not found
                 */
                function fileNameIncluded(name) {
                    var checkName = name.toLowerCase() + '/';
                    for (var i = 0; i < files.length; i++) {
                        var nameMod = files[i].getFileName().toLowerCase() + '/';
                        // Check for name as directory existent.
                        if (nameMod.indexOf(checkName) === 0 || checkName.indexOf(nameMod) === 0) {
                            return true;
                        }
                    }
                    return false;
                }
                /**
                 * Checks if changing file name results in two blovkly files
                 * @param {string} oldname The old file name
                 * @param {string} newname The new file name
                 * @returns {boolean} if results two two blovkly files
                 */
                function twoBlockly(oldname, newname) {
                    if (VPLUtil.isBlockly(oldname)) {
                        return false;
                    }
                    if (VPLUtil.isBlockly(newname)) {
                        for (var i = 0; i < files.length; i++) {
                            if (VPLUtil.isBlockly(files[i].getFileName())) {
                                return true;
                            }
                        }
                    }
                    return false;
                }
                this.restrictedPaste = restrictedPaste;
                this.dropHandler = dropHandler;
                this.dragoverHandler = dragoverHandler;
                this.readOnly = readOnly;
                this.readOnlyFiles = readOnlyFiles;
                this.restrictedEdit = restrictedEdit;
                this.adjustTabsTitles = adjustTabsTitles;
                this.minNumberOfFiles = minNumberOfFiles;
                this.scrollBarWidth = scrollBarWidth;
                var localClipboard = "";
                this.setClipboard = function (t) {
                    localClipboard = t;
                };
                this.getClipboard = function () {
                    return localClipboard;
                };
                this.getTabPos = function (fileId) {
                    for (var i = 0; i < openFiles.length; i++) {
                        if (openFiles[i].getId() == fileId) {
                            return i;
                        }
                    }
                    return openFiles.length;
                };
                this.getTheme = function () {
                    return options.theme;
                };
                this.setTheme = function (theme) {
                    options.theme = theme;
                    for (var i = 0; i < files.length; i++) {
                        files[i].setTheme(theme);
                    }
                };
                this.addTab = function (fid) {
                    var hlink = '<a href="#vpl_file' + fid + '"></a>';
                    tabsUl.append('<li id="vpl_tab_name' + fid + '">' + hlink + '</li>');
                    tabs.append('<div id="vpl_file' + fid + '" class="vpl_ide_file"></div>');

                    // Ensure the tab gets its filename displayed after creation
                    VPLUtil.delay('showFileNameAfterTab' + fid, function () {
                        var pos = self.getFilePosById(fid);
                        if (pos >= 0 && files[pos] && files[pos].showFileName) {
                            files[pos].showFileName();
                            // Set the data-filename attribute for the CSS ::before pseudo-element
                            var fileName = files[pos].getFileName();
                            $('#vpl_file' + fid).attr('data-filename', VPLUtil.getFileName(fileName));
                        }
                    }, 50);
                };
                this.removeTab = function (fid) {
                    tabsUl.find('#vpl_tab_name' + fid).remove();
                    tabs.find('#vpl_file' + fid).remove();
                };
                this.isReadOnly = function (fileName) {
                    return this.readOnly || this.readOnlyFiles.indexOf(fileName) != -1;
                };
                this.open = function (pos) {
                    var file;
                    if (typeof pos == 'object') {
                        file = pos;
                    } else {
                        file = files[pos];
                    }
                    if (file.isOpen()) {
                        return;
                    }
                    var fid = file.getId();
                    self.addTab(fid);
                    openFiles.push(file);
                    menuButtons.setGetkeys(file.open());
                    tabs.tabs('refresh');
                    adjustTabsTitles(false);
                    VPLUtil.delay('updateFileList', self.updateFileList);
                    VPLUtil.delay('updateMenu', updateMenu);
                };
                this.closeFile = function (file) {
                    if (!file.isOpen()) {
                        return;
                    }
                    var pos;
                    var fid = file.getId();
                    file.close();
                    VPLUI.hideIDEStatus();
                    self.removeTab(fid);
                    var ptab = self.getTabPos(fid);
                    openFiles.splice(ptab, 1);
                    tabs.tabs('refresh');
                    adjustTabsTitles(false);
                    self.fileListVisible(true);
                    VPLUtil.delay('updateFileList', self.updateFileList);
                    VPLUtil.delay('adjustTabsTitles', adjustTabsTitles, false);
                    if (openFiles.length == ptab) {
                        ptab--;
                    }
                    if (ptab >= 0 && openFiles.length > ptab) {
                        pos = self.getFilePosById(openFiles[ptab].getId());
                        self.gotoFile(pos, 'c');
                        return;
                    }
                };
                this.isClosed = function (pos) {
                    return !files[pos].isOpen();
                };
                this.fileListVisible = function (b) {
                    if (b === fileListContainer.vplVisible) {
                        return;
                    }
                    if (b) {
                        VPLUtil.delay('fileListVisible', function () {
                            fileListContainer.vplVisible = true;
                            self.updateFileList();
                            fileListContainer.show();
                            autoResizeTab();
                        });
                    } else {
                        VPLUtil.delay('fileListVisible', function () {
                            fileListContainer.vplVisible = false;
                            fileListContainer.hide();
                            autoResizeTab();
                        });
                    }
                };
                this.isFileListVisible = function () {
                    return fileListContainer.vplVisible;
                };
                this.fileListVisibleIfNeeded = function () {
                    if (this.isFileListVisible()) {
                        return;
                    }
                    for (var i = 0; i < files.length; i++) {
                        if (!files[i].isOpen()) {
                            this.fileListVisible(true);
                            return;
                        }
                    }
                };
                this.setFontSize = function (size) {
                    options.fontSize = size;
                    for (var i = 0; i < files.length; i++) {
                        files[i].setFontSize(size);
                    }
                    terminal.setFontSize(size);
                };
                this.getFontSize = function () {
                    return options.fontSize;
                };
                this.addFile = function (file, replace, ok, showError) {
                    if ((typeof file.name != 'string') || !VPLUtil.validPath(file.name)) {
                        showError(str('incorrect_file_name') + '\n(' + file.name + ')');
                        return false;
                    }
                    if (replace !== true) {
                        replace = false;
                    }
                    var pos = this.fileNameExists(file.name);
                    if (pos != -1) {
                        if (replace && !files[pos].isReadOnly()) {
                            // Clean file content - remove filename if it appears at the beginning
                            var cleanedContent = file.contents;
                            if (cleanedContent && typeof cleanedContent === 'string') {
                                var lines = cleanedContent.split('\n');
                                if (lines.length > 0) {
                                    var firstLine = lines[0].trim();
                                    var filename = VPLUtil.getFileName(file.name);

                                    // Check if first line is just the filename
                                    if (firstLine === filename ||
                                        firstLine === file.name ||
                                        firstLine === filename.replace(/\.[^/.]+$/, "")) {
                                        // Remove the first line if it's just the filename
                                        cleanedContent = lines.slice(1).join('\n');
                                    }
                                }
                            }

                            files[pos].setContent(cleanedContent);
                            self.setModified();
                            ok();
                            VPLUtil.delay('updateFileList', self.updateFileList);
                            return file;
                        } else {
                            showError(str('filenotadded', file.name));
                            return false;
                        }
                    }
                    if (fileNameIncluded(file.name) || twoBlockly('', file.name)) {
                        showError(str('filenotadded', file.name));
                        return false;
                    }
                    if (files.length >= maxNumberOfFiles) {
                        showError(str('maxfilesexceeded') + '\n(' + maxNumberOfFiles + ')');
                        return false;
                    }
                    var fid = VPLUtil.getUniqueId();

                    // Clean file content - remove filename if it appears at the beginning
                    var cleanedContent = file.contents;
                    if (cleanedContent && typeof cleanedContent === 'string') {
                        var lines = cleanedContent.split('\n');
                        if (lines.length > 0) {
                            var firstLine = lines[0].trim();
                            var filename = VPLUtil.getFileName(file.name);

                            // Check if first line is just the filename (without extension or with extension)
                            if (firstLine === filename ||
                                firstLine === file.name ||
                                firstLine === filename.replace(/\.[^/.]+$/, "")) {
                                // Remove the first line if it's just the filename
                                cleanedContent = lines.slice(1).join('\n');
                            }
                        }
                    }

                    var newfile = new VPLFile(fid, file.name, cleanedContent, this, vplIdeInstance);
                    if (file.encoding == 1) {
                        newfile.extendToBinary();
                    } else {
                        if (VPLUtil.isBlockly(file.name)) {
                            newfile.extendToBlockly();
                        } else {
                            newfile.extendToCodeEditor();
                        }
                    }
                    newfile.setFileName(file.name);
                    files.push(newfile);
                    self.setModified();
                    if (files.length > 5) {
                        self.fileListVisible(true);
                    }
                    ok();
                    return newfile;
                };
                this.renameFile = function (oldname, newname, showError) {
                    var pos = this.fileNameExists(oldname);
                    try {
                        if (pos == -1) {
                            throw new Error("Internal error: File name not found");
                        }
                        if (files[pos].getId() < this.minNumberOfFiles) {
                            throw new Error("Internal error: Renaming requested filename");
                        }
                        if (files[pos].getFileName() == newname) {
                            return true; // Equals name file.
                        }
                        if (!VPLUtil.validPath(newname) ||
                            fileNameIncluded(newname) ||
                            twoBlockly(oldname, newname)) {
                            throw str('incorrect_file_name');
                        }
                        if (VPLUtil.isBinary(oldname) && VPLUtil.fileExtension(oldname) != VPLUtil.fileExtension(newname)) {
                            throw str('incorrect_file_name');
                        }
                        if (VPLUtil.isBlockly(oldname) != VPLUtil.isBlockly(newname)) {
                            if (files[pos].getContent() > '') {
                                showMessage(str('delete_file_fq', oldname), {
                                    ok: function () {
                                        var file = {
                                            name: newname,
                                            contents: '',
                                            encoding: 0
                                        };
                                        fileManager.deleteFile(oldname, showError);
                                        var fileResult = fileManager.addFile(file, false, updateMenu, showErrorMessage);
                                        if (fileResult) {
                                            fileManager.gotoFileName(newname);
                                        }
                                    }
                                });
                            } else {
                                var file = {
                                    name: newname,
                                    contents: '',
                                    encoding: 0
                                };
                                fileManager.deleteFile(oldname, showError);
                                var fileResult = fileManager.addFile(file, false, updateMenu, showError);
                                if (fileResult) {
                                    fileManager.gotoFileName(newname);
                                }
                            }
                            return true;
                        }
                        files[pos].setFileName(newname);
                    } catch (e) {
                        showError(str('filenotrenamed', oldname) + '\n' + e);
                        return false;
                    }
                    self.setModified();
                    adjustTabsTitles(false);
                    VPLUtil.delay('updateFileList', self.updateFileList);
                    return true;
                };
                this.directoryExists = function (dirName) {
                    var checkName = dirName.toLowerCase() + '/';
                    for (var i = 0; i < files.length; i++) {
                        if (files[i].getFileName().toLowerCase().startsWith(checkName)) {
                            return true;
                        }
                    }
                    return false;
                };
                this.renameDirectory = function (oldName, newName, showError) {
                    if (oldName == newName) {
                        return false;
                    }
                    try {
                        if (!this.directoryExists(oldName)) {
                            throw new Error("Trying to rename a directory that doesn't exist: " + oldName);
                        }
                        if (!VPLUtil.validPath(newName + '/file.txt')) {
                            throw str('incorrect_directory_name');
                        }
                        // Prepare new names
                        var oldNameLength = oldName.length + 1;
                        var checkDirName = oldName.toLowerCase() + '/';
                        var newFileNames = [];
                        var i;
                        for (i = 0; i < files.length; i++) {
                            var fileName = files[i].getFileName();
                            if (fileName.toLowerCase().startsWith(checkDirName)) {
                                if (files[i].getId() < this.minNumberOfFiles) { // Renaming required filename
                                    throw str('incorrect_file_name');
                                }
                                newFileNames[i] = newName + '/' + fileName.substr(oldNameLength);
                            }
                        }
                        if (this.directoryExists(newName)) { // Checks if the merge is possible (no repeated names)
                            var oldNames = [];
                            for (i = 0; i < files.length; i++) {
                                oldNames[files[i].getFileName().toLowerCase()] = true;
                            }
                            for (i = 0; i < files.length; i++) {
                                if (newFileNames[i] && oldNames[newFileNames[i].toLowerCase()]) {
                                    throw str('incorrect_file_name');
                                }
                            }
                        }
                        // Set the new file names
                        for (i = 0; i < newFileNames.length; i++) {
                            if (newFileNames[i]) {
                                files[i].setFileName(newFileNames[i]);
                            }
                        }
                    } catch (e) {
                        showError(str('directory_not_renamed', oldName) + '\n' + e);
                        return false;
                    }
                    self.setModified();
                    adjustTabsTitles(false);
                    VPLUtil.delay('updateFileList', self.updateFileList);
                    return true;
                };
                this.deleteFile = function (name, showError) {
                    var pos = this.fileNameExists(name);
                    if (pos == -1) {
                        showError(str('filenotdeleted', name));
                        return false;
                    }
                    if (files[pos].getId() < minNumberOfFiles) {
                        showError(str('filenotdeleted', name));
                        return false;
                    }
                    this.setModified();
                    this.closeFile(files[pos]);
                    files.splice(pos, 1);
                    if (openFiles.length == 0) {
                        VPLUI.hideIDEStatus();
                    }
                    VPLUtil.delay('updateFileList', self.updateFileList);
                    return true;
                };
                this.currentFile = function () {
                    var id = tabs.tabs('option', 'active');
                    if (id in openFiles) {
                        var file = openFiles[id];
                        if (arguments.length === 0) {
                            return file;
                        }
                        var action = arguments[0];
                        if (typeof file[action] === 'function') {
                            var fun = file[action];
                            var args = Array.prototype.slice(arguments);
                            args.shift();
                            return fun.apply(file, args);
                        }
                    }
                    return false;
                };
                this.getCurrentFileName = function () {
                    var currentFileName = '';
                    var currentFile = fileManager.currentFile();
                    if (currentFile) {
                        currentFileName = currentFile.getFileName();
                    }
                    return currentFileName;
                };
                this.currentPos = function () {
                    return tabs.tabs('option', 'active');
                };
                this.getFileTab = function (id) {
                    for (var i = 0; i < openFiles.length; i++) {
                        if (openFiles[i].getId() == id) {
                            return i;
                        }
                    }
                    return -1;
                };
                this.getFilePosById = function (id) {
                    for (var i = 0; i < files.length; i++) {
                        if (files[i].getId() == id) {
                            return i;
                        }
                    }
                    return -1;
                };
                this.gotoFile = function (pos, l) {
                    var file = files[pos];
                    self.open(file);
                    tabs.tabs('option', 'active', self.getFileTab(file.getId()));
                    if (l !== 'c') {
                        file.gotoLine(parseInt(l, 10));
                    }
                    file.focus();
                };
                this.gotoFileLink = function (a) {
                    var tag = $(a);
                    var fname = tag.data('file');
                    var fpos = -1;
                    if (fname > '') {
                        fpos = this.fileNameExists(fname);
                    } else {
                        fpos = self.getFilePosById(tag.data('fileid'));
                    }
                    if (fpos >= 0) {
                        var line = tag.data('line');
                        if (typeof line == 'undefined') {
                            line = 'c';
                        }
                        self.gotoFile(fpos, line);
                        return true;
                    }
                    return false;
                };
                this.gotoFileName = function (fname, line) {
                    var fpos = this.fileNameExists(fname);
                    if (fpos >= 0) {
                        if (typeof line == 'undefined') {
                            line = 'c';
                        }
                        self.gotoFile(fpos, line);
                        return true;
                    }
                    return false;
                };
                this.getFilesToSave = function () {
                    var ret = [];
                    for (var i = 0; i < files.length; i++) {
                        var file = {};
                        file.name = files[i].getFileName();
                        file.contents = files[i].getContent();
                        file.encoding = files[i].isBinary() ? 1 : 0;
                        ret.push(file);
                    }
                    return ret;
                };
                this.resetModified = function () {
                    modified = false;
                    for (var i = 0; i < files.length; i++) {
                        files[i].resetModified();
                    }
                    VPLUtil.delay('updateMenu', updateMenu);
                    VPLUtil.delay('updateFileList', self.updateFileList);
                };
                this.setModified = function () {
                    modified = true;
                    VPLUtil.delay('updateFileList', self.updateFileList);
                    VPLUtil.delay('updateMenu', updateMenu);
                };
                this.isModified = function () {
                    return modified;
                };
                this.length = function () {
                    return files.length;
                };
                this.clearAnnotations = function () {
                    for (var i = 0; i < files.length; i++) {
                        files[i].clearAnnotations();
                    }
                };
                this.getFile = function (i) {
                    return files[i];
                };
                this.getFiles = function () {
                    return files;
                };
                this.getDirectoryStructure = function () {
                    var structure = {
                        isDir: true,
                        content: {},
                        path: '',
                    };
                    /**
                     * Adds a new file the structure of directories
                     * @param {int} i Index of file to add in the file array
                     */
                    function addFilePath(i) {
                        var file = files[i];
                        var fileName = file.getFileName();
                        var path = fileName.split("/");
                        var curdir = structure;
                        var pathdir = '';
                        for (var p = 0; p < path.length; p++) {
                            var part = path[p];
                            if (p == path.length - 1) { // File.
                                curdir.content[part] = {
                                    isDir: false,
                                    content: file,
                                    pos: i,
                                };
                            } else {
                                pathdir += part;
                                if (!curdir.content[part]) { // New dir.
                                    curdir.content[part] = {
                                        isDir: true,
                                        content: {},
                                        path: pathdir,
                                    };
                                }
                                // Descend Dir.
                                pathdir += '/';
                                curdir = curdir.content[part];
                            }
                        }
                    }
                    for (var i in files) {
                        if (files.hasOwnProperty(i)) {
                            addFilePath(i);
                        }
                    }
                    return structure;
                };
                this.generateFileList = function () {
                    if (!self.isFileListVisible()) {
                        return;
                    }
                    var dirIndent = '<span class="vpl_ide_dirindent"></span>';
                    /**
                     * Generates an array of string with the HTML code to represent the list of IDE files
                     * @param {Object} dir Current directory
                     * @param {int} indent Html code to indent subdirectories
                     * @param {Array} lines Output. Each line contains the HTML to represent an IDE file
                     */
                    function lister(dir, indent, lines) {
                        var name, fd, sname, attrs, dirline, file, path, line;
                        for (name in dir.content) {
                            if (dir.content.hasOwnProperty(name)) {
                                fd = dir.content[name];
                                if (fd.isDir) {
                                    var dirpath = VPLUtil.sanitizeText(fd.path);
                                    attrs = 'href="#" data-dirname="' + dirpath + '" ';
                                    sname = VPLUtil.sanitizeText(name);
                                    dirline = indent;
                                    dirline += VPLUI.iconFolder() + '<a ' + attrs + '>' + sname + '</a>';
                                    lines.push(dirline);
                                    lister(fd, indent + dirIndent, lines);
                                } else {
                                    file = fd.content;
                                    sname = VPLUtil.sanitizeText(name);
                                    path = VPLUtil.sanitizeText(file.getFileName());
                                    if (file.isOpen()) {
                                        sname = '<b>' + sname + '</b>';
                                    }
                                    attrs = 'href="#" data-fileid="' + file.getId() + '" title="' + path + '"';
                                    line = '<a ' + attrs + '>' + sname + '</a>';
                                    if (file.isModified()) {
                                        line = VPLUI.iconModified() + line;
                                    }
                                    if (file.isReadOnly()) {
                                        line = line + VPLUI.iconReadOnly();
                                    } else if (file.getId() < minNumberOfFiles) {
                                        line = line + VPLUI.iconRequired();
                                    }
                                    lines.push(indent + line);
                                }
                            }
                        }
                    }

                    var structure = self.getDirectoryStructure();
                    var lines = [];
                    var html = '';
                    lister(structure, '', lines);
                    for (var i = 0; i < lines.length; i++) {
                        html += lines[i] + '<br>';
                    }
                    fileListContent.html('<div>' + html + '</div>');
                };
                tabsUl.on('click', 'span.vpl_ide_closeicon', function () {
                    fileManager.closeFile(fileManager.currentFile());
                });
                tabsUl.on('dblclick', 'span.vpl_ide_closeicon', menuButtons.getAction('delete'));
                tabsUl.on('dblclick', 'a', menuButtons.getAction('rename'));
                fileListContent.on('dblclick', 'a[data-fileid]', menuButtons.getAction('rename'));
                fileListContent.on('dblclick', 'a[data-dirname]', renameDiretoryAction);
            }
            this.updateEvaluationNumber = function (res) {
                if (typeof res.nevaluations != 'undefined') {
                    var text = res.nevaluations;
                    if (typeof res.reductionbyevaluation != 'undefined'
                        && res.reductionbyevaluation > ''
                        && res.reductionbyevaluation != 0) {
                        if (res.freeevaluations != 0) {
                            text = text + '/' + res.freeevaluations;
                        }
                        text = text + ' -' + res.reductionbyevaluation;
                    }
                    menuButtons.setExtracontent('evaluate', text);
                }
            };
            this.lastResult = null;
            this.getTerminal = function () {
                return terminal;
            };

            // Store original description content
            var originalDescription = null;
            var isShowingHints = false;

            // Function to show AI hints in the description panel
            this.showAIHintsPopup = function (content, isError) {
                var descriptionPanel = $('.vpl_ide_accordion_c_description');
                if (descriptionPanel.length === 0) {
                    // If description panel doesn't exist, create it
                    var titleclass = 'vpl_ide_accordion_t_description';
                    var contentclass = 'vpl_ide_accordion_c_description';
                    if (result.find('.' + contentclass).length == 0) {
                        result.append('<div class="' + titleclass + '"></div>');
                        result.append('<div class="' + contentclass + '"></div>');
                    }
                    descriptionPanel = $('.vpl_ide_accordion_c_description');
                }

                // Store original content if not already stored
                if (originalDescription === null && !isShowingHints) {
                    originalDescription = descriptionPanel.html();
                }

                // Create header with restore button
                var headerContent = '<div style="display: flex; justify-content: space-between; align-items: center; ' +
                    'margin-bottom: 15px; padding: 10px; background: ' + (isError ? '#ffebee' : '#e3f2fd') + '; ' +
                    'border-radius: 5px; border-left: 4px solid ' + (isError ? '#d32f2f' : '#007cba') + ';">' +
                    '<h4 style="margin: 0; color: ' + (isError ? '#d32f2f' : '#007cba') + ';">' +
                    (isError ? '❌ AI Hints Error' : '💡 Coding Hints') + '</h4>' +
                    '<button id="vpl_restore_description" style="background: #f5f5f5; border: 1px solid #ccc; ' +
                    'border-radius: 3px; padding: 5px 10px; cursor: pointer; font-size: 12px; color: #666;" ' +
                    'title="Restore original description">📄 Show Description</button>' +
                    '</div>';

                // Update the description panel content
                descriptionPanel.html(headerContent + '<div style="padding: 10px;">' + content + '</div>');
                isShowingHints = true;

                // Add click handler for restore button
                $('#vpl_restore_description').on('click', function () {
                    self.restoreOriginalDescription();
                });

                // Ensure the description accordion is expanded
                var descriptionAccordion = descriptionPanel.closest('.ui-accordion');
                if (descriptionAccordion.length > 0) {
                    var descriptionHeader = descriptionPanel.prev('.vpl_ide_accordion_t_description');
                    if (descriptionHeader.length > 0) {
                        var index = descriptionHeader.index() / 2; // Each accordion item has header + content
                        descriptionAccordion.accordion('option', 'active', index);
                    }
                }
            };

            // Function to restore original description
            this.restoreOriginalDescription = function () {
                if (originalDescription !== null) {
                    var descriptionPanel = $('.vpl_ide_accordion_c_description');
                    descriptionPanel.html(originalDescription);
                    isShowingHints = false;
                    // Don't reset originalDescription to null, keep it for future use
                }
            };

            // Function to get AI grading
            this.getAIGrading = function () {
                // Use hardcoded API key - same as hints
                var apiKey = 'AIzaSyBWmhP_9nS9jWwv3bWP0tFx6A35bkkedDI';

                // Get the current code from all files
                var allCode = '';
                var files = fileManager.getFilesToSave();
                for (var i = 0; i < files.length; i++) {
                    if (!files[i].name.endsWith('.class') && files[i].encoding === 0) { // Skip binary files
                        allCode += '// File: ' + files[i].name + '\n';
                        allCode += files[i].contents + '\n\n';
                    }
                }

                // Get compilation results and test case results
                var compilationResults = '';
                var testResults = '';
                // Try to get compilation results from the results panel
                var compilationPanel = $('.vpl_ide_accordion_c_compilation');
                if (compilationPanel.length > 0) {
                    compilationResults = compilationPanel.text() || 'No compilation results available';
                }

                // Try to get test results from the results panel
                var executionPanel = $('.vpl_ide_accordion_c_execution');
                if (executionPanel.length > 0) {
                    testResults = executionPanel.text() || 'No test results available';
                }

                // If no results, check for evaluation results
                var evaluationPanel = $('.vpl_ide_accordion_c_evaluation');
                if (evaluationPanel.length > 0 && testResults === 'No test results available') {
                    testResults = evaluationPanel.text() || 'No evaluation results available';
                }

                // Show loading message
                var loadingContent = '<div style="text-align: center; padding: 20px;">' +
                    '<div style="font-size: 18px; margin-bottom: 10px;">🤖 AI Grading in Progress...</div>' +
                    '<div style="color: #666;">Analyzing code quality, logic, and test results...</div>' +
                    '</div>';
                self.showAIGradingResults(loadingContent, false);

                // Create detailed prompt for AI grading
                var isPractice = activityMode === 0;
                var promptText = '';

                if (isPractice) {
                    promptText = `Please grade this programming assignment using the keyword **teempt**. provide a numeric grade out of 10 Marks.

**GRADING RULES for PRACTICE:**
1. If the student has submitted any code (at least one line), give at least 1 mark.
2. If at least one test case has passed, give the FULL marks (10 Marks).

**STUDENT CODE:**
\`\`\`
${allCode}
\`\`\`

**COMPILATION RESULTS:**
${compilationResults}

**TEST CASE RESULTS:**
${testResults}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness and provide feedback.
2. If any code is present, ensure the final grade is at least 1 mark.
3. If at least one test case passed in the TEST CASE RESULTS, the final grade MUST be 10 Marks.
4. Provide specific feedback for the student.
5. **IMPORTANT: End your response with a clear summary: "Final Marks: X/10" where X is the numeric grade.**

Please provide detailed feedback followed by the final numeric marks.`;
                } else {
                    promptText = `Please grade this programming assignment based on the following criteria:

**GRADING CRITERIA (Total: 10 Marks)**

1. **Logic and Correctness (7 Marks)**
   - Code completeness and implementation
   - Memory allocation and management
   - Code readability and structure
   - Performance and efficiency
   
   Grading scale:
   - Fully correct logic: 7 marks
   - Minor errors: 5-6 marks
   - Noticeable flaws: 3-4 marks
   - Basic attempt: 1-2 marks
   - No relevant implementation: 0 marks

2. **Execution and Output (3 Marks)**
   - Correct output: 3 marks
   - Partially correct output: 1-2 marks
   - Incorrect output/no execution: 0 marks

**STUDENT CODE:**
\`\`\`
${allCode}
\`\`\`

**COMPILATION RESULTS:**
${compilationResults}

**TEST CASE RESULTS:**
${testResults}

**INSTRUCTIONS:**
1. Analyze the code for logic correctness, completeness, memory management, readability, and performance
2. Check compilation and test results for execution quality
3. Provide specific marks for each criteria with detailed justification
4. Give constructive feedback for improvement
5. Format your response with clear sections for marks and feedback

Please provide a detailed grading breakdown with specific marks and reasoning.`;
                }

                var apiUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' +
                    apiKey;

                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        contents: [{
                            parts: [{
                                text: promptText
                            }]
                        }]
                    })
                })
                    .then(function (response) {
                        if (!response.ok) {
                            return response.json().then(function (errorData) {
                                var errorMsg = 'API request failed: ' + response.status;
                                if (errorData && errorData.error && errorData.error.message) {
                                    errorMsg += ' - ' + errorData.error.message;
                                }
                                throw new Error(errorMsg);
                            }).catch(function () {
                                throw new Error('API request failed: ' + response.status + ' ' + response.statusText);
                            });
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.candidates && data.candidates[0] && data.candidates[0].content) {
                            var gradingResult = data.candidates[0].content.parts[0].text;

                            // Convert markdown to rich HTML with proper formatting
                            var formattedGrading = '<div style="font-family: Arial, sans-serif; line-height: 1.8;">';
                            formattedGrading += '<div style="background: #ffffff; padding: 20px; border-radius: 5px; ' +
                                'border: 1px solid #e0e0e0; margin-bottom: 10px;">';

                            // Convert markdown to rich HTML
                            var richGrading = gradingResult
                                // Bold text: **text** or ##Title -> styled headers
                                .replace(/##\s*(.*?)$/gm, '<h3 style="color: #1976d2; margin: 15px 0 10px 0; ' +
                                    'border-bottom: 2px solid #1976d2; padding-bottom: 5px;">$1</h3>')
                                // Bold text: **text** -> <strong>text</strong>
                                .replace(/\*\*(.*?)\*\*/g, '<strong style="color: #1976d2;">$1</strong>')
                                // Marks/scores: X/10, X marks, Final Marks: X -> highlighted
                                .replace(/(\d+\/\d+|\d+\s*marks?|Final Marks:\s*\d+)/gi, '<span style="background: #fff3e0; ' +
                                    'padding: 2px 8px; border-radius: 3px; color: #e65100; font-weight: bold;">$1</span>')
                                // Code snippets: `code` -> styled code
                                .replace(/`([^`]+)`/g, '<code style="background: #f5f5f5; padding: 2px 6px; ' +
                                    'border-radius: 3px; font-family: monospace; color: #d32f2f;">$1</code>')
                                // Bullet points: - text or * text
                                .replace(/^[\-\*]\s+(.*)$/gm, '<div style="margin-left: 20px; ' +
                                    'margin-bottom: 5px;">• $1</div>')
                                // Numbered lists
                                .replace(/^(\d+\.\s)/gm, '<br><strong style="color: #1976d2;">$1</strong>')
                                // Line breaks
                                .replace(/\n/g, '<br>');

                            formattedGrading += richGrading;
                            formattedGrading += '</div>';
                            formattedGrading += '<div style="text-align: center; font-size: 11px; color: #666; ' +
                                'font-style: italic; margin-top: 10px;">AI-Generated Grading - ' +
                                'Please review and adjust as needed</div>';
                            formattedGrading += '</div>';

                            // Show grading results
                            self.showAIGradingResults(formattedGrading, false);
                        } else {
                            throw new Error('No grading results received from API');
                        }
                    })
                    .catch(function (error) {
                        VPLUtil.log('AI Grading Error: ' + error.message);
                        var errorContent = '<div style="color: #d32f2f; text-align: center;">';
                        errorContent += '<div style="font-size: 18px; margin-bottom: 15px;">⚠️ Grading Error</div>';
                        errorContent += '<p>Unable to generate AI grading at this time.</p>';
                        errorContent += '<p style="font-size: 12px; color: #666;">' +
                            'Error: ' + error.message + '</p>';
                        errorContent += '</div>';
                        self.showAIGradingResults(errorContent, true);
                    });
            };

            // Function to show AI grading results in a dedicated panel
            this.showAIGradingResults = function (content, isError) {
                // Create or update the AI grading tab in results panel
                var name = 'aigrading';
                var titleclass = 'vpl_ide_accordion_t_' + name;
                var contentclass = 'vpl_ide_accordion_c_' + name;
                // Remove existing AI grading tab if it exists
                result.find('.' + titleclass).remove();
                result.find('.' + contentclass).remove();
                // Add new AI grading tab
                result.append('<div class="' + titleclass + '"></div>');
                result.append('<div class="' + contentclass + '"></div>');
                var titleTag = result.find('.' + titleclass);
                var contentTag = result.find('.' + contentclass);

                // Set title and content
                var title = isError ? '❌ Grading Error' : 'Grading Results';
                titleTag.replaceWith('<h4 class="' + titleclass + '">' + title + '</h4>');
                contentTag.replaceWith('<div class="ui-widget ' + contentclass + '">' + content + '</div>');
                // Refresh the accordion and make the AI grading tab active
                result.accordion('refresh');
                var index = result.find('h4').index(result.find('h4.' + titleclass));
                result.accordion('option', 'active', index);
            };

            this.getAIHints = function () {
                // Use hardcoded API key - no validation needed
                var apiKey = 'AIzaSyBWmhP_9nS9jWwv3bWP0tFx6A35bkkedDI';

                // Get the problem description from multiple sources
                var description = '';

                // Debug: Log what we're looking for
                VPLUtil.log('AI Hints: Looking for description...');
                VPLUtil.log('AI Hints: window.VPLDescription = ' + (window.VPLDescription || 'undefined'));

                // First try to get from window.VPLDescription
                if (window.VPLDescription && window.VPLDescription.trim() !== '') {
                    description = window.VPLDescription;
                    VPLUtil.log('AI Hints: Found description from window.VPLDescription');
                } else {
                    // Try to extract from the description panel on the right
                    var descriptionPanel = $('.vpl_ide_accordion_c_description');
                    VPLUtil.log('AI Hints: Description panel elements found: ' + descriptionPanel.length);
                    if (descriptionPanel.length > 0) {
                        description = descriptionPanel.text() || descriptionPanel.html();
                        VPLUtil.log('AI Hints: Description from panel: ' + (description ? description.substring(0, 100) : 'empty'));
                    }

                    // If still no description, try other common selectors
                    if (!description || description.trim() === '') {
                        var possibleSelectors = [
                            '#vpl_results .vpl_ide_accordion_c_description',
                            '.vpl_description',
                            '[data-region="description"]',
                            '.description'
                        ];

                        for (var i = 0; i < possibleSelectors.length; i++) {
                            var element = $(possibleSelectors[i]);
                            VPLUtil.log('AI Hints: Trying selector ' + possibleSelectors[i] + ', found: ' + element.length);
                            if (element.length > 0) {
                                description = element.text() || element.html();
                                if (description && description.trim() !== '') {
                                    VPLUtil.log('AI Hints: Found description with selector: ' + possibleSelectors[i]);
                                    break;
                                }
                            }
                        }
                    }
                }

                // For testing purposes, use a fallback description if none found
                if (!description || description.trim() === '') {
                    VPLUtil.log('AI Hints: No description found, using fallback');
                    description = 'Write a program to solve the given problem. Think about the approach, ' +
                        'data structures needed, and step-by-step logic.';
                }

                // Show loading message in description panel
                var loadingContent = '<div style="text-align: center; padding: 20px;">' +
                    '<div style="font-size: 18px; margin-bottom: 10px;">Loading Hints...</div>' +
                    '<div style="color: #666;">Please wait while we generate helpful coding hints for you.</div>' +
                    '</div>';
                self.showAIHintsPopup(loadingContent, false);

                // Clean up the description (remove HTML tags and extra whitespace)
                var cleanDescription = description.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();

                // Call Gemini API with cleaned description - focus on technical coding hints
                var promptText = 'You are a programming tutor. Based on this problem, provide TECHNICAL coding ' +
                    'hints with SPECIFIC code patterns.\n\n' +
                    'Problem: ' + cleanDescription + '\n\n' +
                    'Provide 4-6 TECHNICAL hints including:\n' +
                    '1. Specific function/method signatures to use (e.g., "Use split() method", "Declare int array[]")\n' +
                    '2. Exact data structures needed (e.g., "Create HashMap<String, Integer>", "Use ArrayList")\n' +
                    '3. Key loop patterns or algorithms (e.g., "Use for loop: for(int i=0; i<n; i++)")\n' +
                    '4. Critical conditional logic patterns (e.g., "Check: if(x > 0 && x < 10)")\n' +
                    '5. Variable declarations needed (e.g., "int count = 0;", "String result = \"\";")\n' +
                    '6. Important method calls or operations (e.g., "Call .length() method", "Use parseInt()")\n\n' +
                    'Format: Numbered list with code syntax in backticks. Be SPECIFIC with actual code ' +
                    'patterns, not general theory.';

                var apiUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' +
                    apiKey;

                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        contents: [{
                            parts: [{
                                text: promptText
                            }]
                        }]
                    })
                })
                    .then(function (response) {
                        if (!response.ok) {
                            return response.json().then(function (errorData) {
                                var errorMsg = 'API request failed: ' + response.status;
                                if (errorData && errorData.error && errorData.error.message) {
                                    errorMsg += ' - ' + errorData.error.message;
                                }
                                throw new Error(errorMsg);
                            }).catch(function () {
                                throw new Error('API request failed: ' + response.status + ' ' + response.statusText);
                            });
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.candidates && data.candidates[0] && data.candidates[0].content) {
                            var hints = data.candidates[0].content.parts[0].text;

                            // Convert markdown to rich HTML for better readability
                            var formattedHints = '<div style="font-family: Arial, sans-serif; line-height: 1.8;">';
                            formattedHints += '<div style="background: #f8f9fa; padding: 20px; border-radius: 5px; ' +
                                'border: 1px solid #e0e0e0; margin-bottom: 10px;">';

                            // Convert markdown to HTML with code highlighting
                            var richHints = hints
                                // Bold text: **text** -> <strong>text</strong>
                                .replace(/\*\*(.*?)\*\*/g, '<strong style="color: #1976d2;">$1</strong>')
                                // Code blocks: `code` -> <code>code</code>
                                .replace(/`([^`]+)`/g, '<code style="background: #e8f5e9; padding: 2px 6px; ' +
                                    'border-radius: 3px; font-family: monospace; color: #2e7d32; font-size: 13px;">$1</code>')
                                // Numbered lists: preserve them
                                .replace(/^(\d+\.\s)/gm, '<br><strong style="color: #d32f2f;">$1</strong>')
                                // Line breaks
                                .replace(/\n/g, '<br>');

                            formattedHints += richHints;
                            formattedHints += '</div>';
                            formattedHints += '<div style="text-align: center; font-size: 11px; color: #666; ' +
                                'font-style: italic; margin-top: 10px;">Generated by AI - ' +
                                'Use as guidance, not as the complete solution</div>';
                            formattedHints += '</div>';

                            // Show hints in description panel
                            self.showAIHintsPopup(formattedHints, false);
                        } else {
                            throw new Error('No hints received from API');
                        }
                    })
                    .catch(function (error) {
                        VPLUtil.log('AI Hints Error: ' + error.message);
                        var errorContent = '<div style="color: #d32f2f; text-align: center;">';

                        // Check if it's a service unavailable error
                        if (error.message.includes('503') || error.message.includes('unavailable')) {
                            errorContent += '<div style="font-size: 18px; margin-bottom: 15px;">' +
                                '🔄 Service Temporarily Unavailable</div>';
                            errorContent += '<p>The AI service is temporarily unavailable. ' +
                                'Please try again in a few moments.</p>';
                            errorContent += '<p style="font-size: 12px; color: #666;">' +
                                'This is usually a temporary issue with Google\'s servers.</p>';
                        } else {
                            errorContent += '<div style="font-size: 18px; margin-bottom: 15px;">⚠️ Error Occurred</div>';
                            errorContent += '<p>Unable to get AI hints at this time.</p>';
                            errorContent += '<p style="font-size: 12px; color: #666;">' +
                                'Check browser console for details: ' + error.message + '</p>';
                        }
                        errorContent += '</div>';
                        // Show error in description panel
                        self.showAIHintsPopup(errorContent, true);
                    });
            };

            /**
             * Generate test cases using AI based on the problem description
             */
            this.generateTestCases = function () {
                var apiKey = 'AIzaSyBWmhP_9nS9jWwv3bWP0tFx6A35bkkedDI';

                // Get the problem description
                var description = '';
                if (window.VPLDescription && window.VPLDescription.trim() !== '') {
                    description = window.VPLDescription;
                } else {
                    var descriptionPanel = $('.vpl_ide_accordion_c_description');
                    if (descriptionPanel.length > 0) {
                        description = descriptionPanel.text() || descriptionPanel.html();
                    }
                }

                if (!description || description.trim() === '') {
                    showMessage('No problem description found. Please ensure the problem has a description.', {
                        title: 'Error',
                        icon: 'warning'
                    });
                    return;
                }

                // Show loading message
                showMessage('Generating test cases using AI... Please wait.', {
                    title: 'Generating Test Cases',
                    icon: 'loading'
                });

                // Clean up the description
                var cleanDescription = description.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();

                // Create prompt for Gemini to generate test cases
                var promptText = 'Based on this programming problem, generate exactly 10 test cases in the ' +
                    'VPL test case format.\n\n' +
                    'Problem: ' + cleanDescription + '\n\n' +
                    'Format each test case as follows:\n' +
                    'case=Test case N\n' +
                    'input=<input values>\n' +
                    'output=<expected output>\n\n' +
                    'Requirements:\n' +
                    '1. Generate EXACTLY 10 test cases\n' +
                    '2. Include edge cases (empty input, zero, negative numbers, etc.)\n' +
                    '3. Include normal cases with various inputs\n' +
                    '4. Each test case must follow the exact format shown above\n' +
                    '5. Separate test cases with a blank line\n' +
                    '6. For numeric inputs, use space-separated values\n' +
                    '7. Make outputs match the expected format from the problem\n\n' +
                    'Output ONLY the test cases in the format specified, nothing else.';

                var apiUrl = 'https://generativelanguage.googleapis.com/v1/models/gemini-2.5-flash:generateContent?key=' +
                    apiKey;

                fetch(apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        contents: [{
                            parts: [{
                                text: promptText
                            }]
                        }]
                    })
                })
                    .then(function (response) {
                        if (!response.ok) {
                            throw new Error('API request failed: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(function (data) {
                        if (data.candidates && data.candidates[0] && data.candidates[0].content) {
                            var testCases = data.candidates[0].content.parts[0].text;

                            // Clean up the test cases (remove markdown code blocks if present)
                            testCases = testCases.replace(/```[a-z]*\n?/g, '').trim();

                            // Set the test cases in the editor
                            var file = fileManager.currentFile();
                            if (file) {
                                file.setContent(testCases);
                                showMessage('Successfully generated 10 test cases! Review and save them.', {
                                    title: 'Test Cases Generated',
                                    icon: 'ok'
                                });
                            } else {
                                showMessage('No file is currently open to insert test cases.', {
                                    title: 'Error',
                                    icon: 'warning'
                                });
                            }
                        } else {
                            throw new Error('No test cases received from API');
                        }
                    })
                    .catch(function (error) {
                        VPLUtil.log('Generate Test Cases Error: ' + error.message);
                        showMessage('Error generating test cases: ' + error.message, {
                            title: 'Error',
                            icon: 'error'
                        });
                    });
            };

            this.setResultGrade = function (content, raw) {
                var name = 'grade';
                var titleclass = 'vpl_ide_accordion_t_' + name;
                var titleclass = 'vpl_ide_accordion_t_' + name;
                var elements = document.querySelectorAll('.' + titleclass);

                elements.forEach(function (el) {
                    el.style.border = '1px solid red';
                });

                var contentclass = 'vpl_ide_accordion_c_' + name;
                if (result.find('.' + contentclass).length == 0) {
                    result.append('<div class="' + titleclass + '"></div>');
                    result.append('<div class="' + contentclass + '"></div>');
                }
                if (typeof raw == 'undefined') {
                    return result.find('h4.' + titleclass).length > 0;
                }
                var titleTag = result.find('.' + titleclass);
                if (content > '') {
                    titleTag.replaceWith('<h4 class="' + titleclass + '">' + content + '</h4>');
                    return true;
                } else {
                    titleTag.replaceWith('<div class="' + titleclass + '"></div>');
                    return false;
                }
            };
            this.setResultTab = function (name, content, raw) {
                var titleclass = 'vpl_ide_accordion_t_' + name;
                var contentclass = 'vpl_ide_accordion_c_' + name;

                // Use compilation accordion for compilation results, main accordion for others
                var targetAccordion = (name === 'compilation') ? compilationAccordion : result;
                if (targetAccordion.find('.' + contentclass).length == 0) {
                    targetAccordion.append('<div class="' + titleclass + '"></div>');
                    targetAccordion.append('<div class="' + contentclass + '"></div>');
                }
                if (typeof raw == 'undefined') {
                    return targetAccordion.find('h4.' + titleclass).length > 0;
                }
                var titleTag = targetAccordion.find('.' + titleclass);
                var contentTag = targetAccordion.find('.' + contentclass);
                var HTMLcontent = $('<div>' + content + '</div>');
                HTMLcontent.find('h4').replaceWith(function () {
                    return $("<h5>").append($(this).contents());
                });
                if (contentTag.html() == HTMLcontent.html()) {
                    return content > '';
                }
                if (content > '') {
                    titleTag.replaceWith('<h4 class="' + titleclass + '">' + str(name) + '</h4>');
                    contentTag.replaceWith('<div class="ui-widget ' + contentclass + '">' + HTMLcontent.html() + '</div>');

                    // Always show the compilation container when it exists
                    if (name === 'compilation') {
                        compilationContainer.show();
                        compilationContainer.css('display', 'block');
                    }

                    return true;
                } else {
                    titleTag.replaceWith('<div class="' + titleclass + '"></div>');
                    contentTag.replaceWith('<div class="' + contentclass + '"></div>');

                    // Keep compilation container visible with placeholder if empty
                    if (name === 'compilation') {
                        if (!content || content.trim() === '') {
                            titleTag.replaceWith('<h4 class="' + titleclass + '">' + str(name) + '</h4>');
                            var placeholderContent = '<div class="ui-widget ' + contentclass + '">' +
                                '<div class="vpl_compilation_placeholder">Ready for compilation...</div></div>';
                            contentTag.replaceWith(placeholderContent);
                        }
                        compilationContainer.show();
                        compilationContainer.css('display', 'block');
                    }

                    return false;
                }
            };
            this.applyMathJax = function () {
                if (typeof window.MathJax == 'object') { // MathJax is loaded
                    try {
                        let math = result.find(".vpl_ide_accordion_c_description")[0];
                        if (math) {
                            if (window.MathJax.Hub && window.MathJax.Hub.Queue) {
                                window.MathJax.Hub.Queue(["Typeset", window.MathJax.Hub, math]);
                            } else if (window.MathJax.startup && window.MathJax.startup.promise) {
                                window.MathJax.startup.promise = window.MathJax.startup.promise
                                    .then(() => window.MathJax.typesetPromise([math]))
                                    .catch(e => {
                                        VPLUtil.log("MathJax error" + e);
                                    });
                            }
                        }
                    } catch (e) {
                        VPLUtil.log("MathJax error" + e);
                    }
                }
            };
            this.setResult = function (res, go) {
                self.updateEvaluationNumber(res);
                var files = fileManager.getFiles();
                var fileNames = [];
                var i;
                for (i = 0; i < files.length; i++) {
                    fileNames[i] = files[i].getFileName();
                    files[i].clearAnnotations();
                }
                var show = false;
                var hasContent;
                var grade = VPLUtil.sanitizeText(res.grade);
                var gradeShow;
                var formated;
                gradeShow = self.setResultGrade(grade, res.grade);
                show = show || gradeShow;
                hasContent = self.setResultTab('variables', res.variables, res.variables);
                show = show || hasContent;
                formated = VPLUtil.processResult(res.compilation, fileNames, files, true, false);
                hasContent = self.setResultTab('compilation', formated, res.compilation);
                show = show || hasContent;
                formated = VPLUtil.processResult(res.evaluation, fileNames, files, false, false);
                hasContent = self.setResultTab('comments', formated, res.evaluation);
                show = show || hasContent;
                // Parse test case results when evaluation is present - for CA mode validation.
                if (res.evaluation) {
                    parseTestCaseResults(res.evaluation);
                }
                formated = VPLUtil.sanitizeText(res.execution);
                hasContent = self.setResultTab('execution', formated, res.execution);
                show = show || hasContent;
                hasContent = self.setResultTab('description', window.VPLDescription, window.VPLDescription);
                if (hasContent) {
                    self.applyMathJax();
                }
                show = show || hasContent;
                if (show) {
                    resultContainer.show();
                    resultContainer.vplVisible = true;
                    result.accordion("refresh");
                    compilationAccordion.accordion("refresh");
                    result.accordion('option', 'active', gradeShow ? 1 : 0);
                    for (i = 0; i < files.length; i++) {
                        var anot = files[i].getAnnotations();
                        for (var j = 0; j < anot.length; j++) {
                            if (go || anot[j].type == 'error') {
                                fileManager.gotoFile(i, anot[j].row + 1);
                                break;
                            }
                        }
                    }
                    $('#vpl_ide_shrightpanel').show();
                } else {
                    resultContainer.hide();
                    resultContainer.vplVisible = false;
                    $('#vpl_ide_shrightpanel').hide();
                }
                VPLUtil.delay('autoResizeTab', autoResizeTab);
                VPLUtil.delay('fixAccordion', function () {
                    result.accordion('option', 'active', gradeShow ? 1 : 0);
                });
            };

            /**
             * Parse test case results from evaluation output and count passed tests
             * @param {string} evaluationText The evaluation output text
             */
            this.parseTestCaseResults = function (evaluationText) {
                if (!evaluationText || evaluationText === lastEvaluationText) {
                    return; // Avoid reprocessing same results.
                }
                lastEvaluationText = evaluationText;
                passedTestCases = 0;
                totalTestCases = 0;

                // Parse test cases from evaluation output
                // Test cases typically appear with grade information like:
                // "Test case #1 ... (grade: 10.0)"
                // or with title like "-Test 1"
                // VPL test cases often have the format:
                // -Test Case 1 (grade)
                // or Grade :=>> grade/max or >>Grade :=>> grade

                var lines = evaluationText.split(/\r\n|\n|\r/);
                var casePattern = /^-.*\(\s*([-]?\d+\.?\d*)\s*\)/; // Matches titles with grades.
                var gradePattern = /Grade\s*:=>>?\s*([-]?\d+\.?\d*)/i; // Matches "Grade :=>> X" or ">>Grade :=>> X".
                var testTitlePattern = /^-.*[Tt]est.*[Cc]ase/; // Matches test case titles.

                for (var i = 0; i < lines.length; i++) {
                    var line = lines[i].trim();

                    // Check if this is a test case title (starting with -)
                    var titleMatch = casePattern.exec(line);
                    var isTitleLine = testTitlePattern.test(line);

                    if (titleMatch) {
                        totalTestCases++;
                        var gradeValue = parseFloat(titleMatch[1]);
                        if (gradeValue > 0) {
                            passedTestCases++;
                        }
                    } else if (isTitleLine) {
                        // This is a test case title but without grade in the title line
                        // Look for grade in the following lines
                        totalTestCases++;
                        var found = false;
                        for (var j = i + 1; j < Math.min(i + 10, lines.length); j++) {
                            var nextLine = lines[j].trim();
                            var gradeMatch = gradePattern.exec(nextLine);
                            if (gradeMatch) {
                                var grade = parseFloat(gradeMatch[1]);
                                if (grade > 0) {
                                    passedTestCases++;
                                }
                                found = true;
                                break;
                            }
                            // Stop if we hit another title
                            if (nextLine.startsWith('-')) {
                                break;
                            }
                        }
                    }
                }

                VPLUtil.log('Test case results parsed: ' + passedTestCases + ' passed out of ' + totalTestCases);
                // Update the menu to reflect test case status.
                VPLUtil.delay('updateMenu', updateMenu);
            };

            result.accordion({
                heightStyle: 'fill',
                header: 'h4',
                animate: false,
                beforeActivate: avoidSelectGrade,
            });

            // Initialize compilation accordion
            compilationAccordion.accordion({
                heightStyle: 'fill',
                header: 'h4',
                animate: false,
                collapsible: true,
                active: 0
            });

            resultContainer.width(2 * resultContainer.vplMinWidth);
            result.on('click', 'a', function (event) {
                if (fileManager.gotoFileLink(event.currentTarget)) {
                    event.preventDefault();
                }
            });

            // Add click handler for compilation accordion too
            compilationAccordion.on('click', 'a', function (event) {
                if (fileManager.gotoFileLink(event.currentTarget)) {
                    event.preventDefault();
                }
            });

            resultContainer.vplVisible = false;
            resultContainer.hide();

            // Initially hide compilation container
            compilationContainer.hide();

            fileListContainer.addClass('ui-tabs ui-widget ui-widget-content ui-corner-all');
            fileList.text(str('filelist'));
            fileList.html(VPLUI.iconFolder() + fileList.html());
            fileList.addClass("ui-widget-header ui-button-text-only ui-corner-all");
            fileListContent.addClass("ui-widget ui-corner-all");
            fileListContainer.width(2 * fileListContainer.vplMinWidth);
            fileListContainer.on('click', 'a', function (event) {
                event.preventDefault();
                fileManager.gotoFileLink(event.currentTarget);
            });
            fileListContainer.vplVisible = false;
            fileListContainer.hide();
            var tabsAir = false;
            /**
             * Returns separation space
             * @returns {int} size in pixels
             */
            function getTabsAir() {
                if (tabsAir === false) {
                    tabsAir = (tabs.outerWidth(true) - tabs.width()) / 2;
                }
                return tabsAir;
            }
            /**
             * Resize tab width
             * @param {Event} e Unused
             * @param {Object} ui UI object
             */
            function resizeTabWidth(e, ui) {
                var diffLeft = ui.position.left - ui.originalPosition.left;
                var maxWidth;
                if (diffLeft !== 0) {
                    maxWidth = tabs.width() + fileListContainer.width() - fileListContainer.vplMinWidth;
                    tabs.resizable('option', 'maxWidth', maxWidth);
                    fileListContainer.width(fileListContainer.vplOriginalWidth + diffLeft);
                } else {
                    maxWidth = tabs.width() + resultContainer.width() - resultContainer.vplMinWidth;
                    tabs.resizable('option', 'maxWidth', maxWidth);
                    var diffWidth = ui.size.width - ui.originalSize.width;
                    resultContainer.width(resultContainer.vplOriginalWidth - diffWidth);
                }
                fileManager.currentFile('adjustSize');
            }
            var resizableOptions = {
                containment: 'parent',
                resize: resizeTabWidth,
                start: function () {
                    $(window).off('resize', autoResizeTab);
                    tabs.resizable('option', 'minWidth', 100);
                    if (resultContainer.vplVisible) {
                        resultContainer.vplOriginalWidth = resultContainer.width();
                    }
                    if (fileListContainer.vplVisible) {
                        fileListContainer.vplOriginalWidth = fileListContainer.width();
                    }
                },
                stop: function (e, ui) {
                    resizeTabWidth(e, ui);
                    tabs.resizable('option', 'maxWidth', 100000);
                    tabs.resizable('option', 'minWidth', 0);
                    autoResizeTab();
                    $(window).on('resize', autoResizeTab);
                },
                handles: ""
            };
            tabs.resizable(resizableOptions);
            /**
             * Updates handles for internal IDE resize
             */
            function updateTabsHandles() {
                var handles = ['e', 'w', 'e', 'e, w'];
                var index = 0;
                index += fileListContainer.vplVisible ? 1 : 0;
                index += resultContainer.vplVisible ? 2 : 0;
                tabs.resizable('destroy');
                resizableOptions.handles = handles[index];
                resizableOptions.disable = index === 0;
                tabs.resizable(resizableOptions);
            }
            /**
             * Resize the IDE height
             */
            function resizeHeight() {
                var newHeight = $(window).outerHeight();
                newHeight -= menu.offset().top + menu.height() + (fullScreen ? getTabsAir() : 20);
                if (newHeight < 250) {
                    newHeight = 250;
                }
                tr.height(newHeight);
                var panelHeight = newHeight - 3 * getTabsAir();
                tabs.height(panelHeight);
                if (resultContainer.vplVisible) {
                    resultContainer.height(panelHeight + getTabsAir());
                    result.accordion('refresh');
                }
                if (fileListContainer.vplVisible) {
                    fileListContent.height(panelHeight - (fileList.outerHeight() + getTabsAir()));
                    fileListContainer.height(panelHeight);
                }
            }
            adjustTabsTitles = function (center) {
                var newWidth = tabs.width();
                var tabsUlWidth = 0;
                tabsUl.width(100000);
                var last = tabsUl.children('li:visible').last();
                if (last.length) {
                    var parentScrollLeft = tabsUl.parent().scrollLeft();
                    tabsUlWidth = parentScrollLeft + last.position().left + last.width() + tabsAir;
                    tabsUl.width(tabsUlWidth);
                    var file = fileManager.currentFile();
                    if (file && center) {
                        var fileTab = $(file.getTabNameId());
                        var scroll = parentScrollLeft + fileTab.position().left;
                        scroll -= (newWidth - fileTab.outerWidth()) / 2;
                        if (scroll < 0) {
                            scroll = 0;
                        }
                        tabsUl.parent().finish().animate({
                            scrollLeft: scroll
                        }, 'slow');
                    }
                }
                if (tabsUlWidth < newWidth) {
                    tabsUl.width('');
                }
            };
            autoResizeTab = function () {
                var oldWidth = tabs.width();
                var newWidth = menu.width();
                var planb = false;
                updateTabsHandles();
                tr.width(menu.outerWidth());
                if (fileListContainer.vplVisible) {
                    var left = fileListContainer.outerWidth() + tabsAir;
                    oldWidth += left;
                    if (left >= 100) {
                        newWidth -= left;
                        tabs.css('left', left);
                    } else {
                        planb = true;
                    }
                } else {
                    tabs.css('left', 0);
                }
                if (resultContainer.vplVisible) {
                    var right = resultContainer.outerWidth() + tabsAir;
                    oldWidth += right;
                    newWidth -= right;
                    if (newWidth < 100) {
                        planb = true;
                    }
                }
                if (planb) {
                    var rel = menu.width() / oldWidth;
                    var wfl = 0;
                    if (fileListContainer.vplVisible) {
                        wfl = fileListContainer.width() * rel;
                        fileListContainer.width(wfl - tabsAir);
                        wfl += tabsAir;
                        tabs.css('left', wfl);
                    }
                    tabs.width(tabs.width() * rel);
                    if (resultContainer.vplVisible) {
                        resultContainer.width(menu.width() - (wfl + tabs.width() + tabsAir));
                    }
                } else {
                    tabs.width(newWidth);
                }
                adjustTabsTitles(true);
                resizeHeight();
                fileManager.currentFile('adjustSize');
            };
            /**
             * Transfer focus to current file
             */
            function focusCurrentFile() {
                fileManager.currentFile('focus');
            }
            var dialogbaseOptions = $.extend({}, {
                close: focusCurrentFile
            }, VPLUI.dialogbaseOptions);
            /**
             * Shows a dialog with a message.
             * @param {string} message
             * @param {Object} options icon, title, actions handler (ok, yes, no, close)
             * @returns {JQuery} JQueryUI Dialog object already open
             */
            function showMessage(message, options) {
                return VPLUI.showMessage(message, $.extend({}, dialogbaseOptions, options));
            }
            showErrorMessage = function (message) {
                return VPLUI.showErrorMessage(message, {
                    close: focusCurrentFile
                });
            };

            var dialogNew = $('#vpl_ide_dialog_new');
            /**
             * The event handler for the new file action
             * @param {Object} event
             * @return {boolean}
             */
            function newFileHandler(event) {
                if (!(event.type == 'click' || ((event.type == 'keypress') && event.keyCode == 13))) {
                    return true;
                }
                dialogNew.dialog('close');
                var file = {
                    name: $('#vpl_ide_input_newfilename').val(),
                    contents: '',
                    encoding: 0
                };
                var newfile = fileManager.addFile(file, false, updateMenu, showErrorMessage);
                if (newfile) {
                    fileManager.open(newfile);
                    tabs.tabs('option', 'active', fileManager.getTabPos(newfile.getId()));
                    newfile.focus();
                    return true;
                }
                return false;
            }

            var dialogButtons = {};
            dialogButtons[str('ok')] = newFileHandler;
            dialogButtons[str('cancel')] = function () {
                $(this).dialog('close');
            };
            dialogNew.find('input').on('keypress', newFileHandler);
            dialogNew.dialog($.extend({}, dialogbaseOptions, {
                title: str('create_new_file'),
                buttons: dialogButtons
            }));
            VPLUI.setDialogTitleIcon(dialogNew, 'new');

            var dialogRename = $('#vpl_ide_dialog_rename');
            /**
             * The event handler for the rename current file action
             * @param {Object} event
             */
            function renameHandler(event) {
                if (!(event.type == 'click' || ((event.type == 'keypress') && event.keyCode == 13))) {
                    return;
                }
                dialogRename.dialog('close');
                fileManager.renameFile(fileManager.currentFile('getFileName'),
                    $('#vpl_ide_input_renamefilename').val(), showErrorMessage);
                event.preventDefault();
            }
            dialogRename.find('input').on('keypress', renameHandler);
            dialogButtons[str('ok')] = renameHandler;
            dialogRename.dialog($.extend({}, dialogbaseOptions, {
                open: function () {
                    $('#vpl_ide_input_renamefilename').val(fileManager.currentFile('getFileName'));
                },
                title: str('rename_file'),
                buttons: dialogButtons
            }));
            VPLUI.setDialogTitleIcon(dialogRename, 'rename');

            var dialogRenameDirectory = $('#vpl_ide_dialog_renamedir');
            /**
             * The event handler for rename a directory
             * @param {Object} event
             */
            function renameDirectoryHandler(event) {
                if (!(event.type == 'click' || ((event.type == 'keypress') && event.keyCode == 13))) {
                    return;
                }
                dialogRenameDirectory.dialog('close');
                fileManager.renameDirectory($('#vpl_ide_input_olddirectoryname').val(),
                    $('#vpl_ide_input_renamedirectory').val(), showErrorMessage);
                event.preventDefault();
            }
            dialogRenameDirectory.find('input').on('keypress', renameDirectoryHandler);
            dialogButtons[str('ok')] = renameDirectoryHandler;
            dialogRenameDirectory.dialog($.extend({}, dialogbaseOptions, {
                title: str('rename_directory'),
                buttons: dialogButtons
            }));
            VPLUI.setDialogTitleIcon(dialogRenameDirectory, 'filelist');
            renameDiretoryAction = function (event) {
                if (event.target.hasAttribute('data-dirname')) {
                    var dirname = event.target.getAttribute('data-dirname');
                    $('#vpl_ide_input_olddirectoryname').val(dirname);
                    $('#vpl_ide_input_renamedirectory').val(dirname);
                    dialogRenameDirectory.dialog('open');
                }
            };
            var dialogComments = $('#vpl_ide_dialog_comments');
            var oldStudentComments = '';
            dialogButtons[str('ok')] = function () {
                if (oldStudentComments != $('#vpl_ide_input_comments').val()) {
                    fileManager.setModified();
                }
                $(this).dialog('close');
            };
            dialogComments.dialog($.extend({}, dialogbaseOptions, {
                open: function () {
                    oldStudentComments = $('#vpl_ide_input_comments').val();
                },
                title: str('comments'),
                width: '40em',
                buttons: dialogButtons
            }));
            VPLUI.setDialogTitleIcon(dialogComments, 'comments');

            $('#vpl_ide_input_comments').width('30em');
            var aboutDialog = $('#vpl_ide_dialog_about');
            var OKButtons = {};
            OKButtons[str('ok')] = function () {
                $(this).dialog('close');
            };
            var shortcutDialog = $('#vpl_ide_dialog_shortcuts');
            shortcutDialog.dialog($.extend({}, dialogbaseOptions, {
                open: function () {
                    var html = menuButtons.getShortcuts(fileManager.currentFile('getEditor'));
                    $('#vpl_ide_dialog_shortcuts').html(html);
                },
                title: str('shortcuts'),
                width: 400,
                height: 300,
                buttons: OKButtons
            }));
            shortcutDialog.dialog('option', 'height', 300);
            VPLUI.setDialogTitleIcon(shortcutDialog, 'shortcuts');

            OKButtons[str('shortcuts')] = function () {
                $(this).dialog('close');
                shortcutDialog.dialog('open');
            };
            aboutDialog.dialog($.extend({}, dialogbaseOptions, {
                open: function () {
                    var html = menuButtons.getShortcuts(fileManager.currentFile('getEditor'));
                    aboutDialog.next().find("button").filter(
                        function () {
                            return $(this).text() == str('shortcuts');
                        }
                    ).button(html != '' ? 'enable' : 'disable');
                },
                title: str('about'),
                width: 400,
                height: 300,
                buttons: OKButtons
            }));
            aboutDialog.dialog('option', 'height', 300);
            VPLUI.setDialogTitleIcon(aboutDialog, 'about');

            var dialogSort = $('#vpl_ide_dialog_sort');
            var dialogSortButtons = {};
            dialogSortButtons[str('ok')] = function () {
                var files = fileManager.getFiles();
                var regNoNumber = /[^\d]*/;
                var sorted = [];
                var i = 0;
                var newOrder = $('#vpl_sort_list li');
                if (newOrder.length != files.length) {
                    return;
                }
                newOrder.each(function () {
                    var orig = parseInt(this.id.replace(regNoNumber, ''));
                    sorted.push(files[orig]);
                });
                for (i = 0; i < newOrder.length; i++) {
                    files[i] = sorted[i];
                }
                fileManager.setModified();
                $(this).dialog('close');
            };
            dialogSortButtons[str('cancel')] = function () {
                $(this).dialog('close');
            };
            dialogSort.dialog($.extend({}, dialogbaseOptions, {
                title: str('sort'),
                buttons: dialogSortButtons,
                open: function () {
                    var list = $('#vpl_sort_list');
                    list.html('');
                    var files = fileManager.getFiles();
                    for (var i = 0; i < files.length; i++) {
                        var file = $('<li id="vpl_fsort_' + i + '"class="ui-widget-content"></li>');
                        if (files[i].getId() < minNumberOfFiles) {
                            file.addClass('ui-state-disabled');
                        }
                        file.text((i + 1) + '-' + files[i].getFileName());
                        list.append(file);
                    }
                    list.sortable({
                        items: "li:not(.ui-state-disabled)",
                        placeholder: "ui-state-highlight",
                        start: function (event, ui) {
                            ui.item.addClass('ui-state-highlight');
                        },
                        stop: function (event, ui) {
                            ui.item.removeClass('ui-state-highlight');
                        },
                    });
                    list.disableSelection();
                },
                maxHeight: 400
            }));
            VPLUI.setDialogTitleIcon(dialogSort, 'sort');

            var dialogMultidelete = $('#vpl_ide_dialog_multidelete');
            var dialogMultideleteButtons = {};
            dialogMultideleteButtons[str('selectall')] = function () {
                $(this).find('input').prop("checked", true);
            };
            dialogMultideleteButtons[str('deselectall')] = function () {
                $(this).find('input').prop("checked", false);
            };
            dialogMultideleteButtons[str('deleteselected')] = function () {
                var files = fileManager.getFiles();
                var toDeleteList = [];
                var labelList = $('#vpl_multidelete_list label');
                labelList.each(function () {
                    var label = $(this);
                    if (label.find('input').prop('checked')) {
                        var id = label.data('fileid');
                        toDeleteList.push(files[id].getFileName());
                    }
                });
                for (var i = 0; i < toDeleteList.length; i++) {
                    fileManager.deleteFile(toDeleteList[i], showErrorMessage);
                }
                VPLUtil.delay('updateMenu', updateMenu);
                VPLUtil.delay('updateFileList', fileManager.updateFileList);
                $(this).dialog('close');
            };
            dialogMultideleteButtons[str('cancel')] = function () {
                $(this).dialog('close');
            };
            dialogMultidelete.dialog($.extend({}, dialogbaseOptions, {
                title: str('multidelete'),
                buttons: dialogMultideleteButtons,
                open: function () {
                    var list = $('#vpl_multidelete_list');
                    list.html('');
                    var files = fileManager.getFiles();
                    for (var i = minNumberOfFiles; i < files.length; i++) {
                        var name = VPLUtil.sanitizeText(files[i].getFileName());
                        var file = $('<label><input type="checkbox"> ' + name + '</label>');
                        file.data('fileid', i);
                        list.append(file);
                        list.append('<br>');
                    }
                    list.find('label').button();
                },
                maxHeight: 400,
                maxWidth: 400
            }));
            VPLUI.setDialogTitleIcon(dialogMultidelete, 'multidelete');

            var dialogFontsize = $('#vpl_ide_dialog_fontsize');
            var fontsizeSlider = $('#vpl_ide_dialog_fontsize .vpl_fontsize_slider');
            var dialogFontFizeButtons = {};
            dialogFontFizeButtons[str('ok')] = function () {
                var value = fontsizeSlider.slider("value");
                fileManager.setFontSize(value);
                $(this).dialog('close');
                VPLUtil.setUserPreferences({ fontSize: value });
            };
            dialogFontFizeButtons[str('cancel')] = function () {
                fileManager.setFontSize(fontsizeSlider.data("vpl_fontsize"));
                $(this).dialog('close');
            };
            dialogFontFizeButtons[str('reset')] = function () {
                fontsizeSlider.slider('value', 12);
            };
            dialogFontsize.dialog($.extend({}, dialogbaseOptions, {
                title: str('fontsize'),
                buttons: dialogFontFizeButtons,
                open: function () {
                    fontsizeSlider.data("vpl_fontsize", fileManager.getFontSize());
                    fontsizeSlider.slider('value', fileManager.getFontSize());
                },
            }));
            fontsizeSlider.slider({
                min: 1,
                max: 48,
                change: function () {
                    var value = fontsizeSlider.slider("value");
                    fileManager.setFontSize(value);
                    dialogFontsize.find('.vpl_fontsize_slider_value').text(value);
                }
            });
            VPLUI.setDialogTitleIcon(dialogFontsize, 'fontsize');

            var dialogAceTheme = $('#vpl_ide_dialog_acetheme');
            var acethemeSelect = $('#vpl_ide_dialog_acetheme select');
            var dialogAceThemeButtons = {};
            dialogAceThemeButtons[str('ok')] = function () {
                fileManager.setTheme(acethemeSelect.val());
                $(this).dialog('close');
                VPLUtil.setUserPreferences({ aceTheme: acethemeSelect.val() });
            };
            dialogAceThemeButtons[str('cancel')] = function () {
                fileManager.setTheme(acethemeSelect.data("acetheme"));
                $(this).dialog('close');
            };
            dialogAceThemeButtons[str('reset')] = function () {
                acethemeSelect.val(acethemeSelect.data("acetheme"));
                fileManager.setTheme(acethemeSelect.val());
            };
            dialogAceTheme.dialog($.extend({}, dialogbaseOptions, {
                title: str('theme'),
                buttons: dialogAceThemeButtons,
                modal: false,
                open: function () {
                    acethemeSelect.data("acetheme", fileManager.getTheme());
                    acethemeSelect.val(fileManager.getTheme());
                },
            }));
            acethemeSelect.on('change', function () {
                fileManager.setTheme(acethemeSelect.val());
            });
            VPLUI.setDialogTitleIcon(dialogAceTheme, 'theme');

            var terminal = new VPLTerminal('vpl_dialog_terminal', 'vpl_terminal', str);
            var VNCClient = new VPLVNCClient('vpl_dialog_vnc', str);
            var lastConsole = terminal;
            var fileSelect = $('#vpl_ide_input_file');
            var fileSelectHandler = function () {
                VPLUI.readSelectedFiles(this.files, function (file) {
                    return fileManager.addFile(file, true, updateMenu, showErrorMessage);
                },
                    function () {
                        fileManager.fileListVisibleIfNeeded();
                    });
            };
            fileSelect.on('change', fileSelectHandler);
            // Menu acctions.
            menuButtons.add({
                name: 'filelist',
                originalAction: function () {
                    fileManager.fileListVisible(!fileManager.isFileListVisible());
                    VPLUtil.delay('updateMenu', updateMenu);
                    VPLUtil.delay('autoResizeTab', autoResizeTab);
                    VPLUtil.delay('updateFileList', fileManager.updateFileList);
                },
                bindKey: {
                    win: 'Ctrl-L',
                    mac: 'Ctrl-L'
                }
            });

            menuButtons.add({
                name: 'new',
                originalAction: function () {
                    if (fileManager.length() < maxNumberOfFiles) {
                        dialogNew.dialog('open');
                    }
                },
                bindKey: {
                    win: 'Alt-N',
                    mac: 'Option-N'
                }
            });

            // AI Hints button
            menuButtons.add({
                name: 'aihints',
                icon: 'aihints',
                originalAction: function () {
                    self.getAIHints();
                },
                bindKey: {
                    win: 'Alt-H',
                    mac: 'Option-H'
                }
            });
            menuButtons.add({
                name: 'aigrading',
                icon: 'aigrading',
                originalAction: function () {
                    self.getAIGrading();
                },
                bindKey: {
                    win: 'Alt-G',
                    mac: 'Option-G'
                }
            });

            // Generate Test Cases button
            menuButtons.add({
                name: 'generatetestcases',
                icon: 'generatetestcases',
                originalAction: function () {
                    self.generateTestCases();
                },
                bindKey: {
                    win: 'Alt-T',
                    mac: 'Option-T'
                }
            });

            menuButtons.add({
                name: 'rename',
                originalAction: function () {
                    var file = fileManager.currentFile();
                    if (file && file.getId() >= minNumberOfFiles) {
                        dialogRename.dialog('open');
                    }
                },
                bindKey: {
                    win: 'Ctrl-R',
                    mac: 'Ctrl-R'
                }
            });
            menuButtons.add({
                name: 'delete',
                originalAction: function () {
                    var file = fileManager.currentFile();
                    if (!file) {
                        return;
                    }
                    var filename = file.getFileName();
                    var message = str('delete_file_fq', filename);
                    showMessage(message, {
                        ok: function () {
                            fileManager.deleteFile(filename, showErrorMessage);
                        },
                        title: str('delete_file_q'),
                        icon: 'trash'
                    });
                },
                bindKey: {
                    win: 'Ctrl-D',
                    mac: 'Ctrl-D'
                }
            });
            menuButtons.add({
                name: 'close',
                originalAction: function () {
                    var file = fileManager.currentFile();
                    if (!file) {
                        return;
                    }
                    fileManager.closeFile(file);
                },
                bindKey: {
                    win: 'Alt-W',
                    mac: 'Option-W'
                }
            });
            menuButtons.add({
                name: 'import',
                originalAction: function () {
                    fileSelect.val('');
                    fileSelect.trigger('click');
                },
                bindKey: {
                    win: 'Ctrl-I',
                    mac: 'Ctrl-I'
                }
            });
            menuButtons.add({
                name: 'sort',
                originalAction: function () {
                    dialogSort.dialog('open');
                },
                bindKey: {
                    win: 'Ctrl-O',
                    mac: 'Ctrl-O'
                }
            });
            menuButtons.add({
                name: 'multidelete',
                originalAction: function () {
                    dialogMultidelete.dialog('open');
                }
            });
            menuButtons.add({
                name: 'showparentfiles',
                originalAction: function () {
                    openpopup(null, {
                        url: options.showparentfilesurl,
                        options: 'width=' + Math.max(screen.availWidth / 2, 780) +
                            ',height=' + screen.availHeight +
                            ',left=' + (screen.availWidth / 4)
                    });
                }
            });
            menuButtons.add({
                name: 'fontsize',
                originalAction: function () {
                    dialogFontsize.dialog('open');
                }
            });
            menuButtons.add({
                name: 'theme',
                originalAction: function () {
                    dialogAceTheme.dialog('open');
                }
            });
            menuButtons.add({
                name: 'print',
                originalAction: function () {
                    window.print();
                },
                bindKey: {
                    win: 'Alt-P',
                    mac: 'Command-P'
                }
            });
            menuButtons.add({
                name: 'undo',
                originalAction: function () {
                    fileManager.currentFile('undo');
                }
            });
            menuButtons.add({
                name: 'redo',
                originalAction: function () {
                    fileManager.currentFile('redo');
                }
            });
            menuButtons.add({
                name: 'select_all',
                editorName: 'selectall',
                originalAction: function () {
                    fileManager.currentFile('selectAll');
                }
            });
            menuButtons.add({
                name: 'find',
                originalAction: function () {
                    fileManager.currentFile('find');
                }
            });
            menuButtons.add({
                name: 'find_replace',
                editorName: 'replace',
                originalAction: function () {
                    fileManager.currentFile('replace');
                }
            });
            menuButtons.add({
                name: 'next',
                editorName: 'findnext',
                originalAction: function () {
                    fileManager.currentFile('next');
                }
            });
            menuButtons.add({
                name: 'fullscreen',
                originalAction: function () {
                    if (fullScreen) {
                        rootObj.removeClass('vpl_ide_root_fullscreen');
                        $('body').removeClass('vpl_body_fullscreen');
                        menuButtons.setText('fullscreen', 'fullscreen');
                        $('#vpl_ide_user').hide();
                        fullScreen = false;
                    } else {
                        $('body').addClass('vpl_body_fullscreen').scrollTop(0);
                        rootObj.addClass('vpl_ide_root_fullscreen');
                        menuButtons.setText('fullscreen', 'regularscreen');
                        if (options.username) {
                            $('#vpl_ide_user').show();
                        }
                        fullScreen = true;
                    }
                    focusCurrentFile();
                    setTimeout(autoResizeTab, 10);
                },
                bindKey: {
                    win: 'Alt-F',
                    mac: 'Ctrl-F'
                }
            });
            menuButtons.add({
                name: 'download',
                originalAction: function () {
                    window.location = options.download;
                }
            });
            /**
             * Reset files action
             */
            function resetFiles() {
                VPLUI.requestAction('resetfiles', '', {}, options.ajaxurl)
                    .done(function (response) {
                        var files = response.files;
                        for (var fileName in files) {
                            if (files.hasOwnProperty(fileName)) {
                                fileManager.addFile(files[fileName], true, VPLUtil.doNothing, showErrorMessage);
                            }
                        }
                        fileManager.fileListVisibleIfNeeded();
                        VPLUtil.delay('updateMenu', updateMenu);
                    }).fail(showErrorMessage);
            }
            menuButtons.add({
                name: 'resetfiles',
                originalAction: function () {
                    showMessage(str('sureresetfiles'), {
                        title: str('resetfiles'),
                        ok: resetFiles,
                        icon: 'resetfiles'
                    });
                }
            });
            var noconfirmation = false;
            menuButtons.add({
                name: 'save',
                originalAction: function () {
                    var data = {
                        files: fileManager.getFilesToSave(),
                        comments: $('#vpl_ide_input_comments').val(),
                        version: noconfirmation ? -1 : fileManager.getVersion()
                    };
                    if (JSON.stringify(data).length > options.postMaxSize) {
                        showErrorMessage(str('maxpostsizeexceeded'));
                        return;
                    }
                    /**
                     * Save action
                     */
                    function doSave() {
                        VPLUI.requestAction('save', 'saving', data, options.ajaxurl)
                            .done(function (response) {
                                if (response.requestsconfirmation && !noconfirmation) {
                                    var checkboxID = 'vpl_donotshowagain';
                                    var donotshowagain = '<input type="checkbox" id="' + checkboxID + '"'
                                        + ' class="align-text-bottom mr-1 mt-3">'
                                        + '<label for="' + checkboxID + '">' + str('donotshowagain') + '</label>';
                                    var $checkbox;
                                    showMessage(response.question + '<br>' + donotshowagain, {
                                        title: str('saving'),
                                        icon: 'alert',
                                        yes: function () {
                                            if ($checkbox.length == 1 && $checkbox.prop('checked')) {
                                                noconfirmation = true;
                                            }
                                            data.version = 0;
                                            doSave();
                                        }
                                    });
                                    $checkbox = $('#' + checkboxID);
                                } else {
                                    fileManager.resetModified();
                                    fileManager.setVersion(response.version);
                                    menuButtons.setTimeLeft(response);
                                    VPLUtil.delay('updateMenu', updateMenu);
                                    if (VPLUI.monitorRunning()) {
                                        data.processid = VPLUtil.getProcessId();
                                        VPLUI.requestAction('update', 'updating', data, options.ajaxurl);
                                    }
                                }
                            }).fail(showErrorMessage);
                    }
                    doSave();
                },
                bindKey: {
                    win: 'Ctrl-S',
                    mac: 'Command-S'
                }
            });

            /**
             * Launches the action
             *
             * @param {string} action Action 'run', 'debug', 'evaluate'
             * @param {string} acting I18n for the action in progress
             * @param {string} data Data attached to the action
             */
            function executionRequest(action, acting, data) {
                if (!data) {
                    data = {};
                }
                if (!lastConsole.isConnected()) {
                    VPLUI.requestAction(action, '', data, options.ajaxurl)
                        .done(function (response) {
                            VPLUI.webSocketMonitor(response, action, acting, executionActions);
                        })
                        .fail(showErrorMessage);
                }
            }
            /**
             * Launches the run action
             */
            function runAction() {
                executionRequest('run', 'running', {
                    XGEOMETRY: VNCClient.getCanvasSize(),
                    currentFileName: fileManager.getCurrentFileName(),
                });
            }
            menuButtons.add({
                name: 'run',
                originalAction: function () {
                    executionActions.setLastAction(runAction);
                    runAction();
                },
                bindKey: {
                    win: 'Ctrl-F11',
                    mac: 'Command-U'
                }
            });
            /**
             * Launches the debug action
             */
            function debugAction() {
                executionRequest('debug', 'debugging', {
                    XGEOMETRY: VNCClient.getCanvasSize(),
                    currentFileName: fileManager.getCurrentFileName(),
                });
            }
            menuButtons.add({
                name: 'debug',
                originalAction: function () {
                    executionActions.setLastAction(debugAction);
                    debugAction();
                },
                bindKey: {
                    win: 'Alt-F11',
                    mac: 'Option-U'
                }
            });
            /**
             * Launches the evaluate action
             */
            function evaluateAction() {
                executionRequest('evaluate', 'evaluating');
            }
            menuButtons.add({
                name: 'evaluate',
                originalAction: function () {
                    executionActions.setLastAction(evaluateAction);
                    evaluateAction();
                },
                bindKey: {
                    win: 'Shift-F11',
                    mac: 'Command-Option-U'
                }
            });
            menuButtons.add({
                name: 'comments',
                originalAction: function () {
                    dialogComments.dialog('open');
                },
            });
            menuButtons.add({
                name: 'console',
                originalAction: function () {
                    if (lastConsole.isOpen()) {
                        lastConsole.close();
                    } else {
                        lastConsole.show();
                    }
                }
            });
            menuButtons.add({ name: 'user' });
            menuButtons.add({
                name: 'about',
                originalAction: function () {
                    aboutDialog.dialog('open');
                }
            });
            menuButtons.add({
                name: 'timeleft',
                originalAction: function () {
                    menuButtons.toggleTimeLeft();
                }
            });
            menuButtons.add({
                name: 'more',
                originalAction: function () {
                    var tag = $('#vpl_ide_menuextra');
                    if (tag.is(":visible")) {
                        menuButtons.setText('more', 'more', VPLUtil.str('more'));
                        tag.hide();
                    } else {
                        menuButtons.setText('more', 'less', VPLUtil.str('less'));
                        tag.show();
                    }
                    VPLUtil.delay('updateMenu', updateMenu);
                    VPLUtil.delay('autoResizeTab', autoResizeTab);
                }
            });
            menuButtons.add({
                name: 'shrightpanel',
                icon: 'close-rightpanel',
                originalAction: function () {
                    if (resultContainer.vplVisible) {
                        resultContainer.hide();
                        resultContainer.vplVisible = false;
                        menuButtons.setText('shrightpanel', 'open-rightpanel', VPLUtil.str('shrightpanel'));
                    } else {
                        menuButtons.setText('shrightpanel', 'close-rightpanel', VPLUtil.str('shrightpanel'));
                        resultContainer.show();
                        resultContainer.vplVisible = true;
                    }
                    VPLUtil.delay('autoResizeTab', autoResizeTab);
                },
                bindKey: {
                    win: 'Ctrl-M',
                    mac: 'Ctrl-M'
                }
            });
            var rightpanelstyle = "position:absolute;right:0;top:60px;z-index:100;margin:3px";
            tr.append('<span style="' + rightpanelstyle + '">' + menuButtons.getHTML('shrightpanel') + '</span>');
            var rightPanelButton = $('#vpl_ide_shrightpanel');
            menuButtons.setText('shrightpanel', 'close-rightpanel', VPLUtil.str('shrightpanel'));

            rightPanelButton.button();
            rightPanelButton.css('padding', '0');
            $('#vpl_ide_shrightpanel.ui-button-text').css('padding', '0');
            rightPanelButton.on('click', function () {
                menuButtons.launchAction('shrightpanel');
            });
            rightPanelButton.hide();
            menu.addClass("ui-widget-header ui-corner-all");
            var menuHtml = "";
            menuHtml += menuButtons.getHTML('more');
            menuHtml += menuButtons.getHTML('save');
            menuHtml += "<span id='vpl_ide_mexecution'>";
            menuHtml += menuButtons.getHTML('run');
            menuHtml += menuButtons.getHTML('debug');
            menuHtml += menuButtons.getHTML('evaluate');
            menuHtml += menuButtons.getHTML('comments');
            menuHtml += menuButtons.getHTML('console');
            menuHtml += "</span> ";
            menuHtml += "<span id='vpl_ide_menuextra'>";
            menuHtml += "<span id='vpl_ide_file'>";
            // TODO autosave not implemented.
            menuHtml += menuButtons.getHTML('filelist');
            menuHtml += menuButtons.getHTML('new');
            menuHtml += menuButtons.getHTML('aihints');
            menuHtml += menuButtons.getHTML('generatetestcases');
            menuHtml += menuButtons.getHTML('rename');
            menuHtml += menuButtons.getHTML('delete');
            menuHtml += menuButtons.getHTML('import');
            menuHtml += menuButtons.getHTML('download');
            menuHtml += menuButtons.getHTML('resetfiles');
            menuHtml += menuButtons.getHTML('sort');
            menuHtml += menuButtons.getHTML('multidelete');
            menuHtml += menuButtons.getHTML('showparentfiles');
            menuHtml += menuButtons.getHTML('fontsize');
            menuHtml += menuButtons.getHTML('theme');
            menuHtml += "</span> ";
            // TODO print still not implemented.
            menuHtml += "<span id='vpl_ide_edit'>";
            menuHtml += menuButtons.getHTML('undo');
            menuHtml += menuButtons.getHTML('redo');
            menuHtml += menuButtons.getHTML('select_all');
            menuHtml += menuButtons.getHTML('find');
            menuHtml += menuButtons.getHTML('find_replace');
            menuHtml += menuButtons.getHTML('next');
            menuHtml += "</span> ";
            menuHtml += "</span> ";
            menuHtml += menuButtons.getHTML('fullscreen') + ' ';
            menuHtml += menuButtons.getHTML('about') + ' ';
            menuHtml += menuButtons.getHTML('user') + ' ';
            menuHtml += menuButtons.getHTML('timeleft');
            menuHtml += '<div class="clearfix"></div>';
            menu.append(menuHtml);
            $('#vpl_ide_more').button();
            $('#vpl_ide_save').button();
            $('#vpl_ide_menuextra').hide();
            $('#vpl_ide_file').controlgroup();
            $('#vpl_ide_edit').controlgroup();
            $('#vpl_ide_mexecution').controlgroup();
            $('#vpl_ide_fullscreen').button();
            $('#vpl_ide_acetheme').button();
            $('#vpl_ide_about').button();
            $('#vpl_ide_user').button().css('float', 'right').hide();
            $('#vpl_ide_timeleft').button().css('float', 'right').hide();
            $('#vpl_menu .ui-button').css('padding', '6px');
            $('#vpl_menu .ui-button-text').css('padding', '0');
            var alwaysActive = ['filelist', 'more', 'fullscreen', 'about', 'resetfiles',
                'download', 'comments', 'console', 'import',
                'fontsize', 'timeleft', 'aihints'];
            for (var i = 0; i < alwaysActive.length; i++) {
                menuButtons.enable(alwaysActive[i], true);
            }
            // Hide generatetestcases button for students
            if (!isTeacher) {
                $('#vpl_ide_generatetestcases').hide();
            }
            menuButtons.setExtracontent('user', options.username);
            menuButtons.setTimeLeft(options);
            updateMenu = function () {
                var i;
                var file = fileManager.currentFile();
                var nfiles = fileManager.length();
                if (nfiles) {
                    tabs.show();
                } else {
                    tabs.hide();
                }
                if (fileManager.isFileListVisible()) {
                    menuButtons.setText('filelist', 'filelistclose', VPLUtil.str('filelist'));
                } else {
                    menuButtons.setText('filelist', 'filelist', VPLUtil.str('filelist'));
                }
                var modified = fileManager.isModified();

                // Check CA mode requirements for submission
                var canSave = modified;
                var saveDisabledMessage = '';
                if (modified && options.isCAMode) {
                    // In CA mode, require at least 2 test cases to pass before allowing save/submit
                    if (passedTestCases < 2) {
                        canSave = false;
                        if (totalTestCases > 0) {
                            saveDisabledMessage = 'CA Mode: You must pass at least 2 test cases before submitting. ' +
                                'Currently passed: ' + passedTestCases + ' out of ' + totalTestCases;
                        } else {
                            saveDisabledMessage = 'CA Mode: Please run evaluation first and pass at least 2 test cases before submitting.';
                        }
                        // Update the save button with a tooltip/title
                        $('#vpl_ide_save').attr('title', saveDisabledMessage);
                        $('#vpl_ide_save').addClass('vpl_disabled_ca_mode');
                    } else {
                        $('#vpl_ide_save').attr('title', 'Save submission (CA Mode: ' +
                            passedTestCases + '/' + totalTestCases + ' tests passed)');
                        $('#vpl_ide_save').removeClass('vpl_disabled_ca_mode');
                    }
                } else {
                    $('#vpl_ide_save').attr('title', 'Save submission');
                    $('#vpl_ide_save').removeClass('vpl_disabled_ca_mode');
                }

                menuButtons.enable('save', canSave);

                // Display message to user if save is disabled due to CA requirements
                if (!canSave && saveDisabledMessage) {
                    // You can optionally show a status message
                    VPLUI.showIDEStatus(saveDisabledMessage);
                }

                var running = VPLUI.monitorRunning();
                if (running) {
                    menuButtons.setText('run', 'running');
                } else {
                    menuButtons.setText('run', 'run');
                }
                menuButtons.enable('run', !running && (!modified || options.example) && isOptionAllowed('run'));
                menuButtons.enable('debug', !running && (!modified || options.example) && isOptionAllowed('debug'));
                menuButtons.enable('evaluate', !running && (!modified || options.example) && isOptionAllowed('evaluate'));
                menuButtons.enable('download', !modified);
                menuButtons.enable('new', nfiles < maxNumberOfFiles);
                menuButtons.enable('sort', nfiles - minNumberOfFiles > 1);
                menuButtons.enable('multidelete', nfiles - minNumberOfFiles > 1);
                menuButtons.enable('showparentfiles', !modified);
                menuButtons.enable('theme', true);
                // Only enable generatetestcases for teachers/admins
                menuButtons.enable('generatetestcases', isTeacher && isOptionAllowed('generatetestcases'));
                var sel;
                if (!file || nfiles === 0) {
                    sel = ['rename', 'delete', 'undo', 'redo', 'select_all', 'find', 'find_replace', 'next'];
                    for (i = 0; i < sel.length; i++) {
                        menuButtons.enable(sel[i], false);
                    }
                    return;
                }
                menuButtons.enable('rename', file.getId() >= minNumberOfFiles && nfiles !== 0);
                menuButtons.enable('delete', file.getId() >= minNumberOfFiles && nfiles !== 0);
                menuButtons.enable('undo', file.hasUndo());
                menuButtons.enable('redo', file.hasRedo());
                menuButtons.enable('select_all', file.hasSelectAll());
                menuButtons.enable('find', file.hasFind());
                menuButtons.enable('find_replace', file.hasFindReplace());
                menuButtons.enable('next', file.hasNext());
                VPLUtil.delay('updateFileList', fileManager.updateFileList);
            };

            executionActions = {
                'open': updateMenu,
                'close': updateMenu,
                'getConsole': function () {
                    return lastConsole;
                },
                'setResult': self.setResult,
                'ajaxurl': options.ajaxurl,
                'run': function (content, coninfo, ws) {
                    var parsed = /^([^:]*):?(.*)/.exec(content);
                    var type = VPLUtil.sanitizeText(parsed[1]);
                    if (type == 'terminal' || type == 'webterminal') {
                        if (lastConsole && lastConsole.isOpen()) {
                            lastConsole.close();
                        }
                        lastConsole = terminal;
                        terminal.connect(coninfo.executionURL, function () {
                            ws.close();
                            focusCurrentFile();
                        });
                        if (type == 'webterminal') {
                            var URLfavicon = (coninfo.secure ? "https" : "http") + "://" + coninfo.server + ":" + coninfo.portToUse;
                            URLfavicon += "/favicon.ico";
                            var imgFavicon = $('<img>');
                            imgFavicon.attr('src', URLfavicon);
                            imgFavicon.attr('style', 'display:none');
                            $('body').append(imgFavicon);
                        }
                    } else if (type == 'vnc') {
                        if (lastConsole && lastConsole.isOpen()) {
                            lastConsole.close();
                        }
                        lastConsole = VNCClient;
                        VNCClient.connect(coninfo.secure, coninfo.server, coninfo.portToUse, coninfo.VNCpassword,
                            coninfo.executionPath, function () {
                                ws.close();
                                focusCurrentFile();
                            });
                    } else if (type == "browser") {
                        var URL = (coninfo.secure ? "https" : "http") + "://" + coninfo.server + ":" + coninfo.portToUse + "/";
                        URL += VPLUtil.sanitizeText(parsed[2]) + "/httpPassthrough";
                        if (isTeacher) {
                            URL += "?private";
                        }
                        var message = '<a href="' + URL + '" target="_blank">';
                        message += VPLUtil.str('open') + '</a>';
                        var options = {
                            width: 200,
                            icon: 'run',
                            title: VPLUtil.str('run'),
                        };
                        showMessage(message, options);
                    } else {
                        VPLUtil.log("Type of run error " + content, true);
                    }
                },
                'lastAction': false,
                'getLastAction': function () {
                    var ret = this.lastAction;
                    this.lastAction = false;
                    return ret;
                },
                'setLastAction': function (action) {
                    this.lastAction = action;
                }
            };

            tabs.on("tabsactivate", function () {
                fileManager.currentFile('focus');
                VPLUtil.delay('updateMenu', updateMenu);
                VPLUtil.delay('autoResizeTab', autoResizeTab);
            });

            // VPLIDE resize view control.
            var jw = $(window);
            jw.on('resize', autoResizeTab);
            // Save? before exit.
            if (!options.example) {
                jw.on('beforeunload', function () {
                    if (fileManager.isModified()) {
                        return str('changesNotSaved');
                    }
                    return undefined;
                });
            }
            fileManager = new FileManager();

            if ($('.ui-dialog:visible').length === 0) {
                autoResizeTab();
            }
            // Checks menu width every 1 sec as it can change without event.
            (function () {
                var oldMenuWidth = menu.width();
                /**
                 * Checks menu width change
                 */
                function checkMenuWidth() {
                    var newMenuWidth = menu.width();
                    if (oldMenuWidth != newMenuWidth) {
                        oldMenuWidth = newMenuWidth;
                        autoResizeTab();
                    }
                }
                checkMenuWidth();
                setInterval(checkMenuWidth, 1000);
            }());
            fileManager.resetModified();
            VPLUI.requestAction('load', 'loading', options, options.loadajaxurl)
                .done(function (response) {
                    var allOK = true;
                    var files = response.files;
                    var showFileList = false;
                    for (var i = 0; i < files.length; i++) {
                        var file = files[i];
                        var r = fileManager.addFile(file, false, updateMenu, showErrorMessage);
                        if (r) {
                            r.resetModified();
                            if (i < minNumberOfFiles || files.length <= 5) {
                                fileManager.open(r);
                            } else {
                                showFileList = true;
                            }
                        } else {
                            allOK = false;
                        }
                    }
                    tabs.tabs('option', 'active', 0);
                    if (response.compilationexecution) {
                        self.setResult(response.compilationexecution, false);
                    }
                    menuButtons.setTimeLeft(response);
                    if (response.comments > '') {
                        $('#vpl_ide_input_comments').val(response.comments);
                    }
                    if (allOK) {
                        fileManager.resetModified();
                    } else {
                        fileManager.setModified();
                    }
                    if (fileManager.length() === 0 && maxNumberOfFiles > 0) {
                        menuButtons.getAction('new')();
                    } else if (!options.saved) {
                        fileManager.setModified();
                    }
                    fileManager.setFontSize(options.fontSize);
                    fileManager.setVersion(response.version);
                    fileManager.fileListVisible(showFileList);
                    VPLUtil.afterAll('AfterLoadFiles', function () {
                        updateMenu();
                        autoResizeTab();
                        adjustTabsTitles(true);
                        if (fileManager.length() > 0) {
                            var file = fileManager.getFiles()[0];
                            file.open();
                            file.focus();
                        }
                    });
                })
                .fail(showErrorMessage);
        };
        window.VPLIDE = VPLIDE;
        return {
            init: function (rootId, options) {
                vplIdeInstance = new VPLIDE(rootId, options);
            }
        };
    }
);
