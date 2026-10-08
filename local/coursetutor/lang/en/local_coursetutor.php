<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Strings.
 *
 * @package   local_coursetutor
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AI Course Tutor';
$string['coursetutor:use'] = 'Chat with AI course tutors';
$string['coursetutor:manage'] = 'Create and manage AI course tutors';
$string['intro'] = 'AI tutors built by your teachers from the course material. Ask questions, get explanations and practise.';
$string['managetutors'] = 'Manage tutors';
$string['manageintro'] = 'Create tutors for this course. Each tutor reads the course material (summaries, Pages, Books, Labels and activity descriptions) and follows your instructions. Learners open it with the orange circle in the course.';
$string['addtutor'] = 'Create a tutor';
$string['edittutor'] = 'Edit tutor';
$string['tutorname'] = 'Tutor name';
$string['instructions'] = 'Instructions';
$string['instructions_help'] = 'How the tutor should behave, e.g. tone, what to focus on, whether to give full answers or only hints.';
$string['knowledge'] = 'Extra knowledge';
$string['knowledge_help'] = 'Optional extra notes, FAQs or explanations the tutor should know in addition to the course material.';
$string['usecontent'] = 'Use course material';
$string['enabled'] = 'Enabled';
$string['disabled'] = 'Disabled';
$string['chats'] = 'Questions asked';
$string['try'] = 'Try it';
$string['saved'] = 'Tutor saved';
$string['defaultname'] = 'Course Tutor';
$string['defaultinstructions'] = 'Explain concepts simply with examples. For exercises, give hints first and the full solution only if the learner asks again.';
$string['notutors'] = 'Your teacher has not created an AI tutor for this course yet.';
$string['notutorsteacher'] = 'No tutor yet for this course. Use "Manage tutors" to create one in a minute.';
$string['notutorsyet'] = 'No tutors yet.';
$string['nonefound'] = 'No AI tutors are available in your courses yet.';
$string['askplaceholder'] = 'Ask a question about this course…';
$string['send'] = 'Send';
$string['thinking'] = 'Thinking…';
$string['hello'] = 'Hi! I am {name}. Ask me anything about this course.';
$string['emptymessage'] = 'Please type a question.';
$string['notconfigured'] = 'The AI service is not configured yet (no Gemini API key).';
$string['aiunavailable'] = 'The AI service is busy right now. Please try again in a moment.';
$string['aierror'] = 'AI error: {$a}';
$string['apikey'] = 'Gemini API key';
$string['apikey_desc'] = 'Leave empty to reuse the Gemini key configured for GeniAI.';
$string['model'] = 'Gemini model';
$string['model_desc'] = 'Falls back to gemini-3.5-flash-lite when busy.';
$string['privacy:metadata:local_coursetutor_msg'] = 'Questions learners ask a course tutor and the answers, so the chat can be shown again.';
$string['privacy:metadata:local_coursetutor_msg:userid'] = 'The learner.';
$string['privacy:metadata:local_coursetutor_msg:content'] = 'The message text.';
$string['privacy:metadata:gemini'] = 'Questions and course material are sent to Google Gemini to generate answers.';

// AI Question Generator.
$string['qgen'] = 'AI Question Generator';
$string['qgenintro'] = 'Choose any mix of question types and difficulty levels (one row each), generate, check the questions, then save the ones you want into this course\'s question bank. Each question is tagged Easy / Medium / Hard.';
$string['qgentopic'] = 'Topic / content (optional)';
$string['qgentopic_ph'] = 'e.g. Unit 4: Capital Budgeting - NPV, IRR, payback period. You can also paste notes here.';
$string['qgenusecontent'] = 'Also use this course\'s material (pages, books, section summaries)';
$string['qgentype'] = 'Question type';
$string['qgenlevel'] = 'Difficulty';
$string['qgencount'] = 'How many';
$string['qgenaddrow'] = 'Add row';
$string['qgentotal'] = 'Total';
$string['qgengenerate'] = 'Generate questions';
$string['qgenwait'] = 'Generating… this can take up to a minute.';
$string['qgenpreview'] = 'Generated questions ({$a})';
$string['qgenkeep'] = 'Keep';
$string['qgensave'] = 'Save selected to question bank';
$string['qgensaved'] = '{$a->n} question(s) saved to the question bank category "{$a->cat}".';
$string['qgenopenbank'] = 'Open question bank';
$string['qgenmodel'] = 'Model answer';
$string['qgennorows'] = 'Add at least one row with a number of questions.';
$string['qgennosource'] = 'Enter a topic or tick "use this course\'s material".';
$string['qgenempty'] = 'The AI did not return usable questions. Please try again.';
$string['qgennothingchosen'] = 'Tick at least one question to save.';
$string['qgenimportfailed'] = 'Saving to the question bank failed: {$a}';
$string['qtype_truefalse'] = 'True / False';
$string['qtype_multichoice'] = 'Multiple choice (one answer)';
$string['qtype_multiselect'] = 'Multiple select (several answers)';
$string['qtype_shortanswer'] = 'Short answer';
$string['qtype_matching'] = 'Matching';
$string['qtype_essay'] = 'Essay';
$string['level_easy'] = 'Easy';
$string['level_medium'] = 'Medium';
$string['level_hard'] = 'Hard';
$string['qgenedithelp'] = 'Edit anything below before saving: question text, options, the correct answer, difficulty and explanation. Untick "Keep" to leave a question out. After saving you can still edit each question in the question bank.';
$string['qgenqtext'] = 'Question';
$string['qgencorrect'] = 'Correct answer';
$string['qgenoptions1'] = 'Options (select the one correct answer)';
$string['qgenoptionsm'] = 'Options (tick every correct answer)';
$string['qgenanswers'] = 'Accepted answers (separate with " / ")';
$string['qgenpairs'] = 'Matching pairs';
$string['qgenexplain'] = 'Explanation (shown after answering)';
$string['qgendiscard'] = 'Discard all';
$string['qgeninvalid'] = 'Please complete this question before saving: "{$a}…"';
$string['qgenhistory'] = 'Saved questions history ({$a})';
$string['qgenhistoryempty'] = 'No AI-generated questions have been saved for this course yet.';
$string['qgenhistoryhelp'] = 'Questions you saved earlier, newest first, grouped by when they were saved. Click Preview to see the question and its answers here, Try it to attempt it as a student would, or Edit to change it.';
$string['qgenhistorycount'] = '{$a} question(s)';
$string['qgenshow'] = 'Preview';
$string['qgenhide'] = 'Hide';
$string['qgentry'] = 'Try it';
$string['qgensection'] = 'Generated set {$a}';
$string['qgenshowall'] = 'Preview all questions';
$string['qgenhideall'] = 'Hide all';
$string['qgenanswerkey'] = 'Answer key';
$string['qgendownload'] = 'Download';
$string['qgenfmt_doc'] = 'Word (.doc)';
$string['qgenfmt_pdf'] = 'PDF';
$string['qgenfmt_xml'] = 'Moodle XML (re-import)';
$string['qgenwithkey'] = 'Questions + answer key';
$string['qgenpaperonly'] = 'Questions only';
$string['qgendelete'] = 'Delete this question';
$string['qgendeleteset'] = 'Delete this set';
$string['qgendeleteconfirm'] = 'Delete this question from the question bank? This cannot be undone.';
$string['qgendeletesetconfirm'] = 'Delete all {$a} questions of this set from the question bank? This cannot be undone.';
$string['qgendeleted'] = '{$a} question(s) deleted.';
