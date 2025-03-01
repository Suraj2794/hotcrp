<?php
// comment.php -- HotCRP paper comment display/edit page
// Copyright (c) 2006-2019 Eddie Kohler; see LICENSE.

require_once("src/initweb.php");
require_once("src/papertable.php");
$Qreq->text = $Qreq->comment;
$Qreq->tags = $Qreq->commenttags;
if ($Qreq->deletecomment)
    $Qreq->delete = 1;
if ($Qreq->p)
    $Conf->set_paper_request($Qreq, $Me);
$Conf->call_api_exit("comment", $Me, $Qreq, $Conf->paper);
