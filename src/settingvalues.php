<?php
// settingvalues.php -- HotCRP conference settings management helper classes
// Copyright (c) 2006-2019 Eddie Kohler; see LICENSE.

// setting information
class Si {
    public $name;
    public $base_name;
    public $json_name;
    public $title;
    public $group;
    public $type;
    public $internal;
    public $extensible;
    public $storage_type;
    public $storage;
    public $optional = false;
    public $values;
    public $json_values;
    public $size;
    public $placeholder;
    public $parser_class;
    public $novalue = false;
    public $disabled = false;
    public $invalid_value;
    public $default_value;
    public $autogrow;
    public $ifnonempty;
    public $message_default;
    public $message_context_setting;
    public $date_backup;

    static public $option_is_value = [];

    const SI_VALUE = 1;
    const SI_DATA = 2;
    const SI_SLICE = 4;
    const SI_OPT = 8;
    const SI_NEGATE = 16;

    const X_SIMPLE = 1;
    const X_WORD = 2;

    static private $type_storage = [
        "emailheader" => self::SI_DATA,
        "emailstring" => self::SI_DATA,
        "htmlstring" => self::SI_DATA,
        "simplestring" => self::SI_DATA,
        "string" => self::SI_DATA,
        "tag" => self::SI_DATA,
        "tagbase" => self::SI_DATA,
        "taglist" => self::SI_DATA,
        "urlstring" => self::SI_DATA
    ];

    static private $key_storage = [
        "autogrow" => "is_bool",
        "date_backup" => "is_string",
        "disabled" => "is_bool",
        "ifnonempty" => "is_string",
        "internal" => "is_bool",
        "invalid_value" => "is_string",
        "json_values" => "is_array",
        "message_context_setting" => "is_string",
        "message_default" => "is_string",
        "novalue" => "is_bool",
        "optional" => "is_bool",
        "parser_class" => "is_string",
        "placeholder" => "is_string",
        "size" => "is_int",
        "storage" => "is_string",
        "title" => "is_string",
        "title" => "is_string",
        "type" => "is_string",
        "values" => "is_array"
    ];

    private function store($key, $j, $jkey, $typecheck) {
        if (isset($j->$jkey) && call_user_func($typecheck, $j->$jkey))
            $this->$key = $j->$jkey;
        else if (isset($j->$jkey))
            trigger_error("setting {$j->name}.$jkey format error");
    }

    function __construct($j) {
        assert(!preg_match('/_(?:\$|n|m?\d+)\z/', $j->name));
        $this->name = $this->base_name = $this->json_name = $this->title = $j->name;
        if (isset($j->json_name) && ($j->json_name === false || is_string($j->json_name)))
            $this->json_name = $j->json_name;
        foreach ((array) $j as $k => $v) {
            if (isset(self::$key_storage[$k]))
                $this->store($k, $j, $k, self::$key_storage[$k]);
        }
        if (isset($j->default_value) && (is_int($j->default_value) || is_string($j->default_value)))
            $this->default_value = $j->default_value;
        if (isset($j->extensible)) {
            if ($j->extensible === true || $j->extensible === "simple")
                $this->extensible = self::X_SIMPLE;
            else if ($j->extensible === "word")
                $this->extensible = self::X_WORD;
            else if ($j->extensible === false)
                $this->extensible = false;
            else
                trigger_error("setting {$j->name}.extensible format error");
        }
        if (isset($j->group)) {
            if (is_string($j->group))
                $this->group = $j->group;
            else if (is_array($j->group)) {
                $this->group = [];
                foreach ($j->group as $g)
                    if (is_string($g))
                        $this->group[] = $g;
                    else
                        trigger_error("setting {$j->name}.group format error");
            }
        }

        if (!$this->type && $this->parser_class)
            $this->type = "special";

        $s = $this->storage ? : $this->name;
        $dot = strpos($s, ".");
        if ($dot === 3 && substr($s, 0, 3) === "opt") {
            $this->storage_type = self::SI_DATA | self::SI_OPT;
        } else if ($dot === 3 && substr($s, 0, 3) === "ova") {
            $this->storage_type = self::SI_VALUE | self::SI_OPT;
            $this->storage = "opt." . substr($s, 4);
        } else if ($dot === 3 && substr($s, 0, 3) === "val") {
            $this->storage_type = self::SI_VALUE | self::SI_SLICE;
            $this->storage = substr($s, 4);
        } else if ($dot === 3 && substr($s, 0, 3) === "dat") {
            $this->storage_type = self::SI_DATA | self::SI_SLICE;
            $this->storage = substr($s, 4);
        } else if ($dot === 6 && substr($s, 0, 6) === "negval") {
            $this->storage_type = self::SI_VALUE | self::SI_SLICE | self::SI_NEGATE;
            $this->storage = substr($s, 7);
        } else if (isset(self::$type_storage[$this->type])) {
            $this->storage_type = self::$type_storage[$this->type];
        } else {
            $this->storage_type = self::SI_VALUE;
        }
        if ($this->storage_type & self::SI_OPT) {
            $is_value = !!($this->storage_type & self::SI_VALUE);
            $oname = substr($this->storage ? : $this->name, 4);
            if (!isset(self::$option_is_value[$oname]))
                self::$option_is_value[$oname] = $is_value;
            if (self::$option_is_value[$oname] != $is_value)
                error_log("$oname: conflicting option_is_value");
        }

        // defaults for size, placeholder
        if (str_ends_with($this->type, "date")) {
            if ($this->size === null)
                $this->size = 32;
            if ($this->placeholder === null)
                $this->placeholder = "N/A";
        } else if ($this->type == "grace") {
            if ($this->size === null)
                $this->size = 15;
            if ($this->placeholder === null)
                $this->placeholder = "none";
        }
    }

    function is_date() {
        return str_ends_with($this->type, "date");
    }

    function storage() {
        return $this->storage ? : $this->name;
    }

