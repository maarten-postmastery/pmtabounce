<?php
/**
 * This is the language file for the 'pmtabounce' addon.
 *
 * @package Interspire_Addons
 * @subpackage Addons_pmtabounce
 */

/* 
 * Used in Settings::ShowSettingsPage, see also <name> in description.xml.
 */
define('LNG_Cron_Option_pmtabounce_Heading', 'Postmastery Bounce Processing');

define('LNG_Addon_pmtabounce_Directory', 'Directory were bounce logs are placed by PowerMTA after rotation:');
define('LNG_Addon_pmtabounce_After', 'After processing by the addon:');
define('LNG_Addon_pmtabounce_Delete', 'Delete bounce log (default rename)');
define('LNG_Addon_pmtabounce_Save', 'Save');

/* bounce rules for powermta categories */
define('LNG_Bounce_Rule_bad_mailbox', 'Unknown user at domain');
define('LNG_Bounce_Rule_inactive_mailbox', 'Disabled or inactive account');
define('LNG_Bounce_Rule_quota_issues', 'Mailbox quota exceeded');
define('LNG_Bounce_Rule_bad_domain', 'Invalid or non-existing domain');
define('LNG_Bounce_Rule_no_answer_from_host', 'Domain does not accept mail');
define('LNG_Bounce_Rule_relaying_issues', 'Server does not accept mail for this domain');
define('LNG_Bounce_Rule_routing_errors', 'No mail server found for domain');
define('LNG_Bounce_Rule_bad_connection', 'Remote mail server disconnected');
define('LNG_Bounce_Rule_invalid_sender', 'Sender address was rejected');
define('LNG_Bounce_Rule_spam_related', 'Rejected due to spam or reputation');
define('LNG_Bounce_Rule_virus_related', 'Rejected due to malware');
define('LNG_Bounce_Rule_content_related', 'Rejected for content reasons');
define('LNG_Bounce_Rule_policy_related', 'Rejected for policy reasons');
define('LNG_Bounce_Rule_bad_configuration', 'Local or remote configuration error');
define('LNG_Bounce_Rule_protocol_errors', 'SMTP protocol violation or error');
define('LNG_Bounce_Rule_message_expired', 'Message timed out in the queue');
define('LNG_Bounce_Rule_other', 'Error was not recognized');
