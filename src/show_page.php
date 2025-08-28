<?php
require "include/config.inc";
require "include/utils.php";

$_SESSION[PREFIX . "_ppage"] = $_SERVER['REQUEST_URI'];
if ($_SESSION[PREFIX . '_username'] == "") {
    header("location: login.php");
    exit;
}

$in_id = intval($_GET['id']);
if (!$in_id || $in_id < 1) {
    header("location: index.php");
    exit;
}

$show_info = $mysqli->show_info($in_id);
if (!$show_info['id']) {
    header("location: index.php");
    exit;
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

$all_bool = $_GET['all'] ?? 'nobool';
if ($all_bool === '1' || $all_bool === '0') {
    $all_bool = (int)$all_bool;
} else {
    $all_bool = 0;
}

$recent_amt_per_page = 5;
$amt_per_page = 10;

$avg = $_SESSION[$in_id . "_avg"] ?? $mysqli->get_show_column($in_id, "review_avg") ?? 0.0;

// forces 1 decimal point
$avg = number_format($avg, 1);

$review_count = $mysqli->review_count($in_id);
if ($review_count <= $amt_per_page) {
    $page = 1;
    $num_pages = 1;
} else {
    // find the number of pages needed to display all reviews, add one extra if it doesn't divide cleanly
    $num_pages = ($amt_per_page % $review_count) === 0 ? intval($review_count / $amt_per_page) : intval($review_count / $amt_per_page) + 1;

    if ($page > $num_pages) {
        $page = $num_pages;
    }
}

class Reviews
{
    public array $reviews_arr;

    public function __construct($mysqli, $show_id, $amt_per_page, $offset = 0)
    {
        $this->reviews_arr = $mysqli->show_reviews($show_id, $amt_per_page, $offset);
    }

    public function getReviews(): array
    {
        return $this->reviews_arr;
    }

    public function getReviewContent($i)
    {
        return $this->reviews_arr[$i]['review_content'];
    }

    public function getReviewRating($i)
    {
        return $this->reviews_arr[$i]['review_value'];
    }
}

$revs = new Reviews($mysqli, $show_info['id'], $amt_per_page);
$reviews = $revs->getReviews();

/** Extracts various IDs from the specified POST key name
 * @param $key - The POST key to get IDs from
 * @return array
 */
function getIDs($key): array
{
    // retrieves the command (delete or edit)
    $cmd = substr($key, 0, strcspn($key, "0123456789"));

    // creates a substring starting at the first numeric character of $val
    $ids = substr($key, strcspn($key, "0123456789"));

    // get indices of hyphen characters to use as starting points for ids
    $first = strpos($ids, "-");
    $second = strposX($ids, "-", 2);

    // arrid = id in array
    // rid = review id
    // uid = author's user id
    $arrid = substr($ids, 0, $first);
    $rid = substr($ids, $first + 1, $second - 2);
    $uid = substr($ids, $second + 1);

    return array($arrid, $rid, $uid, $cmd);
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_SESSION[PREFIX . '_user_id']) && isset($_POST['modify']) && $_POST['modify'] === "1") {
        unset($_POST['modify']);
        $sid = $show_info['id'];
        foreach ($_POST as $key => $val) {
            if (preg_match("/(delete)[0-9]./", $key)) {
                $IDs = getIDs($key);

                if ($_SESSION[PREFIX . '_user_id'] == $IDs[2] && $IDs[3] === "delete") {
                    $mysqli->review_delete($IDs[1]);
                    update_avg($sid, $reviews[$IDs[0]]["review_value"], $review_count, $mysqli, true);

                    header("location: show_page.php?id=" . $in_id);
                    exit;
                }
            }
        }
    } elseif (!isset($_SESSION[PREFIX . '_user_id']) || !isset($_SESSION[PREFIX . '_security'])) {
        ?>
        <script>
            alert("Invalid user id");
        </script><?php
        unset($_POST);
    } elseif (!isset($_POST['modify']) && $_POST['modal-submit'] === "1" && $_SESSION[PREFIX . '_security'] > 1) {
        unset($_POST['modal-submit']);

        if (isset($_POST['review-input'])) {
            $rating = match ($_POST['rate']) {
                "5" => 5,
                "4" => 4,
                "3" => 3,
                "2" => 2,
                default => 1,
            };

            if (!isset($review_count)) {
                $review_count = 0;
            }
            update_avg($in_id, $rating, $review_count, $mysqli);

            $mysqli->review_insert($rating, $_POST['review-input'], $in_id, $_SESSION[PREFIX . '_user_id']);

            $mysqli->actions_insert("Added Review: " . date("Y-m-d") . " SID: " . $in_id, $_SESSION[PREFIX . '_user_id']);

            $_SESSION[PREFIX . '_action'][] = 'added';
            header("location: show_page.php?id=" . $in_id);
            exit;
        } else {
            ?>
            <script>
                alert("Review text cannot be empty.");
            </script>
            <?php
            unset($_POST);
        }
    } elseif (!isset($_POST['modify']) && $_POST['modal-edit'] === "1" && $_SESSION[PREFIX . '_security'] > 1) {
        unset($_POST['modal-edit']);

        foreach ($_POST as $key => $value) {
            if (preg_match("/(edit)[0-9]./", $key)) {
                $IDs = getIDs($key);

                if ($_SESSION[PREFIX . '_user_id'] == $IDs[2] && $IDs[3] === "edit") {
                    $rating = match ($_POST['rate']) {
                        "5" => 5,
                        "4" => 4,
                        "3" => 3,
                        "2" => 2,
                        default => 1,
                    };

                    $mysqli->review_update($IDs[1], $rating, $_POST['review-input']);

                    // only update the average if the rating changes
                    if ($rating != $reviews[$IDs[0]]['review_value']) {
                        $review_count = $mysqli->get_show_column($in_id, "review_amt");

                        // can just set the avg to the user's rating if it's the only review
                        if (!$review_count || $review_count === 1) {
                            $mysqli->update_show_column($in_id, $rating, "review_avg");
                            $mysqli->update_show_column($in_id, 1, "review_amt");
                        } else {
                            update_avg($in_id, $rating, $review_count, $mysqli, false, true, $reviews[$IDs[0]]['review_value']);
                        }
                    }

                    $mysqli->actions_insert("Updated Review: " . date("Y-m-d") . " " . $in_id, $_SESSION[PREFIX . '_user_id']);

                    $_SESSION[PREFIX . '_action'][] = 'updated';
                    header("location: show_page.php?id=" . $in_id);
                    exit;
                }
            }
        }
    }
}