    function is_interesting(SettingValues $sv) {
        if (!$this->group) {
            error_log("$this->name: missing group");
            return false;
        }
        $groups = $this->group;
        foreach (is_string($groups) ? [$groups] : $groups as $g) {
            if ($sv->group_is_interesting($g))
                return true;
        }
        return false;
    }

    function active_value() {
        return !$this->internal && !$this->disabled && !$this->novalue
            && $this->type && $this->type !== "none";
    }

    static function get($conf, $name, $k = null) {
        if (isset($conf->_setting_info[$name]))
            $si = $conf->_setting_info[$name];
        else if (!preg_match('{\A(.*)(_(?:[^_\s]+))\z}', $name, $m)
                 || !isset($conf->_setting_info[$m[1]]))
            return null;
        else {
            $base_si = $conf->_setting_info[$m[1]];
            if (!$base_si->extensible
                || ($base_si->extensible === self::X_SIMPLE
                    && !preg_match('{\A_(?:\$|n|m?\d+)\z}', $m[2]))) {
                if ($base_si->extensible !== false)
                    error_log("$name: cloning non-extensible setting $base_si->name, " . json_encode(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)));
                return null;
            }
            $si = clone $base_si;
            $si->name = $name;
            if ($si->storage)
                $si->storage .= $m[2];
            if ($si->extensible === self::X_WORD)
                $si->title .= " (" . htmlspecialchars(substr($m[2], 1)) . ")";
            if ($si->message_context_setting
                && str_starts_with($si->message_context_setting, "+"))
                $si->message_context_setting .= $m[2];
            $conf->_setting_info[$name] = $si;
        }
        return $k ? $si->$k : $si;
    }


    static private function read($info, $text, $fname) {
        $j = json_decode($text, true);
        if (is_array($j))
            $info = array_replace_recursive($info, $j);
        else if (json_last_error() !== JSON_ERROR_NONE) {
            Json::decode($text); // our JSON decoder provides error positions
            trigger_error("$fname: Invalid JSON, " . Json::last_error_msg());
        }
        return $info;
    }

    static function initialize(Conf $conf) {
        $last_problem = 0;
        $hook = function ($v, $k, $landmark) use ($conf, &$last_problem) {
            if (is_object($v) && isset($v->name) && is_string($v->name)) {
                $conf->_setting_info[] = $v;
                return true;
            } else
                return false;
        };

        $conf->_setting_info = [];
        expand_json_includes_callback(["etc/settinginfo.json"], $hook);
        if (($olist = $conf->opt("settingInfo")))
            expand_json_includes_callback($olist, $hook);
        usort($conf->_setting_info, function ($a, $b) {
            return strcmp($a->name, $b->name) ? : Conf::xt_priority_compare($a, $b);
        });

        $all = [];
        $nall = count($conf->_setting_info);
        for ($i = 0; $i < $nall; ++$i) {
            $j = $conf->_setting_info[$i];
            while ($i + 1 < $nall
                   && isset($j->merge)
                   && $j->merge
                   && $j->name === $conf->_setting_info[$i + 1]->name) {
                $overlay = $j;
                unset($overlay->merge);
                $j = $conf->_setting_info[$i + 1];
                object_replace_recursive($j, $overlay);
                ++$i;
            }
            if ($conf->xt_allowed($j) && !isset($all[$j->name])) {
                Conf::xt_resolve_require($j);
                $class = get_s($j, "setting_class", "Si");
                $all[$j->name] = new $class($j);
            }
        }
        $conf->_setting_info = array_reverse($all);
    }
}

class SettingParser {
    function parse(SettingValues $sv, Si $si) {
        return false;
    }
    function unparse_json(SettingValues $sv, Si $si, &$j) {
    }
    function save(SettingValues $sv, Si $si) {
    }

    static function parse_interval($v) {
        $t = 0;
        $v = trim($v);
        if ($v === ""
            || strtoupper($v) === "N/A"
            || strtoupper($v) === "NONE"
            || $v === "0")
            return -1;
        if (ctype_digit($v))
            return $v * 60;
        if (preg_match('/\A\s*([\d]+):(\d+\.?\d*|\.\d+)\s*\z/', $v, $m))
            return $m[1] * 60 + $m[2];
        if (preg_match('/\A\s*(\d+\.?\d*|\.\d+)\s*y(?:ears?|rs?|)(?![a-z])/i', $v, $m)) {
            $t += $m[1] * 3600 * 24 * 365;
            $v = substr($v, strlen($m[0]));
        }
        if (preg_match('/\A\s*(\d+\.?\d*|\.\d+)\s*mo(?:nths?|ns?|s|)(?![a-z])/i', $v, $m)) {
            $t += $m[1] * 3600 * 24 * 30;
            $v = substr($v, strlen($m[0]));
        }
        if (preg_match('/\A\s*(\d+\.?\d*|\.\d+)\s*w(?:eeks?|ks?|)(?![a-z])/i', $v, $m)) {
            $t += $m[1] * 3600 * 24 * 7;
            $v = substr($v, strlen($m[0]));
        }
        if (preg_match('/\A\s*(\d+\.?\d*|\.\d+)\s*d(?:ays?|)(?![a-z])/i', $v, $m)) {
            $t += $m[1] * 3600 * 24;
            $v = substr($v, strlen($m[0]));
        }
        if (preg_match('/\A\s*(\d+\.?\d*|\.\d+)\s*h(?:rs?|ours?|)(?![a-z])/i', $v, $m)) {
            $t += $m[1] * 3600;
            $v = substr($v, strlen($m[0]));
        }
        if (preg_match('/\A\s*(\d+\.?\d*|\.\d+)\s*m(?:inutes?|ins?|)(?![a-z])/i', $v, $m)) {
            $t += $m[1] * 60;
            $v = substr($v, strlen($m[0]));
        }
        if (preg_match('/\A\s*(\d+\.?\d*|\.\d+)\s*s(?:econds?|ecs?|)(?![a-z])/i', $v, $m)) {
            $t += $m[1];
            $v = substr($v, strlen($m[0]));
        }
        if (trim($v) == "")
            return $t;
        else
            return false;
    }
}

