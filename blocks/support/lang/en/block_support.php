<?php
$string['pluginname'] = 'Support';
$string['support:addinstance'] = 'Add a new Support block';
$string['support:myaddinstance'] = 'Add a new Support block to Dashboard';
$string['support:view'] = 'View the Support block';
$string['supportbutton'] = 'Raise a Ticket';
$string['lsmmessage'] = 'Your Learner Success Manager is {$a->name}. Please connect by calling on +918748900900 or {$a->number} from your registered mobile number. Available between Monday to Saturday 10:00 AM to 6:00 PM';
$string['lsmmessage_onboarding'] = 'Your Support Manager will be from the Onboarding Team and will guide you. You can reach the team by calling 08062910210 from your registered mobile number. Support is available from Monday to Saturday, 10:00 AM to 6:00 PM (excluding public holidays).';
$string['sopbutton'] = 'Learner Escalation SOP';

// Settings.
$string['settings_endpoints'] = 'Helpdesk endpoints';
$string['settings_endpoints_desc'] = 'Where this site\'s support block sends users. These were hardcoded to Jain\'s helpdesk; the defaults keep that behaviour, so a site that sets nothing is unchanged. Set them to your own helpdesk, or hide the block if this site has none -- otherwise your users\' email addresses are sent to another institution\'s helpdesk.';
$string['setting_apibaseurl'] = 'Helpdesk API base URL';
$string['setting_apibaseurl_desc'] = 'Base URL of the helpdesk API used to mint a login token and sync the user, e.g. https://support.example.edu. Also used as the fallback destination when the token exchange fails.';
$string['setting_apikey'] = 'Helpdesk API key';
$string['setting_apikey_desc'] = 'Key passed to the helpdesk\'s auth endpoint to obtain a bearer token.';
$string['setting_portalticketurl'] = 'Ticket portal URL';
$string['setting_portalticketurl_desc'] = 'Where the "Raise a Ticket" link points when the block builds a direct portal link.';
$string['settings_display'] = 'Display';
$string['setting_showescalationsop'] = 'Show the Learner Escalation SOP button';
$string['setting_showescalationsop_desc'] = 'Adds a link to blocks/support/assets/SOP.pdf beside the ticket button. Turn this off on sites that do not use that document.';
$string['supportnotconfigured'] = 'The support helpdesk is not configured for this site. Please contact your administrator.';