$img_url = 'https://image.tmdb.org/t/p/original';
$temp_url = $img_url . $show_info['show_poster_path'];
$back_url = $img_url . $show_info['show_backdrop_path'];

$year = substr($show_info['show_air_date'], 0, 4);

$page_name = $show_info['show_name'];

$genres = $mysqli->show_genres($in_id);

$r_str = '';
?>
<!DOCTYPE html>
<html lang="en" class="no-mobile">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $page_name; ?></title>
    <link rel="stylesheet" type="text/css" href="vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" type="text/css" href="vendors/base/vendor.bundle.base.css">
    <link rel="stylesheet" type="text/css" href="css/style.css">
    <link rel="shortcut icon" href="images/binged_logo.svg"/>
</head>
<body>
<!-- a nothing script that just prevents a flash of unstyled content on Firefox -->
<script>console.log("start");</script>

<div class="container-scroller">

    <?php require_once 'partials/_navbar.php'; ?>
    <div class="container-fluid page-body-wrapper">
        <div class="main-panel">
            <div class="content-wrapper">
                <div class="row">
                    <div class="col-12 grid-margin stretch-card">
                        <div class="card">
                            <div class="card-body">
                                <div style="float:left">
                                    <img src="<?php echo $temp_url; ?>" width="333" height="500"
                                         style="margin-right:50px" alt="Image could not be loaded."/>
                                </div>
                                <div class="flex-wrap justify-content-md-between">
                                    <h2 class="flex-wrap"
                                        style="padding-bottom:10px"><?php echo $page_name; ?></h2>
                                    <h4 class="flex-wrap" style="padding-bottom:10px"><?php echo $year; ?></h4>
                                    <h4 class="flex-wrap" style="padding-bottom: 10px">Average
                                        score: <?php echo $avg; ?></h4>
                                    <p class="flex-wrap"
                                       style="padding-bottom:10px"><?php echo $show_info['show_overview']; ?></p>
                                </div>
                                <div class="d-flex justify-content-between align-items-end flex-lg-column"
                                     style="float:right">
                                    <button type="button" class="btn btn-primary mt-2 mt-xl-0" data-bs-toggle="modal"
                                            data-bs-target="#review-modal" id="modal_open_review">Write a review
                                    </button>
                                    <script>
                                        // removes review button if user is guest
                                        let check = parseInt('<?php if (isset($_SESSION[PREFIX . '_security'])) {
                                            echo $_SESSION[PREFIX . '_security'];
                                        } else {
                                            echo 1;
                                        } ?>');
                                        if (check < 5) {
                                            document.getElementById('modal_open_review').remove();
                                        }
                                    </script>
                                </div>
                                <h5 class="flex-wrap">Genres: </h5>
                                <?php
                                if (empty($genres)) {
                                    ?>
                                    <p>None</p>
                                    <?php
                                } else {
                                    foreach ($genres as $genre) {
                                        $g_name = match ($genre['genre_name']) {
                                            "Action & Adventure" => "Action",
                                            "Sci-Fi & Fantasy" => "SciFi",
                                            "War & Politics" => "War",
                                            default => $genre['genre_name']
                                        };
                                        ?>
                                        <p style="flex-direction: row">
                                            <b>
                                                <a class="one" style="color: #282f3a; flex-direction: row"
                                                   href="genre_page.php?genre=<?php echo $g_name; ?>"><?php echo $genre['genre_name']; ?></a>
                                            </b>
                                        </p>
                                        <?php
                                    }
                                }
                                ?>

                            </div>
                        </div>

                    </div>

                    <div class="row">
                        <div class="col-md-12 grid-margin">
                            <div class="dashboard-tabs p-0">
                                <ul class="nav nav-tabs px-4" id="review-nav-tabs">
                                    <li class="nav-item <?php if (!$all_bool) echo 'active'; ?>" id="review-tab-recent">
                                        <a id="review-link-recent"
                                           class="nav-link <?php if (!$all_bool) echo 'active'; ?>"
                                           href="#recent-reviews">Recent
                                            reviews</a>
                                    </li>
                                    <li class="nav-item <?php if ($all_bool) echo 'active'; ?>" id="review-tab-all">
                                        <a id="review-link-all" class="nav-link <?php if ($all_bool) echo 'active'; ?>"
                                           href="#all-reviews">All reviews
                                            (<?php echo $review_count ?>)</a>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-12 grid-margin stretch-card">
                        <div class="card" id="recent-reviews" <?php if ($all_bool) echo 'hidden'; ?>>
                            <div class="card-body">
                                <?php
                                if (empty($reviews)) {
                                    ?>
                                    <div class="col-sm-12 grid-margin stretch-card" style="border: none; outline: none">
                                        <div class="card" style="border: none; outline: none">
                                            <div class="card-body" style="border: none; outline: none">
                                                <div class="card-title">No reviews yet!</div>
                                            </div>
                                        </div>
                                    </div>
                                <?php } else {
                                    $max = $recent_amt_per_page;
                                    // make sure to not loop out of bounds
                                    if ($review_count < $max) {
                                        $max = $review_count;
                                    }
                                    $i = 0;
                                    while ($i < $max) {
                                        echo review_temp($i, $reviews, $mysqli, $in_id);
                                        $i++;
                                    }
                                } ?>
                            </div>
                        </div>
                        <div class="card" id="all-reviews" <?php if (!$all_bool) echo 'hidden'; ?>>
                            <div class="card-body">
                                <div class="d-flex justify-content-center">
                                    <div class="spinner-border text-primary"
                                         role="status" id="spinner" <?php if ($all_bool) echo 'hidden'; ?>>
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </div>
                                <div id="load-content" <?php if (!$all_bool) echo 'hidden'; ?>>
                                    <?php if ($page > 1) {
                                        $new_revs = new Reviews($mysqli, $in_id, $amt_per_page, ($amt_per_page * ($page - 1)));
                                        $new_reviews = $new_revs->getReviews();
                                        $page_rev_count = count($new_reviews);
                                        $i = 0;

                                        while ($i < $page_rev_count) {
                                            echo review_temp($i, $new_reviews, $mysqli, $in_id);
                                            $i++;
                                        }
                                    } else {
                                        $max = $amt_per_page;
                                        if ($review_count < $max) {
                                            $max = $review_count;
                                        }
                                        $i = 0;
                                        while ($i < $max) {
                                            echo review_temp($i, $reviews, $mysqli, $in_id);
                                            $i++;
                                        }

                                    }
                                    if ($review_count > $amt_per_page) echo pagination_template($page, $num_pages, $in_id, 0, "review"); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <!-- content wrapper ends -->
            <?php require_once 'partials/_footer.php'; ?>
        </div>
    </div>