class SettingValues extends MessageSet {
    public $conf;
    public $user;
    public $interesting_groups = [];
    public $all_interesting;

    private $parsers = [];
    private $saved_si = [];
    private $cleanup_callbacks = [];
    public $need_lock = [];
    public $changes = [];

    public $req = array();
    public $req_files = array();
    public $savedv = array();
    public $explicit_oldv = array();
    private $hint_status = array();
    private $has_req = array();
    private $null_mailer;

    private $_gxt = null;

    function __construct(Contact $user) {
        parent::__construct();
        $this->conf = $user->conf;
        $this->user = $user;
        // maybe set $Opt["contactName"] and $Opt["contactEmail"]
        $this->conf->site_contact();
        // maybe initialize _setting_info
        if ($this->conf->_setting_info === null)
            Si::initialize($this->conf);
    }
    static function make_request(Contact $user, $qreq) {
        $sv = new SettingValues($user);
        foreach ($qreq as $k => $v)
            $sv->set_req($k, $v);
        if ($qreq instanceof Qrequest) {
            foreach ($qreq->files() as $f => $finfo)
                $sv->req_files[$f] = $finfo;
        }
        return $sv;
    }
    function set_req($k, $v) {
        $this->req[$k] = $v;
        if (preg_match('/\A(?:has_)?(\S+?)(|_n|_m?\d+)\z/', $k, $m)) {
            if (!isset($this->has_req[$m[1]]))
                $this->has_req[$m[1]] = [$m[2]];
            else if (!in_array($m[2], $this->has_req[$m[1]]))
                $this->has_req[$m[1]][] = $m[2];
        }
    }
    function session_highlight() {
        foreach ($this->user->session("settings_highlight", []) as $f => $v)
            $this->msg($f, null, $v);
        $this->user->save_session("settings_highlight", null);
    }


    private function gxt() {
        if ($this->_gxt === null)
            $this->_gxt = new GroupedExtensions($this->user, ["etc/settinggroups.json"], $this->conf->opt("settingGroups"));
        return $this->_gxt;
    }
    function canonical_group($g) {
        return $this->gxt()->canonical_group(strtolower($g));
    }
    function is_titled_group($g) {
        $gj = $this->gxt()->get($g);
        return $gj && $gj->name == $gj->group && isset($gj->title);
    }
    function group_anchorid($g) {
        $gj = $this->gxt()->get($g);
        return $gj && isset($gj->anchorid) ? $gj->anchorid : null;
    }
    function group_titles() {
        return array_map(function ($gj) { return $gj->title; }, $this->gxt()->groups());
    }
    function group_members($g) {
        return $this->gxt()->members(strtolower($g));
    }
    function mark_interesting_group($g) {
        foreach ($this->group_members($g) as $gj) {
            $this->interesting_groups[$gj->name] = true;
            foreach ($gj->synonym as $syn)
                $this->interesting_groups[$syn] = true;
        }
    }
    function crosscheck() {
        foreach ($this->gxt()->all() as $gj) {
            if (isset($gj->crosscheck_callback)) {
                Conf::xt_resolve_require($gj);
                call_user_func($gj->crosscheck_callback, $this, $gj);
            }
        }
    }
    function render_group($g) {
        $this->gxt()->start_render(3, "settings");
        foreach ($this->group_members($g) as $gj)
            $this->gxt()->render($gj, [$this, $gj]);
        $this->gxt()->end_render();
    }


    function use_req() {
        return $this->has_error();
    }
    function error_at($field, $html = false) {
        if (is_array($field)) {
            foreach ($field as $f)
                $this->error_at($f, $html);
        } else {
            $fname = $field instanceof Si ? $field->name : $field;
            parent::error_at($fname, $html);
        }
    }
    function warning_at($field, $html = false) {
        if (is_array($field)) {
            foreach ($field as $f)
                $this->warning_at($f, $html);
        } else {
            $fname = $field instanceof Si ? $field->name : $field;
            parent::warning_at($fname, $html);
        }
    }
    private function report_mx(&$msgs, &$lastmsg, $mx) {
        $t = $mx[1];
        if ($mx[2] === MessageSet::WARNING)
            $t = "Warning: " . $t;
        $loc = null;
        if ($mx[0] && ($si = Si::get($this->conf, $mx[0])) && $si->title)
            $loc = htmlspecialchars($si->title);
        if ($lastmsg && $lastmsg[0] === $t) {
            if ($lastmsg[1])
                $loc = $loc ? $lastmsg[1] . ", " . $loc : $lastmsg[1];
            $msgs[count($msgs) - 1] = $loc ? $loc . ": " . $t : $t;
        } else
            $msgs[] = $loc ? $loc . ": " . $t : $t;
        $lastmsg = [$t, $loc];
    }
    function report($is_update = false) {
        $msgs = [];
        if ($is_update && $this->has_error())
            $msgs[] = "Your changes were not saved. Please fix these errors and try again.";
        $lastmsg = null;
        foreach ($this->messages(true) as $mx)
            $this->report_mx($msgs, $lastmsg, $mx);
        if (!empty($msgs) && $this->has_error())
            Conf::msg_error($msgs, true);
        else if (!empty($msgs))
            Conf::msg_warning($msgs, true);
    }
    function parser(Si $si) {
        if (($class = $si->parser_class)) {
            if (!isset($this->parsers[$class]))
                $this->parsers[$class] = new $class($this, $si);
            return $this->parsers[$class];
        } else
            return null;
    }
    function group_is_interesting($g) {
        return isset($this->interesting_groups[$g]);
    }

    static function add_class($c1, $c2) {
        if ((string) $c1 !== "" && (string) $c2 !== "")
            return $c1 . " " . $c2;
        else
            return (string) $c1 !== "" ? $c1 : $c2;
    }

