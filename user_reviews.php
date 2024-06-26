<?php
include "include/config.inc";

$_SESSION[PREFIX . "_ppage"] = $_SERVER['REQUEST_URI'];
if ($_SESSION[PREFIX . '_username'] == "") {
    header("Location: login.php");
    exit;
}

$in_id = (int)$_GET['id'];
if (!$in_id) {
    header("location: index.php");
    exit;
}

$user_info = $mysqli->user_info($in_id);

$page_name = "" . $user_info['user_name'] . "'" . "s Reviews";

$img_url = 'https://image.tmdb.org/t/p/original';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $page_name; ?></title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
    <!-- endinject -->
    <!-- plugin css for this page -->
    <link rel="stylesheet" href="vendors/datatables.net-bs4/dataTables.bootstrap4.css">
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="css/style.css">
    <!-- endinject -->
    <link rel="shortcut icon" href="images/favicon.png"/>

</head>
<body>
<div class="container-scroller">

    <?php require_once 'partials/_navbar.php'; ?>
    <div class="container-fluid page-body-wrapper">
        <?php //require_once 'partials/_sidebar.php'; ?>
        <div class="main-panel">
            <div class="content-wrapper">

                <div class="row">
                    <div class="col-md-12 grid-margin">
                        <div class="dashboard-tabs p-0">
                            <ul class="nav nav-tabs px-4">
                                <li class="nav-item">
                                    <a id="profile-tab" class="nav-link"
                                       href="user_profile.php?id=<?php echo $in_id; ?>">Profile</a>
                                </li>
                                <li class="nav-item">
                                    <a id="review-tab" class="nav-link active">Reviews</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-12 grid-margin">
                        <div class="d-flex justify-content-between flex-wrap">
                            <div class="d-flex align-items-end flex-wrap">
                                <div class="me-md-3 me-xl-5">
                                    <h4><?php echo $page_name; ?></h4>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>


                <div class="row no-gutters">
                    <?php
                    $results = $mysqli->user_review_info($in_id);
                    if (empty($results)) { ?>
                        <div class="col-sm-12 grid-margin stretch-card">
                            <div class="card">
                                <div class="card-body">
                                    <div class="card-title">No reviews found
                                        for <?php echo $user_info['user_name'] ?></div>
                                </div>
                            </div>
                        </div>
                    <?php } else {
                        foreach ($results as $result) {
                            $temp_url = $img_url . $result['show_poster_path']; ?>
                            <div class="col-md-6" style="border-radius: 0">
                                <div class="card flex-nowrap" style="border-radius: 0; flex-direction: row">
                                    <div class="container-md-1">
                                        <div class="card-img">
                                            <a href="review_page.php?rid=<?php echo $result['review_id']; ?>&sid=<?php echo $result['id']; ?>&uid=<?php echo $in_id; ?>"><img
                                                        src="<?php echo $temp_url ?>" class="card-img"
                                                        style="max-width: 100%; max-height: 100%; object-fit: scale-down"
                                                        alt=""/></a>
                                        </div>
                                    </div>
                                    <div class="card-description">
                                        <a href="review_page.php?rid=<?php echo $result['review_id']; ?>&sid=<?php echo $result['id']; ?>&uid=<?php echo $in_id; ?>"
                                           style="text-decoration: none; color: inherit">
                                            <p class="card-title"><?php echo $result['show_name']; ?>
                                                <span style="color: #0072ff"><?php switch ($result['review_value']) {
                                                        case 0:
                                                            $r_str = "No Rating";
                                                            break;
                                                        case 1:
                                                            $r_str = "★";
                                                            break;
                                                        case 2:
                                                            $r_str = "★★";
                                                            break;
                                                        case 3:
                                                            $r_str = "★★★";
                                                            break;
                                                        case 4:
                                                            $r_str = "★★★★";
                                                            break;
                                                        case 5:
                                                            $r_str = "★★★★★";
                                                            break;
                                                        default:
                                                            $r_str = "No rating";
                                                            break;
                                                    }
                                                    echo nl2br("\n") . $r_str; ?></span>
                                            </p>
                                            <p class="card-text"><?php echo $result['review_content']; ?></p>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <?php
                        }
                    }
                    ?>
                </div>


            </div>
            <!-- content-wrapper ends -->
            <?php require_once 'partials/_footer.php'; ?>
        </div>
        <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
</div>
<!-- container-scroller -->

<!-- plugins:js -->
<script src="vendors/base/vendor.bundle.base.js"></script>
<!-- endinject -->
<!-- Plugin js for this page-->
<script src="vendors/chart.js/Chart.min.js"></script>
<script src="vendors/datatables.net/jquery.dataTables.js"></script>
<script src="vendors/datatables.net-bs4/dataTables.bootstrap4.js"></script>
<!-- End plugin js for this page-->
<!-- inject:js -->
<script src="js/off-canvas.js"></script>
<script src="js/hoverable-collapse.js"></script>
<script src="js/template.js"></script>
<!-- endinject -->
<!-- Custom js for this page-->


<!-- End custom js for this page-->

<script src="js/jquery.cookie.js" type="text/javascript"></script>
</body>

</html>


