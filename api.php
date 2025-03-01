<?php
// api.php -- HotCRP JSON API access page
// Copyright (c) 2006-2019 Eddie Kohler; see LICENSE.

// argument cleaning
require_once("lib/navigation.php");
if (!isset($_GET["fn"])) {
    $fn = Navigation::path_component(0, true);
    if ($fn && ctype_digit($fn)) {
        if (!isset($_GET["p"]))
            $_GET["p"] = $fn;
        $fn = Navigation::path_component(1, true);
    }
    if ($fn)
        $_GET["fn"] = $fn;
    else if (isset($_GET["track"]))
        $_GET["fn"] = "track";
    else {
        http_response_code(404);
        header("Content-Type: text/plain; charset=utf-8");
        echo json_encode(["ok" => false, "error" => "API function missing"]);
        exit;
    }
}
if ($_GET["fn"] === "deadlines")
    $_GET["fn"] = "status";
if (!isset($_GET["p"])
    && ($p = Navigation::path_component(1, true))
    && ctype_digit($p))
    $_GET["p"] = $p;

// trackerstatus is a special case: prevent session creation
global $Me;
if ($_GET["fn"] === "trackerstatus") {
    $Me = false;
    require_once("src/initweb.php");
    MeetingTracker::trackerstatus_api(new Contact(null, $Conf));
    exit;
}

// initialization
require_once("src/initweb.php");

if ($Qreq->base !== null)
    $Conf->set_siteurl($Qreq->base);
if (!$Me->has_database_account()
    && ($key = $Me->capability("tracker_kiosk"))) {
    $kiosks = $Conf->setting_json("__tracker_kiosk") ? : (object) array();
    if (isset($kiosks->$key) && $kiosks->$key->update_at >= $Now - 172800) {
        if ($kiosks->$key->update_at < $Now - 3600) {
            $kiosks->$key->update_at = $Now;
            $Conf->save_setting("__tracker_kiosk", 1, $kiosks);
        }
        $Me->tracker_kiosk_state = $kiosks->$key->show_papers ? 2 : 1;
    }
}
if ($Qreq->p)
    $Conf->set_paper_request($Qreq, $Me);

// requests
if ($Conf->has_api($Qreq->fn)) {
    $Conf->call_api_exit($Qreq->fn, $Me, $Qreq, $Conf->paper);
}

if ($Qreq->fn === "events") {
    if (!$Me->is_reviewer())
        json_exit(403, ["ok" => false]);
    $from = $Qreq->from;
    if (!$from || !ctype_digit($from))
        $from = $Now;
    $when = $from;
    $rf = $Conf->review_form();
    $events = new PaperEvents($Me);
    $rows = [];
    $more = false;
    foreach ($events->events($when, 11) as $xr) {
        if (count($rows) == 10)
            $more = true;
        else {
            if ($xr->crow)
                $rows[] = $xr->crow->unparse_flow_entry($Me);
            else
                $rows[] = $rf->unparse_flow_entry($xr->prow, $xr->rrow, $Me);
            $when = $xr->eventTime;
        }
    }
    json_exit(["ok" => true, "from" => (int) $from, "to" => (int) $when - 1,
               "rows" => $rows, "more" => $more]);
}

if ($Qreq->fn === "searchcompletion") {
    $s = new PaperSearch($Me, "");
    json_exit(["ok" => true, "searchcompletion" => $s->search_completion()]);
}

// from here on: `status` and `track` requests
$is_track = $Qreq->fn === "track";
if ($is_track)
    MeetingTracker::track_api($Me, $Qreq); // may fall through to act like `status`
else if ($Qreq->fn !== "status")
    json_exit(404, "Unknown request “" . $Qreq->fn . "”");

$j = $Me->my_deadlines($Conf->paper);

if ($Conf->paper && $Me->can_view_tags($Conf->paper)) {
    $pj = (object) ["pid" => $Conf->paper->paperId];
    $Conf->paper->add_tag_info_json($pj, $Me);
    if (count((array) $pj) > 1)
        $j->p = [$Conf->paper->paperId => $pj];
}

if ($is_track && ($new_trackerid = $Qreq->annex("new_trackerid")))
    $j->new_trackerid = $new_trackerid;
$j->ok = true;
json_exit($j);