    function label($name, $html, $label_js = null) {
        $name1 = is_array($name) ? $name[0] : $name;
        if ($label_js
            && (get($label_js, "class") === false
                || get($label_js, "no_control_class"))) {
            unset($label_js["no_control_class"]);
        } else {
            foreach (is_array($name) ? $name : array($name) as $n) {
                if (($sc = $this->control_class($n))) {
                    $label_js["class"] = self::add_class($sc, get_s($label_js, "class"));
                    break;
                }
            }
        }
        $post = "";
        if (($pos = strpos($html, "<input")) !== false)
            list($html, $post) = [substr($html, 0, $pos), substr($html, $pos)];
        return Ht::label($html, $name1, $label_js) . $post;
    }
    function sjs($name, $js = array()) {
        $x = ["id" => $name];
        if (Si::get($this->conf, $name, "disabled"))
            $x["disabled"] = true;
        foreach ($js ? : [] as $k => $v)
            $x[$k] = $v;
        if ($this->has_problem_at($name))
            $x["class"] = $this->control_class($name, get($x, "class"));
        return $x;
    }

    function si($name) {
        $si = Si::get($this->conf, $name);
        if (!$si)
            error_log(caller_landmark(2) . ": setting $name: missing information");
        return $si;
    }
    private function req_has($xname, $suffix) {
        $x = get($this->req, "has_$xname$suffix");
        return $x && $x !== "false";
    }
    function req_si(Si $si) {
        $xname = str_replace(".", "_", $si->name);
        $xsis = [];
        foreach (get($this->has_req, $xname, []) as $suffix) {
            $xsi = $this->si($si->name . $suffix);
            if ($xsi->parser_class)
                $has_value = $this->req_has($xname, $suffix);
            else
                $has_value = isset($this->req["$xname$suffix"])
                    || ($this->req_has($xname, $suffix)
                        && in_array($xsi->type, ["cdate", "checkbox"]));
            if ($has_value)
                $xsis[] = $xsi;
        }
        return $xsis;
    }

    function curv($name, $default_value = null) {
        return $this->si_curv($this->si($name), $default_value);
    }
    function oldv($name, $default_value = null) {
        return $this->si_oldv($this->si($name), $default_value);
    }
    function reqv($name, $default_value = null) {
        $name = str_replace(".", "_", $name);
        return get($this->req, $name, $default_value);
    }
    function has_savedv($name) {
        $si = $this->si($name);
        return array_key_exists($si->storage(), $this->savedv);
    }
    function has_interest($name) {
        return $this->all_interesting || $this->si_has_interest($this->si($name));
    }
    function si_has_interest(Si $si) {
        return $this->all_interesting
            || array_key_exists($si->storage(), $this->savedv)
            || $si->is_interesting($this);
    }
    function savedv($name, $default_value = null) {
        $si = $this->si($name);
        return $this->si_savedv($si->storage(), $si, $default_value);
    }
    function newv($name, $default_value = null) {
        $si = $this->si($name);
        $s = $si->storage();
        if (array_key_exists($s, $this->savedv))
            return $this->si_savedv($s, $si, $default_value);
        else
            return $this->si_oldv($si, $default_value);
    }

    function set_oldv($name, $value) {
        $this->explicit_oldv[$name] = $value;
    }
    function save($name, $value) {
        $si = $this->si($name);
        if (!$si) {
            return;
        }
        if ($value !== null
            && !($si->storage_type & Si::SI_DATA ? is_string($value) : is_int($value))) {
            error_log(caller_landmark() . ": setting $name: invalid value " . var_export($value, true));
            return;
        }
        $s = $si->storage();
        if ($value === $si->default_value
            || ($value === "" && ($si->storage_type & Si::SI_DATA))) {
            $value = null;
        }
        if ($si->storage_type & Si::SI_NEGATE) {
            $value = $value ? 0 : 1;
        }
        if ($si->storage_type & Si::SI_SLICE) {
            if (!isset($this->savedv[$s])) {
                if (!array_key_exists($s, $this->savedv)) {
                    $this->savedv[$s] = [$this->conf->setting($s, 0), $this->conf->setting_data($s, null)];
                } else {
                    $this->savedv[$s] = [0, null];
                }
            }
            $idx = $si->storage_type & Si::SI_DATA ? 1 : 0;
            $this->savedv[$s][$idx] = $value;
            if ($this->savedv[$s][0] === 0 && $this->savedv[$s][1] === null) {
                $this->savedv[$s] = null;
            }
        } else if ($value === null) {
            $this->savedv[$s] = null;
        } else if ($si->storage_type & Si::SI_DATA) {
            $this->savedv[$s] = [1, $value];
        } else {
            $this->savedv[$s] = [$value, null];
        }
    }
    function update($name, $value) {
        if ($value !== $this->oldv($name)) {
            $this->save($name, $value);
            return true;
        } else
            return false;
    }
    function cleanup_callback($name, $func, $arg = null) {
        if (!isset($this->cleanup_callbacks[$name]))
            $this->cleanup_callbacks[$name] = [$func, null];
        if (func_num_args() > 2)
            $this->cleanup_callbacks[$name][1][] = $arg;
    }

    private function si_curv(Si $si, $default_value) {
        if ($this->use_req()
            && ($this->all_interesting
                || !$si->group
                || $si->is_interesting($this)))
            return get($this->req, str_replace(".", "_", $si->name), $default_value);
        else
            return $this->si_oldv($si, $default_value);
    }
    private function si_oldv(Si $si, $default_value) {
        if ($default_value === null) {
            $default_value = $si->default_value;
        }
        if (isset($this->explicit_oldv[$si->name])) {
            $val = $this->explicit_oldv[$si->name];
        } else if ($si->storage_type & Si::SI_OPT) {
            $val = $this->conf->opt(substr($si->storage(), 4), $default_value);
            if (($si->storage_type & Si::SI_VALUE) && is_bool($val)) {
                $val = (int) $val;
            }
        } else if ($si->storage_type & Si::SI_DATA) {
            $val = $this->conf->setting_data($si->storage(), $default_value);
        } else {
            $val = $this->conf->setting($si->storage(), $default_value);
        }
        if ($val === $si->invalid_value) {
            $val = "";
        }
        if ($si->storage_type & Si::SI_NEGATE) {
            $val = $val ? 0 : 1;
        }
        return $val;
    }
    private function si_savedv($s, Si $si, $default_value) {
        if (isset($this->savedv[$s])) {
            $val = $this->savedv[$s][$si->storage_type & Si::SI_DATA ? 1 : 0];
            if ($si->storage_type & Si::SI_NEGATE) {
                $val = $val ? 0 : 1;
            }
            return $val;
        } else {
            return $default_value;
        }
    }

