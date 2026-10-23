<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * file
 *
 * @package   local_helpdesk
 * @copyright 2025 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_helpdesk\model;

use context_system;

/**
 * Class category
 *
 * @package local_helpdesk\model
 */
class category {
    /** @var int */
    protected $id;
    /** @var int */
    protected $parentid = 0;
    /** @var string */
    protected $name;
    /** @var string */
    protected $description;
    /** @var int */
    protected $createdat;

    /**
     * category constructor.
     *
     * @param array|object $obj
     */
    public function __construct($obj) {
        foreach ($obj as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Function get_by_id
     *
     * @param int $categoryid
     *
     * @return category|null
     * @throws \dml_exception
     */
    public static function get_by_id($categoryid) {
        global $DB;
        $record = $DB->get_record("local_helpdesk_category", ["id" => $categoryid]);

        if ($record) {
            return new self($record);
        }

        return null;
    }

    /**
     * Function get_all
     *
     * @param array|string $wheres
     * @param array $params
     * @param string $order
     *
     * @return array
     * @throws \dml_exception
     */
    public static function get_all($wheres = null, $params = [], $order = null) {
        return model_base::get_all("local_helpdesk_category", self::class, $wheres, $params, $order);
    }

    /**
     * Function get_all_parents - returns only root/parent categories (parentid = 0)
     *
     * @return array
     * @throws \dml_exception
     */
    public static function get_all_parents() {
        return self::get_all(["parentid = 0"], [], "name ASC");
    }

    /**
     * Function get_subcategories - returns sub-categories for a given parent
     *
     * @param int $parentid
     *
     * @return array
     * @throws \dml_exception
     */
    public static function get_subcategories($parentid) {
        return self::get_all(["parentid = :parentid"], ["parentid" => intval($parentid)], "name ASC");
    }

    /**
     * Function has_subcategories - check if this category has sub-categories
     *
     * @return bool
     * @throws \dml_exception
     */
    public function has_subcategories() {
        global $DB;
        return $DB->record_exists("local_helpdesk_category", ["parentid" => $this->id]);
    }

    /**
     * Function get_all_with_depth - returns ALL categories as a flat ordered array with depth info.
     * Categories are ordered hierarchically (parent first, then children, recursively).
     *
     * @return array  Each element: ['category' => category, 'depth' => int]
     * @throws \dml_exception
     */
    public static function get_all_with_depth() {
        $allcats = self::get_all(null, [], "name ASC");

        // Build parentid => [children] map.
        $childrenmap = [];
        foreach ($allcats as $cat) {
            $pid = $cat->get_parentid();
            if (!isset($childrenmap[$pid])) {
                $childrenmap[$pid] = [];
            }
            $childrenmap[$pid][] = $cat;
        }

        $result = [];
        self::flatten_tree($childrenmap, 0, 0, $result);
        return $result;
    }

    /**
     * Function flatten_tree - recursively flattens a category tree.
     *
     * @param array $childrenmap  parentid => [category, ...]
     * @param int $parentid
     * @param int $depth
     * @param array $result       Pass by reference
     */
    private static function flatten_tree($childrenmap, $parentid, $depth, &$result) {
        if (!isset($childrenmap[$parentid])) {
            return;
        }
        foreach ($childrenmap[$parentid] as $cat) {
            $result[] = ["category" => $cat, "depth" => $depth];
            self::flatten_tree($childrenmap, $cat->get_id(), $depth + 1, $result);
        }
    }

    /**
     * Function collect_descendant_ids - collect all IDs that are descendants of $parentid.
     *
     * @param array $allcats  All category objects
     * @param int $parentid
     * @param array $ids      Pass by reference
     */
    private static function collect_descendant_ids($allcats, $parentid, &$ids) {
        foreach ($allcats as $cat) {
            if ((int)$cat->get_parentid() === (int)$parentid) {
                $ids[] = $cat->get_id();
                self::collect_descendant_ids($allcats, $cat->get_id(), $ids);
            }
        }
    }

    /**
     * Function get_parent_options - returns an options array for the parent dropdown.
     * Excludes $excludeid and all its descendants to prevent circular references.
     *
     * @param int $excludeid  The category being edited (0 = creating new)
     *
     * @return array  [id => label, ...]
     * @throws \dml_exception
     */
    public static function get_parent_options($excludeid = 0) {
        $flatlist = self::get_all_with_depth();

        // Collect IDs to exclude (the category itself and all its descendants).
        $excludeids = [];
        if ($excludeid > 0) {
            $excludeids[] = $excludeid;
            $allcats = self::get_all();
            self::collect_descendant_ids($allcats, $excludeid, $excludeids);
        }

        $options = [0 => get_string("parentcategory_none", "local_helpdesk")];
        foreach ($flatlist as $item) {
            $cat = $item["category"];
            $depth = $item["depth"];
            if (!in_array($cat->get_id(), $excludeids)) {
                $prefix = str_repeat("\u{2014} ", $depth); // — repeated by depth.
                $options[$cat->get_id()] = $prefix . $cat->get_name();
            }
        }
        return $options;
    }

    /**
     * Function save
     *
     * @return bool|int
     * @throws \dml_exception
     */
    public function save() {
        global $DB;

        if ($this->id) {
            return $DB->update_record("local_helpdesk_category", get_object_vars($this));
        } else {
            return $this->id = $DB->insert_record("local_helpdesk_category", get_object_vars($this));
        }
    }

    /**
     * Function delete
     *
     * @return bool
     * @throws \dml_exception
     */
    public function delete() {
        global $DB;

        require_capability("local/helpdesk:categorydelete", context_system::instance());

        return $DB->delete_records("local_helpdesk_category", ["id" => $this->id]);
    }

    // Getters.

    /**
     * Function get_id
     *
     * @return mixed
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Function get_parentid
     *
     * @return int
     */
    public function get_parentid() {
        return (int)$this->parentid;
    }

    /**
     * Function set_parentid
     *
     * @param int $parentid
     */
    public function set_parentid($parentid) {
        $this->parentid = (int)$parentid;
    }

    /**
     * Function is_subcategory
     *
     * @return bool
     */
    public function is_subcategory() {
        return $this->parentid > 0;
    }

    /**
     * Function create_role
     *
     * @return int
     * @throws \coding_exception
     * @throws \dml_exception
     */
    public function get_role_id() {
        global $DB, $USER;

        $rolename = get_string("pluginname", "local_helpdesk");
        $roleshortname = get_string("pluginname", "local_helpdesk");
        $roledescription = get_string("category_role_description", "local_helpdesk");
        $rolearchetype = "";

        $role = $DB->get_record("role", ["shortname" => $roleshortname]);
        if ($role) {
            return $role->id;
        }

        $roleid = create_role($rolename, $roleshortname, $roledescription, $rolearchetype);
        set_role_contextlevels($roleid, [CONTEXT_SYSTEM]);

        $capabilities = [
            "contextid" => context_system::instance()->id,
            "roleid" => $roleid,
            "capability" => "local/helpdesk:ticketmanage",
            "permission" => 1,
            "timemodified" => time(),
            "modifierid" => $USER->id,
        ];
        $DB->insert_record("role_capabilities", $capabilities);
        $capabilities = [
            "contextid" => context_system::instance()->id,
            "roleid" => $roleid,
            "capability" => "local/kopere_dashboard:ajax",
            "permission" => 1,
            "timemodified" => time(),
            "modifierid" => $USER->id,
        ];
        $DB->insert_record("role_capabilities", $capabilities);

        return $roleid;
    }

    /**
     * Function get_name
     *
     * @return mixed
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Function get_description
     *
     * @return mixed
     */
    public function get_description() {
        return $this->description;
    }

    /**
     * Function get_createdat
     *
     * @return mixed
     */
    public function get_createdat() {
        return $this->createdat;
    }

    // Setters.

    /**
     * Function set_name
     *
     * @param string $name
     */
    public function set_name($name) {
        $this->name = $name;
    }

    /**
     * Function set_description
     *
     * @param string $description
     */
    public function set_description($description) {
        $this->description = $description;
    }
}