</div>

<div class="modal fade reviewModal hideModal" id="review-modal" data-bs-backdrop="static" data-bs-keyboard="false"
     tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="height: 60%">
            <div class="modal-header">
                <h5 class="modal-title" id="review-modal-title">
                    <span id="review-modal-title-watch" data-title-label="submit">I watched...</span>
                    <span id="review-modal-title-edit" data-title-label="edit" hidden>Edit review</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="modal_close_review"></button>
            </div>
            <div class="modal-body">
                <form id="review-form" class="reviewForm" method="post">
                    <section class="reviewStep submit fields-reversed">
                        <header class="header">
                            <div class="prod inline film">
                                <h3 class="primaryname">
                                    <span class="name"><?php echo $page_name; ?></span>
                                </h3>
                            </div>
                        </header>
                        <aside class="figure">
                            <figure>
                                <img src="<?php echo $temp_url; ?>" width="150" height="225" alt=""/>
                            </figure>
                        </aside>
                        <div class="body">
                            <input type="hidden" name="modal-submit" id="modal-submit-hidden" value="1">
                            <input type="hidden" name="modal-edit" id="modal-edit-hidden" value="0">
                            <div class="reviewfields">
                                <div class="inner">
                                    <textarea id="review-input" class="field reviewfield" name="review-input"
                                              placeholder="Add a review..." autofocus></textarea>
                                    <span id="review-input-msg" class="alert-msg"></span>
                                </div>
                            </div>
                            <div class="rating">
                                <div class="rate">
                                    <h4 class="rating-label">Rating: </h4>
                                    <?php for ($i = 5; $i >= 1; $i--) { ?>
                                        <input type="radio" id="star<?php echo $i; ?>" name="rate"
                                               value="<?php echo $i; ?>"/>
                                        <label for="star<?php echo $i; ?>" title="text"><?php echo $i; ?>
                                            stars</label>
                                    <?php } ?>
                                </div>
                            </div>
                        </div>
                    </section>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-primary" type="submit" form="review-form" id="review-modal-submit-btn">Submit
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade alertModal hideModal" id="alert-modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="height: 30%">
            <div class="modal-header">
                <h5 class="modal-title" id="alert_modal_title">
                    Are you sure you want to delete this review?
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" id="modal_close_alert"></button>
            </div>
            <div class="modal-footer">
                <form action="" method="post" id="alert-form">
                    <input type="hidden" name="modify" id="modify-field-hidden-modal" value="1">
                    <button class="btn btn-danger" type="submit">DELETE</button>
                    <button class="btn btn-primary" type="button" data-bs-dismiss="modal">Cancel</button>
                </form>
            </div>
        </div>
    </div>