    function render_messages_at($field) {
        $t = "";
        $fname = $field instanceof Si ? $field->name : $field;
        foreach ($this->messages_at($fname, true) as $mx)
            $t .= '<p class="' . MessageSet::status_class($mx[2], "settings-ap f-h", "is-") . '">' . $mx[1] . "</p>";
        return $t;
    }
    function echo_messages_at($field) {
        echo $this->render_messages_at($field);
    }

    private function strip_group_js($js) {
        $njs = [];
        foreach ($js ? : [] as $k => $v) {
            if (strlen($k) < 10
                || (!str_starts_with($k, "group_")
                    && !str_starts_with($k, "hint_")
                    && !str_starts_with($k, "control_")
                    && !str_starts_with($k, "label_")
                    && $k !== "horizontal"))
                $njs[$k] = $v;
        }
        return $njs;
    }
    function echo_checkbox_only($name, $js = null) {
        $js["id"] = "cb$name";
        $x = $this->curv($name);
        echo Ht::hidden("has_$name", 1),
            Ht::checkbox($name, 1, $x !== null && $x > 0, $this->sjs($name, $js));
    }
    function echo_checkbox($name, $text, $js = null, $hint = null) {
        echo '<div class="', self::add_class("checki", get($js, "group_class")),
            '"><span class="checkc">';
        $this->echo_checkbox_only($name, self::strip_group_js($js));
        echo '</span>', $this->label($name, $text, ["for" => "cb$name", "class" => get($js, "label_class")]);
        if ($hint)
            echo '<p class="', self::add_class("settings-ap f-hx", get($js, "hint_class")), '">', $hint, '</p>';
        $this->echo_messages_at($name);
        if (!get($js, "group_open"))
            echo "</div>\n";
    }
    function echo_radio_table($name, $varr, $heading = null, $rest = null) {
        $x = $this->curv($name);
        if ($x === null || !isset($varr[$x]))
            $x = 0;
        if (is_string($rest))
            $rest = ["after" => $rest];
        $fold = $rest ? get($rest, "fold", false) : false;

        echo '<div class="settings-radio';
        if ($fold !== false && $fold !== true)
            echo ' has-fold fold', in_array($x, explode(" ", $fold)) ? "o" : "c", '" data-fold-values="', $fold;
        echo '">';
        if ($heading)
            echo '<div class="settings-radioheading">', $heading, '</div>';
        foreach ($varr as $k => $item) {
            if (is_string($item))
                $item = ["label" => $item];
            $label = $item["label"];
            $hint = get($item, "hint", "");
            unset($item["label"], $item["hint"]);
            $item["id"] = "{$name}_{$k}";
            if ($fold !== false && !isset($item["class"]))
                $item["class"] = "uich js-foldup";

            $label1 = "<label>";
            $label2 = "</label>";
            if (strpos($label, "<label") !== false)
                $label1 = $label2 = "";

            echo '<div class="settings-radioitem checki">',
                $label1, '<span class="checkc">',
                Ht::radio($name, $k, $k == $x, $this->sjs($name, $item)),
                '</span>', $label, $label2, $hint, '</div>';
        }
        $this->echo_messages_at($name);
        if ($rest && isset($rest["after"]))
            echo $rest["after"];
        echo "</div>\n";
    }
    function render_entry($name, $js = []) {
        $v = $this->curv($name);
        $t = "";
        if (($si = $this->si($name))) {
            if ($si->size && !isset($js["size"]))
                $js["size"] = $si->size;
            if ($si->placeholder && !isset($js["placeholder"]))
                $js["placeholder"] = $si->placeholder;
            if ($si->autogrow)
                $js["class"] = ltrim(get($js, "class", "") . " need-autogrow");
            if ($si->is_date())
                $v = $this->si_render_date_value($v, $si);
            else if ($si->type === "grace")
                $v = $this->si_render_grace_value($v, $si);
            if ($si->parser_class)
                $t = Ht::hidden("has_$name", 1);
        }
        return Ht::entry($name, $v, $this->sjs($name, $js)) . $t;
    }
    function echo_entry($name) {
        echo $this->render_entry($name);
    }
    function echo_control_group($name, $description, $control,
                                $js = null, $hint = null) {
        if (($horizontal = get($js, "horizontal")) !== null)
            unset($js["horizontal"]);
        $klass = $horizontal ? "entryi" : "f-i";
        $si = $this->si($name);
        if ($description === null && $si)
            $description = $si->title;

        echo '<div class="', $this->control_class($name, $klass), '">',
            $this->label($name, $description, ["class" => get($js, "label_class"), "no_control_class" => true]);
        if ($horizontal)
            echo '<div class="entry">';
        echo $control, get_s($js, "control_after");
        $thint = $si ? $this->type_hint($si->type) : null;
        if ($hint || $thint) {
            echo '<div class="f-h">';
            if ($hint && $thint)
                echo '<div>', $hint, '</div><div>', $thint, '</div>';
            else if ($hint || $thint)
                echo $hint ? $hint : $thint;
            echo '</div>';
        }
        $this->echo_messages_at($name);
        if ($horizontal)
            echo "</div>";
        if (!get($js, "group_open"))
            echo "</div>\n";
    }
    function echo_entry_group($name, $description, $js = null, $hint = null) {
        $this->echo_control_group($name, $description,
            $this->render_entry($name, self::strip_group_js($js)),
            $js, $hint);
    }
    function render_select($name, $values, $js = null) {
        $v = $this->curv($name);
        $t = "";
        if (($si = $this->si($name)) && $si->parser_class)
            $t = Ht::hidden("has_$name", 1);
        return Ht::select($name, $values, $v !== null ? $v : 0, $this->sjs($name, $js)) . $t;
    }
    function echo_select_group($name, $values, $description, $js = null, $hint = null) {
        $this->echo_control_group($name, $description,
            $this->render_select($name, $values, self::strip_group_js($js)),
            $js, $hint);
    }
    function render_textarea($name, $js = []) {
        $v = $this->curv($name);
        $t = "";
        $rows = 10;
        if (($si = $this->si($name))) {
            if ($si->size)
                $rows = $si->size;
            if ($si->placeholder)
                $js["placeholder"] = $si->placeholder;
            if ($si->autogrow || $si->autogrow === null)
                $js["class"] = ltrim(get($js, "class", "") . " need-autogrow");
            if ($si->parser_class)
                $t = Ht::hidden("has_$name", 1);
        }
        if (!isset($js["rows"]))
            $js["rows"] = $rows;
        if (!isset($js["cols"]))
            $js["cols"] = 80;
        return Ht::textarea($name, $v, $this->sjs($name, $js)) . $t;
    }
    private function echo_message_base($name, $description, $hint, $xclass) {
        $si = $this->si($name);
        if ($si->message_default)
            $si->default_value = $this->si_message_default($si);
        $current = $this->curv($name);
        $description = '<a class="ui qq js-foldup" href="">'
            . expander(null, 0) . $description . '</a>';
        echo '<div class="f-i has-fold fold', ($current == $si->default_value ? "c" : "o"), '">',
            '<div class="f-c', $xclass, ' ui js-foldup">',
            $this->label($name, $description),
            ' <span class="n fx">(HTML allowed)</span></div>',
            $this->render_textarea($name, ["class" => "fx"]),
            $hint, "</div>\n";
    }
    function echo_message($name, $description, $hint = "") {
        $this->echo_message_base($name, $description, $hint, "");
    }
    function echo_message_minor($name, $description, $hint = "") {
        $this->echo_message_base($name, $description, $hint, " n");
    }
    function echo_message_horizontal($name, $description, $hint = "") {
        $si = $this->si($name);
        if ($si->message_default)
            $si->default_value = $this->si_message_default($si);
        $current = $this->curv($name);
        if ($current !== $si->default_value) {
            echo '<div class="entryi">',
                $this->label($name, $description), '<div>';
            $close = "";
        } else {
            $description = '<a class="ui qq js-foldup href="">'
                . expander(null, 0) . $description . '</a>';
            echo '<div class="entryi has-fold foldc">',
                $this->label($name, $description), '<div>',
                '<div class="dim ui js-foldup fn">default</div>',
                '<div class="fx">';
            $close = "</div>";
        }
        echo '<div class="f-c n">(HTML allowed)</div>',
            $this->render_textarea($name),
            $hint, $close, "</div></div>";
    }

