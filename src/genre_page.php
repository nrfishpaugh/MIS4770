<?php
include "include/config.inc";
include "include/utils.php";

$_SESSION[PREFIX . "_ppage"] = $_SERVER['REQUEST_URI'];
if ($_SESSION[PREFIX . '_username'] == "") {
    header("Location: login.php");
    exit;
}

$genre = $_GET['genre'];
if (!$genre) {
    header("location: index.php");
    exit;
}

$page = (isset($_GET['page']) && is_numeric($_GET['page'])) ? intval($_GET['page']) : 1;
if ($page < 1) $page = 1;

$img_url = 'https://image.tmdb.org/t/p/original';

$genre_full = match ($genre) {
    "Action" => "Action & Adventure",
    "SciFi" => "Sci-Fi & Fantasy",
    "War" => "War & Politics",
    default => $genre,
};

$genre_id = $mysqli->get_genre_id($genre_full);

$amt_per_page = 30;
$count = $mysqli->genre_show_count($genre_id);

if ($count <= $amt_per_page) {
    $page = 1;
    $num_pages = 1;
} else {
    // find the number of pages needed to display all results, add one if it doesn't divide cleanly
    $num_pages = ($amt_per_page % $count) === 0 ? intval($count / $amt_per_page) : intval($count / $amt_per_page) + 1;

    if ($page > $num_pages) {
        $page = $num_pages;
    }
}

$results = $results = $mysqli->show_list_genre($genre_full, $amt_per_page, ($amt_per_page * ($page - 1)));

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo $app_name; ?> - <?php echo $genre_full; ?></title>
    <!-- plugins:css -->
    <link rel="stylesheet" href="vendors/mdi/css/materialdesignicons.min.css">
    <link rel="stylesheet" href="vendors/base/vendor.bundle.base.css">
    <!-- endinject -->
    <!-- plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="css/style.css">
    <!-- endinject -->
    <link rel="shortcut icon" href="images/binged_logo.svg"/>

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
                        <div class="d-flex justify-content-between flex-wrap">
                            <div class="d-flex align-items-end flex-wrap">
                                <div class="me-md-3 me-xl-5">
                                    <h2><?php echo $genre_full; ?></h2>
                                </div>
                            </div>

                        </div>

                    </div>
                </div>


                <div class="row no-gutters">
                    <?php
                    foreach ($results as $result) {
                        $temp_url = $img_url . $result['show_poster_path']; ?>
                        <div class="col-4 col-md-3 col-lg-2 grid-margin stretch-card" style="border-radius: 0">
                            <div class="card flex-wrap" style="border-radius: 0">
                                <div class="container-lg">
                                    <div class="card-img">
                                        <a href="show_page.php?id=<?php echo $result['id']; ?>/"><img
                                                    src="<?php echo $temp_url ?>" class="card-img"
                                                    style="max-width: 100%; max-height: 100%; object-fit: scale-down"
                                                    alt=""/></a>
                                    </div>
                                </div>

                                <div class="card-description">
                                    <a href="show_page.php?id=<?php echo $result['id']; ?>"
                                       style="text-decoration: none; color: inherit">
                                        <p class="card-title"
                                           style="white-space: normal; overflow: visible"><?php echo $result['show_name'] ?></p>
                                        <!-- <p class="card-text" style=""><?php //echo $result['show_overview']; ?></p> -->
                                    </a>
                                </div>

                            </div>
                        </div>
                        <?php
                    }
                    if ($count > $amt_per_page) echo pagination_template($page, $num_pages, 0, 0, "genre", "", $genre)
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
<!-- End plugin js for this page-->
<!-- Custom js for this page-->


<!-- End custom js for this page-->

<script src="js/jquery.cookie.js" type="text/javascript"></script>
</body>

</html>