</div>
<!-- php functions -->
<?php
function review_temp($i, $arr, $mysqli, $in_id): string
{
    $user_id = $arr[$i]["user_id"];
    $user_info = $mysqli->user_info($user_id);
    $user_pf = $mysqli->user_pf_info($user_id);
    $r_str = starify($arr[$i]["review_value"]);
    $is_user = (isset($user_info['user_id']) && $user_info['user_id'] == $_SESSION[PREFIX . '_user_id']);

    // get html from template
    return review_template($arr[$i], $user_pf, $i, $user_info, $in_id, $r_str, $is_user);
}

?>
<!-- end php -->
<!-- plugins:js -->
<script src="js/eventListeners.js"></script>
<script>
    // get all buttons with ids that start with 'modal_'
    // for (let i of document.querySelectorAll("button[id^=modal_]")) {
    //     let sub = i.id.substring(i.id.lastIndexOf('_') + 1);
    //     let modalId = '';
    //     if (sub.indexOf("alert")) {
    //         modalId = 'alert-modal';
    //     } else {
    //         modalId = 'review-modal';
    //     }
    //
    //     i.addEventListener('click', function () {
    //         console.log(i.id + " was clicked");
    //         console.log(modalId);
    //         changeModalDisplay(document.getElementById(modalId));
    //     });
    // }

    // since guests don't have this button, need to check if it exists first
    if (document.getElementById('modal_open_review')) {
        document.getElementById('modal_open_review').addEventListener('click', function () {
            document.getElementById('modal-submit-hidden').value = 1;
            document.getElementById('modal-edit-hidden').value = 0;
            document.getElementById('review-modal-title-edit').hidden = true;
            document.getElementById('review-modal-title-watch').hidden = false;
        });
    }

    for (let i of document.querySelectorAll("button[id^=modal_open_alert]")) {
        i.addEventListener('click', function () {
            transferName(i);
        });
    }

    // clears inputs for the review modal
    document.getElementById("modal_close_review").addEventListener('click', function () {
        document.getElementById('review-input').innerText = '';
        for (let i of document.querySelectorAll("input[id^=star]")) {
            if (i.hasAttribute("checked")) i.removeAttribute("checked");
        }
    });

    // review modal basic form validation
    document.getElementById('review-modal-submit-btn').addEventListener('click', function (evt) {
        let el = document.getElementById('review-input-msg');
        let revEl = document.getElementById('review-input');
        if (revEl.value === '' || revEl.value.length < 5) {
            evt.preventDefault();
            el.style.color = 'red';
            el.innerText = 'Review text must be at least 5 characters long.';
            this.prop('disabled', true);
        } else if (revEl.value.length > 1000) {
            evt.preventDefault();
            el.style.color = 'red';
            el.innerText = 'Review text cannot be over 1000 characters.';
            this.prop('disabled', true);
        } else {
            el.innerText = '';
            this.prop('disabled', true);
        }
    });

    // switches active review tab to recent
    document.getElementById('review-tab-recent').addEventListener('click', function () {
        changeActive(this, document.getElementById('recent-reviews'), document.getElementById('review-tab-all'), document.getElementById('all-reviews'));
    });

    // switches active review tab to all
    document.getElementById('review-tab-all').addEventListener('click', function () {
        changeActive(this, document.getElementById('all-reviews'), document.getElementById('review-tab-recent'), document.getElementById('recent-reviews'));
    });

    document.getElementById('review-tab-all').addEventListener('click', function () {
        showOnLoad(document.getElementById('load-content'));
    });

    for (let i of document.querySelectorAll("button[id^=edit]")) {
        let content = i.dataset.rcontent;
        let rating = i.dataset.rrating;
        let contentEl = document.getElementById('review-input');
        let ratingEl = document.getElementById("star" + rating);
        document.getElementById('modal-submit-hidden').value = 0;
        document.getElementById('modal-edit-hidden').value = 1;
        document.getElementById('review-modal-title-edit').hidden = false;
        document.getElementById('review-modal-title-watch').hidden = true;
        i.addEventListener('click', function () {
            changeModalDisplay(document.getElementById('review-modal'));
        });
        i.addEventListener('click', function () {
            fillEditModal(contentEl, ratingEl, content);
        });
        i.addEventListener('click', function () {
            transferName(i, 'edit');
        });
    }
</script>
<script src="vendors/base/vendor.bundle.base.js"></script>
<!-- endinject -->
<!-- Plugin js for this page-->
<!-- End plugin js for this page-->

<script src="js/jquery.cookie.js" type="text/javascript"></script>
</body>
</html>