    private function si_render_date_value($v, Si $si) {
        if ($v !== null && $this->use_req())
            return $v;
        else if ($si->date_backup
                 && $this->curv($si->date_backup) == $v)
            return "";
        else if ($si->placeholder !== "N/A"
                 && $si->placeholder !== "none"
                 && $v === 0)
            return "none";
        else if ($v <= 0)
            return "";
        else if ($v == 1)
            return "now";
        else
            return $this->conf->parseableTime($v, true);
    }
    private function si_render_grace_value($v, Si $si) {
        if ($v === null || $v <= 0 || !is_numeric($v))
            return "none";
        if ($v % 3600 == 0)
            return ($v / 3600) . " hr";
        if ($v % 60 == 0)
            return ($v / 60) . " min";
        return sprintf("%d:%02d", intval($v / 60), $v % 60);
    }

    function type_hint($type) {
        if (str_ends_with($type, "date") && !isset($this->hint_status["date"])) {
            $this->hint_status["date"] = true;
            return "Date examples: “now”, “10 Dec 2006 11:59:59pm PST”, “2014-10-31 00:00 UTC-1100” <a href=\"http://php.net/manual/en/datetime.formats.php\">(more examples)</a>";
        } else if ($type === "grace" && !isset($this->hint_status["grace"])) {
            $this->hint_status["grace"] = true;
            return "Example: “15 min”";
        } else
            return false;
    }

    function expand_mail_template($name, $default) {
        if (!$this->null_mailer)
            $this->null_mailer = new HotCRPMailer($this->conf, null, null, array("width" => false));
        return $this->null_mailer->expand_template($name, $default);
    }

    private function si_message_default($si) {
        $msgname = $si->message_default;
        if (str_starts_with($msgname, "msg."))
            $msgname = substr($msgname, 4);
        $ctxarg = null;
        if (($ctxname = $si->message_context_setting))
            $ctxarg = $this->curv($ctxname[0] === "+" ? substr($ctxname, 1) : $ctxname);
        return $this->conf->ims()->default_itext($msgname, false, $ctxarg);
    }


