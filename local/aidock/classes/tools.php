<?php
/**
 * Status and links for the four AI tools.
 *
 * @package    local_aidock
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_aidock;

defined('MOODLE_INTERNAL') || die();

class tools {

    /** Installed? */
    protected static function installed(string $component): bool {
        return \core_component::get_component_directory($component) !== null
            && get_config($component, 'version') !== false;
    }

    /** EduGears LTI tool type id, or 0. */
    public static function edugears_typeid(): int {
        global $DB;
        if (!self::installed('local_edugears')) {
            return 0;
        }
        $sql = "SELECT id FROM {lti_types} WHERE " . $DB->sql_like('baseurl', ':u', false) . " OR " . $DB->sql_like('name', ':n', false);
        $ids = $DB->get_fieldset_sql($sql, ['u' => '%edugears%', 'n' => '%edugears%']);
        return $ids ? (int)reset($ids) : 0;
    }

    /**
     * Returns the four tools with their ready state and admin setup URL.
     */
    public static function all(): array {
        $out = [];
        $out['geniai'] = [
            'installed' => self::installed('local_geniai'),
            'ready' => self::installed('local_geniai') && strlen((string)get_config('local_geniai', 'apikey')) > 5
                && get_config('local_geniai', 'mode') !== 'none',
            'setup' => new \moodle_url('/admin/settings.php', ['section' => 'local_geniai']),
            'icon' => 'fa-robot', 'color' => '#7c3aed',
        ];
        $out['tutorai'] = [
            'installed' => self::installed('local_dttutor'),
            'ready' => self::installed('local_dttutor') && get_config('local_dttutor', 'enabled')
                && (!empty(get_config('aiprovider_datacurso', 'licensekey'))
                    || (class_exists('\\local_dttutorgemini\\client') && \local_dttutorgemini\client::is_configured())),
            'setup' => new \moodle_url('/admin/settings.php', ['section' => 'local_dttutor']),
            'icon' => 'fa-user-graduate', 'color' => '#0ea5e9',
        ];
        $out['edugears'] = [
            'installed' => self::installed('local_edugears'),
            'ready' => self::edugears_typeid() > 0,
            'setup' => new \moodle_url('/local/edugears/register.php'),
            'icon' => 'fa-gears', 'color' => '#16a34a',
        ];
        $out['raison'] = [
            'installed' => self::installed('local_corolair'),
            'ready' => self::installed('local_corolair') && (bool)get_config('local_corolair', 'setupcompleted'),
            'setup' => new \moodle_url('/local/corolair/setup.php'),
            'icon' => 'fa-comments', 'color' => '#f98012',
        ];
        // Orange circle: the local AI Course Tutor (Raison alternative). Raison stays installed, just not on the dock.
        if (self::installed('local_coursetutor') && get_config('local_aidock', 'coursetutorondock')) {
            unset($out['raison']);
            $out['coursetutor'] = [
                'installed' => true,
                'ready' => \local_coursetutor\gemini::is_configured(),
                'setup' => new \moodle_url('/admin/settings.php', ['section' => 'local_coursetutor']),
                'url' => '/local/coursetutor/index.php',
                'icon' => 'fa-comments', 'color' => '#f98012',
            ];
        }
        return array_filter($out, fn($t) => $t['installed']);
    }
}