    function execute() {
        global $Now;
        // parse settings
        foreach ($this->conf->_setting_info as $si)
            $this->account($si);

        // check date relationships
        foreach (array("sub_reg" => "sub_sub", "final_soft" => "final_done")
                 as $dn1 => $dn2)
            list($dv1, $dv2) = [$this->savedv($dn1), $this->savedv($dn2)];
            if (!$dv1 && $dv2)
                $this->save($dn1, $dv2);
            else if ($dv2 && $dv1 > $dv2) {
                $si = Si::get($this->conf, $dn1);
                $this->error_at($si, "Must come before " . Si::get($this->conf, $dn2, "title") . ".");
                $this->error_at($dn2);
            }
        if ($this->has_savedv("sub_sub"))
            $this->save("sub_update", $this->savedv("sub_sub"));
        if ($this->conf->opt("defaultSiteContact")) {
            if ($this->has_savedv("opt.contactName")
                && $this->conf->opt("contactName") === $this->savedv("opt.contactName"))
                $this->save("opt.contactName", null);
            if ($this->has_savedv("opt.contactEmail")
                && $this->conf->opt("contactEmail") === $this->savedv("opt.contactEmail"))
                $this->save("opt.contactEmail", null);
        }
        if ($this->has_savedv("resp_active") && $this->savedv("resp_active"))
            foreach (explode(" ", $this->newv("resp_rounds")) as $i => $rname) {
                $isuf = $i ? "_$i" : "";
                if ($this->newv("resp_open$isuf") > $this->newv("resp_done$isuf")
                    && $this->newv("resp_done$isuf")) {
                    $si = Si::get($this->conf, "resp_open$isuf");
                    $this->error_at($si, "Must come before " . Si::get($this->conf, "resp_done", "title") . ".");
                    $this->error_at("resp_done$isuf");
                }
            }

        // Setting relationships
        if ($this->has_savedv("sub_open")
            && $this->newv("sub_open", 1) <= 0
            && $this->oldv("sub_open") > 0
            && $this->newv("sub_sub") <= 0)
            $this->save("sub_close", $Now);
        if ($this->has_savedv("msg.clickthrough_submit"))
            $this->save("clickthrough_submit", null);

        // make settings
        $this->changes = [];
        if (!$this->has_error()
            && (!empty($this->savedv) || !empty($this->saved_si))) {
            $tables = "Settings write";
            foreach ($this->need_lock as $t => $need)
                if ($need)
                    $tables .= ", $t write";
            $this->conf->qe_raw("lock tables $tables");

            // load db settings, pre-crosscheck
            $dbsettings = array();
            $result = $this->conf->qe("select name, value, data from Settings");
            while (($row = edb_row($result)))
                $dbsettings[$row[0]] = $row;
            Dbl::free($result);

            // apply settings
            foreach ($this->saved_si as $si) {
                $this->parser($si)->save($this, $si);
            }

            $dv = $av = array();
            foreach ($this->savedv as $n => $v) {
                if (substr($n, 0, 4) === "opt.") {
                    $okey = substr($n, 4);
                    if (array_key_exists($okey, $this->conf->opt_override))
                        $oldv = $this->conf->opt_override[$okey];
                    else
                        $oldv = $this->conf->opt($okey);
                    $vi = Si::$option_is_value[$okey] ? 0 : 1;
                    $basev = $vi ? "" : 0;
                    $newv = $v === null ? $basev : $v[$vi];
                    if ($oldv === $newv)
                        $v = null; // delete override value in database
                    else if ($v === null && $oldv !== $basev && $oldv !== null)
                        $v = $vi ? [0, ""] : [0, null];
                }
                if ($v === null
                    ? !isset($dbsettings[$n])
                    : isset($dbsettings[$n]) && (int) $dbsettings[$n][1] === $v[0] && $dbsettings[$n][2] === $v[1])
                    continue;
                $this->changes[] = $n;
                if ($v !== null)
                    $av[] = [$n, $v[0], $v[1]];
                else
                    $dv[] = $n;
            }
            if (!empty($dv)) {
                $this->conf->qe("delete from Settings where name?a", $dv);
                //Conf::msg_info(Ht::pre_text_wrap(Dbl::format_query("delete from Settings where name?a", $dv)));
            }
            if (!empty($av)) {
                $this->conf->qe("insert into Settings (name, value, data) values ?v on duplicate key update value=values(value), data=values(data)", $av);
                //Conf::msg_info(Ht::pre_text_wrap(Dbl::format_query("insert into Settings (name, value, data) values ?v on duplicate key update value=values(value), data=values(data)", $av)));
            }

            $this->conf->qe_raw("unlock tables");
            if (!empty($this->changes))
                $this->user->log_activity("Updated settings " . join(", ", $this->changes));
            $this->conf->load_settings();
            foreach ($this->cleanup_callbacks as $cb)
                call_user_func($cb[0], $this, $cb[1]);

            // contactdb may need to hear about changes to shortName
            if ($this->has_savedv("opt.shortName") && ($cdb = $this->conf->contactdb()))
                Dbl::ql($cdb, "update Conferences set shortName=? where dbName=?", $this->conf->short_name, $this->conf->dbname);
        }
        return !$this->has_error();
    }
    function account(Si $si1) {
        if ($si1->internal)
            return;
        foreach ($this->req_si($si1) as $si) {
            if (!$si->active_value()) {
                /* ignore changes to disabled/novalue settings */;
            } else if ($si->parser_class) {
                if ($this->parser($si)->parse($this, $si)) {
                    $this->saved_si[] = $si;
                }
            } else {
                $v = $this->parse_value($si);
                if ($v === null || $v === false)
                    return;
                if (is_int($v) && $v <= 0 && $si->type !== "radio" && $si->type !== "zint")
                    $v = null;
                $this->save($si->name, $v);
                if ($si->ifnonempty)
                    $this->save($si->ifnonempty, $v === null || $v === "" ? null : 1);
            }
        }
    }
    function parse_value(Si $si) {
        global $Now;

        if (!isset($sv->req[$si->name])) {
            $xname = str_replace(".", "_", $si->name);
            if (isset($this->req[$xname]))
                $this->req[$si->name] = $this->req[$xname];
            else if (in_array($si->type, ["cdate", "checkbox"]))
                return 0;
            else
                return null;
        }

        $v = trim($this->req[$si->name]);
        if (($si->placeholder && $si->placeholder === $v)
            || ($si->invalid_value && $si->invalid_value === $v))
            $v = "";

        if ($si->type === "checkbox")
            return $v != "" ? 1 : 0;
        else if ($si->type === "cdate" && $v == "1")
            return 1;
        else if ($si->type === "date"
                 || $si->type === "cdate"
                 || $si->type === "ndate") {
            if ((string) $v === ""
                || $v === "0"
                || !strcasecmp($v, "N/A")
                || !strcasecmp($v, "same as PC")
                || ($si->type !== "ndate" && !strcasecmp($v, "none")))
                return -1;
            else if (!strcasecmp($v, "none"))
                return 0;
            else if (($v = $this->conf->parse_time($v)) !== false)
                return $v;
            $err = "Should be a date.";
        } else if ($si->type === "grace") {
            if (($v = SettingParser::parse_interval($v)) !== false)
                return intval($v);
            $err = "Should be a grace period.";
        } else if ($si->type === "int" || $si->type === "zint") {
            if (preg_match("/\\A[-+]?[0-9]+\\z/", $v))
                return intval($v);
            if ($v == "" && $si->placeholder)
                return 0;
            $err = "Should be a number.";
        } else if ($si->type === "string") {
            // Avoid storing the default message in the database
            if (substr($si->name, 0, 9) == "mailbody_") {
                $t = $this->expand_mail_template(substr($si->name, 9), true);
                $v = cleannl($v);
                if ($t["body"] == $v)
                    return "";
            }
            return $v;
        } else if ($si->type === "simplestring") {
            return simplify_whitespace($v);
        } else if ($si->type === "tag"
                   || $si->type === "tagbase") {
            $tagger = new Tagger($this->user);
            $v = trim($v);
            if ($v === "" && $si->optional)
                return $v;
            $v = $tagger->check($v, $si->type === "tagbase" ? Tagger::NOVALUE : 0);
            if ($v)
                return $v;
            $err = $tagger->error_html;
        } else if ($si->type === "emailheader") {
            $v = MimeText::encode_email_header("", $v);
            if ($v !== false)
                return ($v == "" ? "" : MimeText::decode_header($v));
            $err = "Should be an email header.";
        } else if ($si->type === "emailstring") {
            $v = trim($v);
            if ($v === "" && $si->optional)
                return "";
            else if (validate_email($v) || $v === $this->oldv($si->name, null))
                return $v;
            $err = "Should be an email address.";
        } else if ($si->type === "urlstring") {
            $v = trim($v);
            if (($v === "" && $si->optional)
                || preg_match(',\A(?:https?|ftp)://\S+\z,', $v))
                return $v;
            $err = "Should be a URL.";
        } else if ($si->type === "htmlstring") {
            if (($v = CleanHTML::basic_clean($v, $err)) !== false) {
                if ($si->message_default
                    && $v === $this->si_message_default($si))
                    return "";
                return $v;
            }
            /* $err set by CleanHTML::basic_clean */
        } else if ($si->type === "radio") {
            foreach ($si->values as $allowedv)
                if ((string) $allowedv === $v)
                    return $allowedv;
            $err = "Unexpected value.";
        } else
            return $v;

        $this->error_at($si, $err);
        return null;
    }

    function unparse_json_value(Si $si) {
        $v = $this->si_oldv($si, null);
        if ($si->type === "checkbox")
            return !!$v;
        else if ($si->type === "cdate" && $v == 1)
            return true;
        else if ($si->type === "date"
                 || $si->type === "cdate"
                 || $si->type === "ndate") {
            if ($v > 0)
                return $this->conf->parseableTime($v, true);
            else
                return false;
        } else if ($si->type === "grace") {
            if ($v > 0)
                return $this->si_render_grace_value($v, $si);
            else
                return false;
        } else if ($si->type === "int") {
            return $v > 0 ? (int) $v : false;
        } else if ($si->type === "zint") {
            return (int) $v;
        } else if ($si->type === "string"
                   || $si->type === "simplestring"
                   || $si->type === "tag"
                   || $si->type === "tagbase"
                   || $si->type === "emailheader"
                   || $si->type === "emailstring"
                   || $si->type === "urlstring"
                   || $si->type === "htmlstring") {
            return (string) $v !== "" ? $v : false;
        } else if ($si->type === "radio") {
            $pos = array_search($v, $si->values);
            if ($pos !== false && $si->json_values && isset($si->json_values[$pos]))
                return $si->json_values[$pos];
            else
                return $v;
        } else {
            return $v;
        }
    }
    function unparse_json() {
        assert(!$this->use_req());
        $j = [];
        foreach ($this->conf->_setting_info as $si)
            if ($this->si_has_interest($si)
                && $si->active_value()
                && $si->json_name) {
                if ($si->parser_class) {
                    $this->parser($si)->unparse_json($this, $si, $j);
                } else {
                    $v = $this->unparse_json_value($si);
                    $j[$si->json_name] = $v;
                }
            }
        return (object) $j;
    }

    function parse_json_value(Si $si, $v) {
        if ($v === null)
            return;
        if (in_array($si->type, ["cdate", "checkbox"])
            && is_bool($v)) {
            $sv->set_req("has_{$si->name}", "1");
            if ($v)
                $sv->set_req($si->name, "1");
            return;
        } else if ($si->type === "date"
                   || $si->type === "cdate"
                   || $si->type === "ndate"
                   || $si->type === "grace") {
            if (is_string($v) || $v === false) {
                $sv->set_req($si->name, $v === false ? "none" : $v);
                return;
            }
        } else if ($si->type === "int"
                   || $si->type === "zint") {
            if (is_int($v) || ($si->type === "int" && $v === false)) {
                $sv->set_req($si->name, (string) $v);
                return;
            }
        } else if ($si->type === "string"
                   || $si->type === "simplestring"
                   || $si->type === "tag"
                   || $si->type === "tagbase"
                   || $si->type === "emailheader"
                   || $si->type === "emailstring"
                   || $si->type === "urlstring"
                   || $si->type === "htmlstring") {
            if (is_string($v) || $v === false) {
                $sv->set_req($si->name, (string) $v);
                return;
            }
        } else if ($si->type === "radio") {
            $jvalues = $si->json_values ? : $si->values;
            $pos = array_search($v, $jvalues);
            if ($pos === false && ($v === false || $v === true))
                $pos = array_search($v ? "yes" : "no", $jvalues);
            else if ($pos === false && ($v === "yes" || $v === "no"))
                $pos = array_search($v === "yes" ? true : false, $jvalues);
            if ($pos !== false) {
                $sv->set_req($si->name, (string) $si->values[$pos]);
                return;
            }
        }

        $this->error_at($si, "Invalid value.");
    }

    function changes() {
        return $this->changes;
    }
}
